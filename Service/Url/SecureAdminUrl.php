<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Url;

use Magento\Backend\Model\UrlInterface as BackendUrl;

class SecureAdminUrl
{
    public function __construct(
        private readonly BackendUrl $backendUrl
    ) {
    }

    public function getUrl(string $route, array $params = []): string
    {
        return $this->backendUrl->getUrl($route, $params);
    }
}
