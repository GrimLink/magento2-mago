<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Skills\Content\CmsData;

use MaggyAssistant\Base\Api\Skill\ActionInterface;
use MaggyAssistant\Base\Service\Api\InternalApiClient;

class CreatePageAction implements ActionInterface
{
    public function __construct(
        private readonly InternalApiClient $apiClient
    ) {
    }

    public function getName(): string
    {
        return 'create_page';
    }

    public function getDescription(): string
    {
        return 'Create a new CMS page';
    }

    public function getParameterSchema(): array
    {
        return [
            'identifier' => [
                'type' => 'string',
                'description' => 'URL key for the page (e.g. "about-us")',
                'required' => true,
            ],
            'title' => [
                'type' => 'string',
                'description' => 'Page title',
                'required' => true,
            ],
            'content' => [
                'type' => 'string',
                'description' => 'Page content (HTML)',
                'required' => true,
            ],
            'content_heading' => [
                'type' => 'string',
                'description' => 'Content heading displayed above the content',
            ],
            'is_active' => [
                'type' => 'boolean',
                'description' => 'Whether the page is enabled (default: true)',
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

        $page = [
            'identifier' => $identifier,
            'title' => $title,
            'content' => $content,
            'active' => ($params['is_active'] ?? true) ? true : false,
        ];

        if (!empty($params['content_heading'])) {
            $page['content_heading'] = $params['content_heading'];
        }

        $result = $this->apiClient->post('cmsPage', ['page' => $page], $adminUserId);

        if (isset($result['error'])) {
            return ['error' => 'Failed to create page: ' . $result['error']];
        }

        return [
            'success' => true,
            'message' => 'Page "' . $identifier . '" created',
            'id' => $result['id'] ?? null,
        ];
    }
}
