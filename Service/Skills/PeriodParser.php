<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Skills;

class PeriodParser
{
    /**
     * Parse period string into [from, to] date strings
     *
     * @return string[] [from, to]
     */
    public function parse(string $period): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return match ($period) {
            'today' => [$now->format('Y-m-d 00:00:00'), $now->format('Y-m-d 23:59:59')],
            'yesterday' => [
                $now->modify('-1 day')->format('Y-m-d 00:00:00'),
                $now->modify('-1 day')->format('Y-m-d 23:59:59'),
            ],
            '7days' => [$now->modify('-7 days')->format('Y-m-d 00:00:00'), $now->format('Y-m-d 23:59:59')],
            '30days' => [$now->modify('-30 days')->format('Y-m-d 00:00:00'), $now->format('Y-m-d 23:59:59')],
            'this_month' => [$now->format('Y-m-01 00:00:00'), $now->format('Y-m-d 23:59:59')],
            'last_month' => [
                $now->modify('first day of last month')->format('Y-m-d 00:00:00'),
                $now->modify('last day of last month')->format('Y-m-d 23:59:59'),
            ],
            'this_year' => [$now->format('Y-01-01 00:00:00'), $now->format('Y-m-d 23:59:59')],
            default => $this->parseDateRange($period, $now),
        };
    }

    /**
     * Get "from" date string for a period (no "to" date)
     */
    public function getFromDate(string $period): string
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return match ($period) {
            '7days' => $now->modify('-7 days')->format('Y-m-d 00:00:00'),
            '30days' => $now->modify('-30 days')->format('Y-m-d 00:00:00'),
            'this_month' => $now->format('Y-m-01 00:00:00'),
            'this_year' => $now->format('Y-01-01 00:00:00'),
            default => $now->modify('-30 days')->format('Y-m-d 00:00:00'),
        };
    }

    private function parseDateRange(string $period, \DateTimeImmutable $now): array
    {
        if (str_contains($period, ':')) {
            $parts = explode(':', $period);
            return [$parts[0] . ' 00:00:00', $parts[1] . ' 23:59:59'];
        }

        return [$now->modify('-30 days')->format('Y-m-d 00:00:00'), $now->format('Y-m-d 23:59:59')];
    }
}
