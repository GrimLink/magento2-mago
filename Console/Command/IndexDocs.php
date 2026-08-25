<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use MaggyAssistant\Base\Service\Docs\DocsSyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class IndexDocs extends Command
{
    private const STEP_LABELS = [
        'tree' => 'Fetching doc tree',
        'fetch' => 'Downloading docs',
        'normalize' => 'Normalizing',
        'store' => 'Storing in database',
    ];

    public function __construct(
        private readonly DocsSyncService $syncService,
        private readonly State $appState,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('maggy:docs:index')
            ->setDescription('Index the Magento admin documentation corpus for the Maggy assistant.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Re-index even if the source is unchanged.');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Throwable $e) {
            // area may already be set — ignore
        }

        $progressBar = null;
        $currentStep = '';

        $onProgress = function (string $step, int $current, int $total) use ($output, &$progressBar, &$currentStep): void {
            if ($step !== $currentStep) {
                if ($progressBar !== null) {
                    $progressBar->finish();
                    $output->writeln('');
                }
                $currentStep = $step;
                $label = self::STEP_LABELS[$step] ?? $step;
                if ($total > 0) {
                    $progressBar = new ProgressBar($output, $total);
                    $progressBar->setFormat(" %message% [%bar%] %current%/%max%");
                    $progressBar->setMessage($label);
                    $progressBar->start();
                } else {
                    $output->writeln("<comment>{$label}...</comment>");
                    $progressBar = null;
                }
            }
            if ($progressBar !== null && $current > 0) {
                $progressBar->setProgress($current);
            }
        };

        $result = $this->syncService->sync((bool)$input->getOption('force'), $onProgress);

        if ($progressBar !== null) {
            $progressBar->finish();
            $output->writeln('');
        }

        if (isset($result['error'])) {
            $output->writeln('<error>Docs sync failed: ' . $result['error'] . '</error>');
            return Command::FAILURE;
        }

        if (isset($result['skipped'])) {
            $output->writeln('<info>Skipped: ' . $result['skipped']
                . (isset($result['docs']) ? ' (' . $result['docs'] . ' docs)' : '') . '</info>');
            return Command::SUCCESS;
        }

        $output->writeln('<info>Indexed ' . (int)($result['indexed'] ?? 0) . ' docs (sha '
            . substr((string)($result['sha'] ?? ''), 0, 10) . ').</info>');
        return Command::SUCCESS;
    }
}
