<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class Price extends Column
{
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item[$fieldName])) {
                    $item[$fieldName] = '$' . number_format((float)$item[$fieldName], 4);
                }
            }
        }

        return $dataSource;
    }
}
