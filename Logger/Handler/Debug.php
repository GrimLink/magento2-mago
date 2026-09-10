<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Logger\Handler;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class Debug extends Base
{
    protected $loggerType = Logger::DEBUG;
    protected $fileName = '/var/log/mago-debug.log';
}
