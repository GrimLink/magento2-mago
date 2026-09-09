<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Framework\Serialize\Serializer\Json;
use MaggyAssistant\Base\Api\Config\RepositoryInterface as ConfigRepository;
use MaggyAssistant\Base\Service\Command\CommandRegistry;
use MaggyAssistant\Base\Service\Command\CommandRunner;
use MaggyAssistant\Base\Service\Tool\ToolRegistry;

class ChatPanel extends Template
{
    protected $_template = 'MaggyAssistant_Base::chat/panel.phtml';

    public function __construct(
        Context $context,
        private readonly ConfigRepository $configRepository,
        private readonly AdminSession $adminSession,
        private readonly Json $json,
        private readonly ToolRegistry $toolRegistry,
        private readonly CommandRegistry $commandRegistry,
        private readonly CommandRunner $commandRunner,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isVisible(): bool
    {
        if (!$this->configRepository->isEnabled()) {
            return false;
        }

        // Only show when admin user is logged in
        return $this->adminSession->isLoggedIn();
    }

    public function getJsConfig(): string
    {
        return (string)$this->json->serialize([
            'streamUrl' => $this->getUrl('maggy/chat/stream'),
            'historyUrl' => $this->getUrl('maggy/chat/history'),
            'loadUrl' => $this->getUrl('maggy/chat/load'),
            'deleteUrl' => $this->getUrl('maggy/chat/delete'),
            'confirmUrl' => $this->getUrl('maggy/chat/confirm'),
            'rejectUrl' => $this->getUrl('maggy/chat/reject'),
            'statusUrl' => $this->getUrl('maggy/chat/status'),
            'apiBaseUrl' => $this->getUrl('rest/V1/assistant'),
            'isStreamingEnabled' => $this->configRepository->isStreamingEnabled(),
        ]);
    }

    public function getStreamUrl(): string
    {
        return $this->getUrl('maggy/chat/stream');
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    /**
     * Skills for the slash-command legend: only those the current admin may invoke,
     * described as that admin sees them (write actions omitted for a read-only grant).
     */
    public function getSkillsJson(): string
    {
        $adminUserId = $this->getAdminUserId();
        $skills = [];
        foreach ($this->toolRegistry->getEnabledTools($adminUserId) as $tool) {
            $definition = $this->toolRegistry->getToolDefinition($tool, $adminUserId);
            $skills[] = [
                'name' => $definition['name'],
                'description' => $definition['description'],
                'readOnly' => $tool->isReadOnly() || !$this->toolRegistry->hasWriteAccess($tool, $adminUserId),
            ];
        }
        return (string)$this->json->serialize($skills);
    }

    /**
     * Slash commands for the menu: only the subcommands the current admin may run.
     */
    public function getCommandsJson(): string
    {
        $adminUserId = $this->getAdminUserId();
        $commands = [];
        foreach ($this->commandRegistry->getAvailable($adminUserId) as $command) {
            $subcommands = [];
            foreach ($this->commandRunner->getAvailableSubcommands($command, $adminUserId) as $name => $definition) {
                $subcommands[] = [
                    'name' => $name,
                    'args' => $definition['args'],
                    'description' => $definition['description'],
                    'readOnly' => $definition['readOnly'],
                ];
            }
            if ($subcommands === []) {
                continue;
            }
            $commands[] = [
                'name' => $command->getName(),
                'description' => $command->getDescription(),
                'subcommands' => $subcommands,
            ];
        }
        return (string)$this->json->serialize($commands);
    }

    private function getAdminUserId(): ?int
    {
        $user = $this->adminSession->getUser();
        return $user && $user->getId() ? (int)$user->getId() : null;
    }

    public function getAdminFirstName(): string
    {
        $user = $this->adminSession->getUser();
        if (!$user) {
            return '';
        }
        return $user->getFirstName() ?: $user->getUserName();
    }

    public function getAccentColor(): string
    {
        return $this->configRepository->getAccentColor();
    }

    public function getTextColor(): string
    {
        return $this->configRepository->getTextColor();
    }

    public function getAssistantName(): string
    {
        return $this->configRepository->getAssistantName();
    }
}
