<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Logger\Handler;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class Error extends Base
{
    protected $loggerType = Logger::ERROR;
    protected $fileName = '/var/log/mago-error.log';
}
