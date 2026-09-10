<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class Skills extends Column
{
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            foreach ($dataSource['data']['items'] as &$item) {
                $raw = $item[$fieldName] ?? '';
                if (empty($raw)) {
                    $item[$fieldName] = '';
                    continue;
                }

                $skills = array_unique(array_filter(array_map('trim', explode(',', $raw))));
                $tags = [];
                foreach ($skills as $skill) {
                    $tags[] = '<span style="display:inline-block;background:#f0f0f0;color:#555;font-size:11px;'
                        . 'padding:2px 8px;border-radius:10px;margin:1px 2px;white-space:nowrap">'
                        . htmlspecialchars($skill) . '</span>';
                }
                $item[$fieldName] = implode(' ', $tags);
            }
        }

        return $dataSource;
    }
}
