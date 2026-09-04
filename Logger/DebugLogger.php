<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Logger;

use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class DebugLogger
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Json $json
    ) {
    }

    /**
     * @param string $type
     * @param mixed $data
     * @return void
     */
    public function addLog(string $type, $data): void
    {
        $message = $type . ': ';

        if (is_array($data) || is_object($data)) {
            $message .= $this->json->serialize(is_object($data) ? (array)$data : $data);
        } else {
            $message .= (string)$data;
        }

        $this->logger->info($message);
    }
}
