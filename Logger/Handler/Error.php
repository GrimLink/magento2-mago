<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Logger\Handler;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class Error extends Base
{
    protected $loggerType = Logger::ERROR;
    protected $fileName = '/var/log/maggy-error.log';
}
