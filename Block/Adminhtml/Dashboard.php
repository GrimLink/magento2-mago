<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use MaggyAssistant\Base\Api\Config\RepositoryInterface as ConfigRepository;
use MaggyAssistant\Base\Service\Usage\UsageStats;

class Dashboard extends Template
{
    protected $_template = 'MaggyAssistant_Base::dashboard/index.phtml';

    public function __construct(
        Context $context,
        private readonly UsageStats $usageStats,
        private readonly ConfigRepository $configRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getAccentColor(): string
    {
        return $this->configRepository->getAccentColor();
    }

    public function getPeriod(): string
    {
        return $this->getRequest()->getParam('period', '30days');
    }

    public function getStats(): array
    {
        return [
            'today' => $this->usageStats->getForPeriod('today'),
            'week' => $this->usageStats->getForPeriod('7days'),
            'month' => $this->usageStats->getForPeriod('30days'),
        ];
    }

    public function getUserStats(): array
    {
        return $this->usageStats->getUserBreakdown($this->getPeriod());
    }

    public function getSkillStats(): array
    {
        return $this->usageStats->getSkillBreakdown($this->getPeriod());
    }

    public function getDailyTrend(): array
    {
        $period = $this->getPeriod();
        $days = match ($period) {
            'today' => 1,
            '7days' => 7,
            '30days' => 30,
            'all' => 90,
            default => 30,
        };
        return $this->usageStats->getDailyTrend($days);
    }

    public function formatTokens(int $tokens): string
    {
        if ($tokens >= 1000000) {
            return round($tokens / 1000000, 1) . 'M';
        }
        if ($tokens >= 1000) {
            return round($tokens / 1000, 1) . 'K';
        }
        return (string)$tokens;
    }

    public function formatCost(float $cost): string
    {
        return '$' . number_format($cost, 2);
    }
}
