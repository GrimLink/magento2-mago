<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Api;

use MagoAssistant\Mago\Logger\ErrorLogger;

final class CleartextTokenWarning
{
    private const SCHEME_HTTPS = 'https';
    private const LOOPBACK_HOSTS = ['localhost', '::1', '[::1]'];
    private const LOOPBACK_IPV4_PREFIX = '127.';
    private const LOG_TYPE = 'InternalAPI cleartext token warning';

    /** @var array<string, bool> */
    private array $warnedHosts = [];

    public function __construct(
        private readonly ErrorLogger $errorLogger
    ) {
    }

    public function warnIfNeeded(string $url): void
    {
        $host = $this->getHost($url);
        if ($this->isEncrypted($url) || $this->isLoopbackHost($host) || isset($this->warnedHosts[$host])) {
            return;
        }

        $this->warnedHosts[$host] = true;
        $this->errorLogger->addLog(self::LOG_TYPE, $this->buildMessage($host));
    }

    private function isEncrypted(string $url): bool
    {
        return strtolower((string)parse_url($url, PHP_URL_SCHEME)) === self::SCHEME_HTTPS;
    }

    private function getHost(string $url): string
    {
        $host = (string)parse_url($url, PHP_URL_HOST);

        return $host !== '' ? $host : $url;
    }

    private function isLoopbackHost(string $host): bool
    {
        $host = strtolower($host);
        if (in_array($host, self::LOOPBACK_HOSTS, true)) {
            return true;
        }

        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && str_starts_with($host, self::LOOPBACK_IPV4_PREFIX);
    }

    private function buildMessage(string $host): string
    {
        return sprintf(
            'Internal API URL uses plain http to non-loopback host "%s"; the admin bearer token'
            . ' is sent unencrypted. Use https, or a loopback address, in'
            . ' Stores > Configuration > Mago Assistant > API > Internal API URL.',
            $host
        );
    }
}
