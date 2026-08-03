<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Docs;

use Magento\Framework\FlagManager;
use MaggyAssistant\Base\Api\Config\RepositoryInterface as ConfigRepository;
use MaggyAssistant\Base\Logger\ErrorLogger;
use MaggyAssistant\Base\Model\Doc\Repository as DocRepository;

class DocsSyncService
{
    private const FLAG_SHA = 'maggy_docs_source_sha';
    private const FLAG_SYNCED_AT = 'maggy_docs_synced_at';
    private const FLAG_ERROR = 'maggy_docs_last_error';
    private const VARCHAR_MAX = 512;

    public function __construct(
        private readonly ConfigRepository $config,
        private readonly GitHubDocsSource $source,
        private readonly ExlMarkdownNormalizer $normalizer,
        private readonly DocRepository $docRepository,
        private readonly FlagManager $flagManager,
        private readonly ErrorLogger $errorLogger
    ) {
    }

    /**
     * @return array<string, mixed> summary of what happened
     */
    public function sync(bool $force = false): array
    {
        if (!$this->config->isDocsEnabled()) {
            return ['skipped' => 'disabled'];
        }

        $repo = $this->config->getDocsSourceRepo();
        $ref = $this->config->getDocsRef();

        try {
            $tree = $this->source->fetchTree($repo, $ref);
            if ($tree === null) {
                throw new \RuntimeException('Could not fetch doc tree from ' . $repo . '@' . $ref);
            }

            $sha = $tree['sha'];
            $storedSha = (string)$this->flagManager->getFlagData(self::FLAG_SHA);
            $count = $this->docRepository->count();

            if (!$force && $sha !== '' && $sha === $storedSha && $count > 0) {
                return ['skipped' => 'up-to-date', 'sha' => $sha, 'docs' => $count];
            }

            $rawByPath = [];
            foreach ($tree['paths'] as $path) {
                $content = $this->source->fetchRaw($repo, $ref, $path);
                if ($content !== null) {
                    $rawByPath[$path] = $content;
                }
            }

            if (!$rawByPath) {
                throw new \RuntimeException('Fetched 0 files from ' . $repo . '@' . $ref . ' — keeping existing corpus');
            }

            $resolver = static function (string $include) use ($rawByPath): ?string {
                $path = ltrim($include, '/');
                return $rawByPath[$path] ?? null;
            };

            $rows = [];
            foreach ($rawByPath as $path => $raw) {
                if (!$this->normalizer->isIndexable($path)) {
                    continue;
                }
                $normalized = $this->normalizer->normalize($raw, $resolver);
                if ($normalized['content'] === '') {
                    continue;
                }
                $title = $normalized['title'] !== '' ? $normalized['title'] : $path;
                $rows[] = [
                    'source' => $repo,
                    'path' => mb_substr($path, 0, self::VARCHAR_MAX),
                    'title' => mb_substr($title, 0, self::VARCHAR_MAX),
                    'description' => $normalized['description'],
                    'tags' => $normalized['tags'],
                    'edition' => $this->normalizer->edition($path),
                    'url' => $this->normalizer->url($repo, $ref, $path),
                    'content' => $normalized['content'],
                    'source_sha' => $sha,
                ];
            }

            if (!$rows) {
                throw new \RuntimeException('No indexable docs produced (0 rows) — keeping existing corpus');
            }

            $this->docRepository->replaceAll($rows);

            $this->flagManager->saveFlag(self::FLAG_SHA, $sha);
            $this->flagManager->saveFlag(self::FLAG_SYNCED_AT, time());
            $this->flagManager->deleteFlag(self::FLAG_ERROR);

            return ['indexed' => count($rows), 'sha' => $sha];
        } catch (\Throwable $e) {
            $this->flagManager->saveFlag(self::FLAG_ERROR, $e->getMessage());
            $this->errorLogger->addLog('DocsSync', $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}
