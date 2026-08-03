<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Skills\Docs\DocsSearch;

use MaggyAssistant\Base\Api\Skill\ActionInterface;
use MaggyAssistant\Base\Model\Doc\Repository as DocRepository;

class GetDocAction implements ActionInterface
{
    private const CONTENT_CAP = 24576; // ~24 KB (~6k tokens)

    public function __construct(
        private readonly DocRepository $docRepository
    ) {
    }

    public function getName(): string
    {
        return 'get_doc';
    }

    public function getDescription(): string
    {
        return 'Fetch the full text of one documentation page by its id (from search results). '
            . 'Answer the user from it and cite its url.';
    }

    public function getParameterSchema(): array
    {
        return [
            'id' => [
                'type' => 'integer',
                'description' => 'The doc id returned by the "search" action.',
            ],
        ];
    }

    public function getAclResource(): ?string
    {
        return null;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getInstructions(): string
    {
        return '';
    }

    public function execute(array $params, int $adminUserId): array
    {
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            return ['error' => 'A numeric doc "id" (from search results) is required.'];
        }

        $doc = $this->docRepository->getById($id);
        if ($doc === null) {
            return ['error' => 'Doc not found: ' . $id];
        }

        $content = (string)($doc['content'] ?? '');
        $bytes = strlen($content);
        if ($bytes > self::CONTENT_CAP) {
            $remaining = (int)ceil(($bytes - self::CONTENT_CAP) / 1024);
            $content = mb_strcut($content, 0, self::CONTENT_CAP)
                . "\n\n[truncated, {$remaining} KB remaining — ask about a specific section to see more]";
        }

        return [
            'id' => $id,
            'title' => (string)($doc['title'] ?? ''),
            'url' => (string)($doc['url'] ?? ''),
            'edition' => ($doc['edition'] ?? null) ?: null,
            'content' => $content,
        ];
    }
}
