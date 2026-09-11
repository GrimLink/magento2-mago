<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Controller\Adminhtml\Chat;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Serialize\Serializer\Json;
use MagoAssistant\Mago\Api\ChatServiceInterface;
use MagoAssistant\Mago\Api\Config\RepositoryInterface as ConfigRepository;
use MagoAssistant\Mago\Api\ConversationRepositoryInterface;
use MagoAssistant\Mago\Logger\DebugLogger;
use MagoAssistant\Mago\Logger\ErrorLogger;
use MagoAssistant\Mago\Service\Ai\Client;
use MagoAssistant\Mago\Service\Command\CommandRunner;

class Stream extends Action implements HttpPostActionInterface
{
    use FormKeyJsonValidation;

    public const ADMIN_RESOURCE = 'MagoAssistant_Mago::assistant_read';

    public function __construct(
        Context $context,
        private readonly ChatServiceInterface $chatService,
        private readonly ConversationRepositoryInterface $conversationRepository,
        private readonly ConfigRepository $configRepository,
        private readonly Client $client,
        private readonly Json $json,
        private readonly ErrorLogger $errorLogger,
        private readonly DebugLogger $debugLogger,
        private readonly FormKey $formKey,
        private readonly CommandRunner $commandRunner
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface|HttpResponse
    {
        /** @var HttpResponse $response */
        $response = $this->getResponse();
        $response->setHeader('Content-Type', 'text/event-stream', true);
        $response->setHeader('Cache-Control', 'no-cache', true);
        $response->setHeader('Connection', 'keep-alive', true);
        $response->setHeader('X-Accel-Buffering', 'no', true);
        $response->sendHeaders();

        // Disable all output buffering for SSE
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_implicit_flush(true);

        try {
            $rawBody = $this->getRequest()->getContent();
            $this->debugLogger->addLog('Stream Request', ['raw_body' => $rawBody]);

            $postData = $this->json->unserialize($rawBody);

            $message = $postData['message'] ?? '';
            $conversationId = !empty($postData['conversation_id']) ? (int)$postData['conversation_id'] : null;

            if (!$message) {
                $this->sendSse('error', ['error' => 'Message is required']);
                $this->sendSse('done', []);
                $this->terminateResponse();
            }

            $user = $this->_auth->getUser();
            if (!$user) {
                $this->debugLogger->addLog('Stream', 'No admin user in session');
                $this->sendSse('error', ['error' => 'Admin user session not found']);
                $this->sendSse('done', []);
                $this->terminateResponse();
            }

            if (!$this->configRepository->isEnabled()) {
                $this->sendSse('error', ['error' => 'The assistant is currently disabled. Enable it in Stores > Configuration > Mago Assistant.']);
                $this->sendSse('done', []);
                $this->terminateResponse();
            }

            $adminUserId = (int)$user->getId();
            $adminName = $user->getFirstName() ?: $user->getUserName();

            // Slash commands run against Magento directly and need no AI provider
            if ($this->commandRunner->isCommand($message)) {
                $this->runCommand($message, $conversationId, $adminUserId, $adminName);
            }

            // Before the conversation row exists: an unconfigured store would otherwise persist the
            // question and then fail, leaving a conversation nobody ever got an answer to.
            try {
                $this->client->resolve();
            } catch (\Throwable $e) {
                $this->sendSse('error', ['error' => $e->getMessage()]);
                $this->sendSse('done', []);
                $this->terminateResponse();
            }

            $conversationId = $this->resolveConversation($conversationId, $adminUserId, $message);
            $this->conversationRepository->addMessage($conversationId, 'user', $message);

            $messages = $this->conversationRepository->getMessages($conversationId);
            $formattedMessages = [];

            // Collect all tool response IDs to validate tool_call chains
            $toolResponseIds = [];
            foreach ($messages as $msg) {
                if (($msg['role'] ?? '') === 'tool' && !empty($msg['tool_call_id'])) {
                    $toolResponseIds[$msg['tool_call_id']] = true;
                }
            }

            foreach ($messages as $msg) {
                $role = $msg['role'] ?? 'user';
                $content = $msg['content'] ?? '';

                if (!empty($msg['tool_calls'])) {
                    $tc = $msg['tool_calls'];
                    if (is_string($tc)) {
                        try {
                            $tc = json_decode($tc, true, 512, JSON_THROW_ON_ERROR);
                        } catch (\Throwable $e) {
                            $tc = [];
                        }
                    }
                    // Only include tool_calls if all responses exist (prevents API errors)
                    $allResolved = true;
                    foreach ($tc as $call) {
                        if (!isset($toolResponseIds[$call['id'] ?? ''])) {
                            $allResolved = false;
                            break;
                        }
                    }
                    if ($allResolved && !empty($tc)) {
                        $entry = ['role' => $role, 'content' => $content, 'tool_calls' => $tc];
                    } else {
                        // Tool calls without responses (rejected/abandoned confirmation) —
                        // skip this message entirely if it has no text content
                        if (empty(trim($content))) {
                            continue;
                        }
                        $entry = ['role' => $role, 'content' => $content];
                    }
                } elseif ($role === 'tool') {
                    // Only include tool responses if they have matching tool_calls already included
                    $toolCallId = $msg['tool_call_id'] ?? '';
                    if ($toolCallId && !isset($toolResponseIds[$toolCallId])) {
                        continue;
                    }
                    $entry = ['role' => $role, 'content' => $content];
                    if ($toolCallId) {
                        $entry['tool_call_id'] = $toolCallId;
                    }
                } else {
                    $entry = ['role' => $role, 'content' => $content];
                    if (!empty($msg['tool_call_id'])) {
                        $entry['tool_call_id'] = $msg['tool_call_id'];
                    }
                }

                $formattedMessages[] = $entry;
            }

            $this->sendSse('conversation', [
                'conversation_id' => $conversationId,
                'admin_user' => $adminName,
            ]);

            $this->debugLogger->addLog('Stream', [
                'conversation_id' => $conversationId,
                'message_count' => count($formattedMessages),
                'admin_user' => $adminName,
            ]);

            $result = $this->chatService->processMessageStreaming(
                $formattedMessages,
                function (string $type, array $data) {
                    $this->sendSse($type, $data);
                },
                $conversationId,
                $adminUserId
            );

            $this->debugLogger->addLog('Stream Result', [
                'content_length' => strlen($result['content'] ?? ''),
                'tool_calls_count' => count($result['tool_calls'] ?? []),
            ]);

            $content = $result['content'] ?? '';
            $pendingConfirmation = !empty($result['pending_confirmation']);

            $messageId = $this->conversationRepository->addMessage(
                $conversationId,
                'assistant',
                $content,
                $result['tool_calls'] ?? null,
                $pendingConfirmation
            );

            $this->sendSse('done', [
                'message_id' => $messageId,
                'conversation_id' => $conversationId,
                'pending_confirmation' => $pendingConfirmation,
            ], true);
        } catch (\Throwable $e) {
            $this->errorLogger->addLog('Stream Controller', $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->sendSse('error', ['error' => $e->getMessage()]);
            $this->sendSse('done', [], true);
        }

        $this->terminateResponse();
    }

    /**
     * Answer a slash command: persist the exchange like a normal turn, then stream the reply as one
     * text chunk. The command's tool_status events pass straight through to the panel.
     */
    private function runCommand(string $message, ?int $conversationId, int $adminUserId, string $adminName): never
    {
        $conversationId = $this->resolveConversation($conversationId, $adminUserId, $message);
        $this->conversationRepository->addMessage($conversationId, 'user', $message);

        $this->sendSse('conversation', [
            'conversation_id' => $conversationId,
            'admin_user' => $adminName,
        ]);

        $content = $this->commandRunner->run(
            $message,
            $adminUserId,
            function (string $type, array $data) {
                $this->sendSse($type, $data);
            }
        );
        $this->debugLogger->addLog('Slash Command', ['message' => $message, 'content_length' => strlen($content)]);

        $this->sendSse('text', ['text' => $content]);
        $messageId = $this->conversationRepository->addMessage($conversationId, 'assistant', $content);
        $this->sendSse('done', [
            'message_id' => $messageId,
            'conversation_id' => $conversationId,
            'pending_confirmation' => false,
        ], true);
        $this->terminateResponse();
    }

    /**
     * Existing conversation of this admin, or a new one titled after the message
     */
    private function resolveConversation(?int $conversationId, int $adminUserId, string $message): int
    {
        if (!$conversationId) {
            return $this->conversationRepository->create($adminUserId, mb_substr($message, 0, 50));
        }

        // Reject posting into another admin's conversation
        $this->conversationRepository->getByIdForUser($conversationId, $adminUserId);

        return $conversationId;
    }

    private function sendSse(string $event, array $data, bool $pad = false): void
    {
        // phpcs:ignore Magento2.Security.LanguageConstruct.DirectOutput
        $payload = "event: {$event}\ndata: " . json_encode($data) . "\n\n";
        if ($pad || $event === 'tool_status') {
            // Add SSE comment padding to push data through network/proxy buffers (4KB)
            $payload .= str_repeat(": \n", max(0, (int)ceil((8192 - strlen($payload)) / 3)));
        }
        echo $payload;
        flush();
    }

    /**
     * Terminate response to prevent Magento from sending its own HTML response.
     * SSE requires direct output, Magento's response object would override our headers.
     *
     * @SuppressWarnings("PHPMD.ExitExpression")
     */
    private function terminateResponse(): never
    {
        // phpcs:ignore Magento2.Security.LanguageConstruct.ExitUsage
        exit(0);
    }
}
