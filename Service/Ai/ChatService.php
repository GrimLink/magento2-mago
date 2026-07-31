<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Ai;

use MaggyAssistant\Base\Api\ChatServiceInterface;
use MaggyAssistant\Base\Api\Config\RepositoryInterface as ConfigRepository;
use MaggyAssistant\Base\Logger\DebugLogger;
use MaggyAssistant\Base\Logger\ErrorLogger;
use Magento\Framework\AuthorizationInterface;
use MaggyAssistant\Base\Model\Ai\ProviderFactory;
use MaggyAssistant\Base\Service\Tool\ToolRegistry;
use MaggyAssistant\Base\Service\Usage\UsageLogger;

class ChatService implements ChatServiceInterface
{
    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly ProviderFactory $providerFactory,
        private readonly ToolRegistry $toolRegistry,
        private readonly DebugLogger $debugLogger,
        private readonly ErrorLogger $errorLogger,
        private readonly UsageLogger $usageLogger,
        private readonly AuthorizationInterface $authorization
    ) {
    }

    public function processMessage(array $messages, ?int $conversationId = null, ?int $adminUserId = null): array
    {
        $provider = $this->providerFactory->create();
        $tools = $this->toolRegistry->getToolDefinitions();
        $maxIterations = $this->configRepository->getMaxToolIterations();

        $messages = $this->prependSystemMessage($messages);
        $instructedTools = [];

        for ($i = 0; $i < $maxIterations; $i++) {
            try {
                $response = $provider->chat($messages, $tools);
            } catch (\Throwable $e) {
                $this->errorLogger->addLog('ChatService', $e->getMessage());
                return ['content' => 'An error occurred: ' . $e->getMessage(), 'tool_calls' => []];
            }

            $this->logUsage($response, $adminUserId, $conversationId, $provider, $messages);

            if (empty($response['tool_calls'])) {
                return $response;
            }

            // Check if any tool call requires confirmation (write action)
            foreach ($response['tool_calls'] as $toolCall) {
                $tool = $this->toolRegistry->getTool($toolCall['name']);
                if ($tool && !$tool->isReadOnlyAction($toolCall['input'] ?? [])) {
                    return [
                        'content' => $response['content'],
                        'tool_calls' => $response['tool_calls'],
                        'pending_confirmation' => true,
                    ];
                }
            }

            // Execute read-only tool calls and continue the loop
            $messages[] = [
                'role' => 'assistant',
                'content' => $response['content'],
                'tool_calls' => $response['tool_calls'],
            ];

            foreach ($response['tool_calls'] as $toolCall) {
                $result = $this->executeTool($toolCall, $adminUserId);
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall['id'],
                    'content' => json_encode($result),
                ];

                $this->injectToolInstructions($toolCall['name'], $messages, $instructedTools);
            }
        }

        return ['content' => 'Maximum tool iterations reached.', 'tool_calls' => []];
    }

    public function processMessageStreaming(array $messages, callable $onChunk, ?int $conversationId = null, ?int $adminUserId = null): array
    {
        $provider = $this->providerFactory->create();
        $tools = $this->toolRegistry->getToolDefinitions();
        $maxIterations = $this->configRepository->getMaxToolIterations();

        $messages = $this->prependSystemMessage($messages);
        $instructedTools = [];

        for ($i = 0; $i < $maxIterations; $i++) {
            try {
                $response = $provider->stream($messages, $tools, [], $onChunk);
            } catch (\Throwable $e) {
                $this->errorLogger->addLog('ChatService Stream', $e->getMessage());
                throw $e;
            }

            $this->logUsage($response, $adminUserId, $conversationId, $provider, $messages);

            if (empty($response['tool_calls'])) {
                return $response;
            }

            // Check for write actions needing confirmation
            foreach ($response['tool_calls'] as $toolCall) {
                $tool = $this->toolRegistry->getTool($toolCall['name']);
                if ($tool && !$tool->isReadOnlyAction($toolCall['input'] ?? [])) {
                    // Send confirm event with tool details so frontend can show what will happen
                    $confirmTools = [];
                    foreach ($response['tool_calls'] as $tc) {
                        $t = $this->toolRegistry->getTool($tc['name']);
                        if ($t && !$t->isReadOnlyAction($tc['input'] ?? [])) {
                            $confirmTools[] = [
                                'name' => $tc['name'],
                                'description' => $t->getDescription(),
                                'input' => $tc['input'] ?? [],
                            ];
                        }
                    }
                    $onChunk('confirm', ['tools' => $confirmTools]);
                    return [
                        'content' => $response['content'],
                        'tool_calls' => $response['tool_calls'],
                        'pending_confirmation' => true,
                    ];
                }
            }

            // Execute read-only tools and loop
            $messages[] = [
                'role' => 'assistant',
                'content' => $response['content'],
                'tool_calls' => $response['tool_calls'],
            ];

            foreach ($response['tool_calls'] as $toolCall) {
                $statusMsg = $this->getToolStatusMessage(
                    $toolCall['name'],
                    $toolCall['input']['action'] ?? '',
                    $toolCall['input'] ?? []
                );
                $onChunk('tool_status', [
                    'name' => $toolCall['name'],
                    'status' => 'running',
                    'message' => $statusMsg,
                ]);

                $result = $this->executeTool($toolCall, $adminUserId);

                $onChunk('tool_status', [
                    'name' => $toolCall['name'],
                    'status' => 'done',
                ]);

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall['id'],
                    'content' => json_encode($result),
                ];

                $this->injectToolInstructions($toolCall['name'], $messages, $instructedTools);
            }
        }

        return ['content' => 'Maximum tool iterations reached.', 'tool_calls' => []];
    }

    /**
     * Execute a confirmed write action
     *
     * @param array $toolCalls
     * @return array Results keyed by tool call ID
     */
    public function executeConfirmedTools(array $toolCalls, ?int $adminUserId = null, ?callable $onChunk = null): array
    {
        $results = [];
        foreach ($toolCalls as $toolCall) {
            if ($onChunk) {
                $statusMsg = $this->getToolStatusMessage(
                    $toolCall['name'],
                    $toolCall['input']['action'] ?? '',
                    $toolCall['input'] ?? []
                );
                $onChunk('tool_status', [
                    'name' => $toolCall['name'],
                    'status' => 'running',
                    'message' => $statusMsg,
                ]);
            }

            $results[$toolCall['id']] = $this->executeTool($toolCall, $adminUserId);

            if ($onChunk) {
                $onChunk('tool_status', [
                    'name' => $toolCall['name'],
                    'status' => 'done',
                ]);
            }
        }
        return $results;
    }

    private function logUsage(
        array $response,
        ?int $adminUserId,
        ?int $conversationId,
        \MaggyAssistant\Base\Api\Ai\ProviderInterface $provider,
        ?array $messages = null
    ): void {
        if (empty($response['usage'])) {
            return;
        }

        try {
            $this->usageLogger->log(
                $adminUserId ?? 0,
                $conversationId,
                $provider->getProviderName(),
                $this->configRepository->getModel(),
                $response['usage']['input_tokens'] ?? 0,
                $response['usage']['output_tokens'] ?? 0,
                array_column($response['tool_calls'] ?? [], 'name'),
                $messages,
                [
                    'content' => $response['content'] ?? '',
                    'tool_calls' => $response['tool_calls'] ?? [],
                ]
            );
        } catch (\Throwable $e) {
            $this->errorLogger->addLog('UsageLogger', $e->getMessage());
        }
    }

    private function executeTool(array $toolCall, ?int $adminUserId = null): array
    {
        $tool = $this->toolRegistry->getTool($toolCall['name']);
        if (!$tool) {
            return ['error' => 'Tool not found: ' . $toolCall['name']];
        }

        // Check Magento-native ACL if the tool requires it
        $magentoAcl = $tool->getMagentoAcl();
        if ($magentoAcl && !$this->authorization->isAllowed($magentoAcl)) {
            return ['error' => sprintf(
                'Access denied: you do not have the required Magento permission (%s) to use the %s tool',
                $magentoAcl,
                $tool->getName()
            )];
        }

        try {
            if ($this->configRepository->isDebugEnabled()) {
                $this->debugLogger->addLog('Tool Execute', [
                    'tool' => $toolCall['name'],
                    'input' => $toolCall['input'] ?? [],
                ]);
            }
            $input = $toolCall['input'] ?? [];
            if ($adminUserId !== null) {
                $input['_admin_user_id'] = $adminUserId;
            }
            $result = $tool->execute($input);
            if ($this->configRepository->isDebugEnabled()) {
                $this->debugLogger->addLog('Tool Result', ['tool' => $toolCall['name'], 'result' => $result]);
            }
            return $result;
        } catch (\Throwable $e) {
            $this->errorLogger->addLog('Tool Error', [
                'tool' => $toolCall['name'],
                'error' => $e->getMessage(),
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Inject tool instructions once per tool per conversation (JIT)
     */
    private function injectToolInstructions(string $toolName, array &$messages, array &$instructedTools): void
    {
        if (isset($instructedTools[$toolName])) {
            return;
        }

        $tool = $this->toolRegistry->getTool($toolName);
        $instructions = $tool ? $tool->getInstructions() : '';
        if ($instructions) {
            $messages[] = [
                'role' => 'system',
                'content' => "[Instructions for {$toolName}]\n{$instructions}",
            ];
            if ($this->configRepository->isDebugEnabled()) {
                $this->debugLogger->addLog('JIT Instructions', ['tool' => $toolName]);
            }
        }
        $instructedTools[$toolName] = true;
    }

    private function getToolStatusMessage(string $toolName, string $action, array $input): string
    {
        $messages = [
            'sales_data.revenue_summary' => 'Calculating revenue...',
            'sales_data.recent_orders' => 'Fetching recent orders...',
            'sales_data.lookup_order' => 'Looking up order...',
            'sales_data.get_order_details' => 'Fetching order details...',
            'product_data.search' => 'Searching products...',
            'product_data.low_stock' => 'Checking low stock...',
            'product_data.get_by_sku' => 'Fetching product...',
            'customer_data.lookup_customer' => 'Searching for customer...',
            'customer_data.recent_customers' => 'Fetching recent customers...',
            'cms_data.create_page' => 'Creating CMS page...',
            'cms_data.update_page' => 'Updating CMS page...',
            'cms_data.list_pages' => 'Listing CMS pages...',
            'cms_data.create_block' => 'Creating CMS block...',
            'cms_data.update_block' => 'Updating CMS block...',
            'cms_data.list_blocks' => 'Listing CMS blocks...',
            'config_reader' => 'Reading configuration...',
            'config_writer' => 'Updating configuration...',
            'cache_manager.flush' => 'Flushing cache...',
            'cache_manager.status' => 'Checking cache status...',
            'indexer_manager.reindex' => 'Reindexing...',
            'indexer_manager.status' => 'Checking indexer status...',
            'order_manager.create_shipment' => 'Creating shipment...',
            'order_manager.create_invoice' => 'Creating invoice...',
            'order_manager.create_creditmemo' => 'Creating credit memo...',
            'order_manager.add_comment' => 'Adding order comment...',
            'order_manager.cancel' => 'Cancelling order...',
            'order_manager.hold' => 'Holding order...',
            'order_manager.unhold' => 'Removing hold from order...',
        ];

        $key = $action ? "{$toolName}.{$action}" : $toolName;

        // Try exact match first
        if (isset($messages[$key])) {
            $msg = $messages[$key];

            // Add context from input
            if ($action === 'lookup_order' && !empty($input['order_number'])) {
                return 'Looking up order #' . $input['order_number'] . '...';
            }
            if ($action === 'get_by_sku' && !empty($input['query'])) {
                return 'Fetching product ' . $input['query'] . '...';
            }
            if ($action === 'lookup_customer' && !empty($input['search'])) {
                return 'Searching for customer ' . $input['search'] . '...';
            }

            return $msg;
        }

        // Try tool-level match (no action)
        if (isset($messages[$toolName])) {
            return $messages[$toolName];
        }

        return 'Running ' . $toolName . '...';
    }

    private function prependSystemMessage(array $messages): array
    {
        $systemPrompt = $this->configRepository->getSystemPrompt();
        $hasSystem = false;
        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') === 'system') {
                $hasSystem = true;
                break;
            }
        }

        if (!$hasSystem) {
            array_unshift($messages, ['role' => 'system', 'content' => $systemPrompt]);
        }

        return $messages;
    }
}
