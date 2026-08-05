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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class IndexDocs extends Command
{
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

        $result = $this->syncService->sync((bool)$input->getOption('force'));

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
