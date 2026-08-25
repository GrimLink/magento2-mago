<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Skills\Content\CmsData;

use MaggyAssistant\Base\Api\Skill\ActionInterface;
use MaggyAssistant\Base\Service\Api\InternalApiClient;

class CreateBlockAction implements ActionInterface
{
    public function __construct(
        private readonly InternalApiClient $apiClient
    ) {
    }

    public function getName(): string
    {
        return 'create_block';
    }

    public function getDescription(): string
    {
        return 'Create a new CMS block';
    }

    public function getParameterSchema(): array
    {
        return [
            'identifier' => [
                'type' => 'string',
                'description' => 'Block identifier (e.g. "footer-links")',
                'required' => true,
            ],
            'title' => [
                'type' => 'string',
                'description' => 'Block title',
                'required' => true,
            ],
            'content' => [
                'type' => 'string',
                'description' => 'Block content (HTML)',
                'required' => true,
            ],
            'is_active' => [
                'type' => 'boolean',
                'description' => 'Whether the block is enabled (default: true)',
            ],
        ];
    }

    public function getAclResource(): ?string
    {
        return null;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function getInstructions(): string
    {
        return '';
    }

    public function execute(array $params, int $adminUserId): array
    {
        $identifier = $params['identifier'] ?? '';
        $title = $params['title'] ?? '';
        $content = $params['content'] ?? '';

        if (!$identifier || !$title) {
            return ['error' => 'Both identifier and title are required'];
        }

        if (!$adminUserId) {
            return ['error' => 'Admin user context is required'];
        }

        $block = [
            'identifier' => $identifier,
            'title' => $title,
            'content' => $content,
            'active' => ($params['is_active'] ?? true) ? true : false,
        ];

        $result = $this->apiClient->post('cmsBlock', ['block' => $block], $adminUserId);

        if (isset($result['error'])) {
            return ['error' => 'Failed to create block: ' . $result['error']];
        }

        return [
            'success' => true,
            'message' => 'Block "' . $identifier . '" created',
            'id' => $result['id'] ?? null,
        ];
    }
}
