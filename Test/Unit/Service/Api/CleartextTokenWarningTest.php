<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Api;

use Magento\Framework\Serialize\Serializer\Json;
use MagoAssistant\Mago\Logger\ErrorLogger;
use MagoAssistant\Mago\Service\Api\CleartextTokenWarning;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CleartextTokenWarningTest extends TestCase
{
    private FakeLogger $logger;
    private CleartextTokenWarning $warning;

    protected function setUp(): void
    {
        $this->logger = new FakeLogger();
        $this->warning = new CleartextTokenWarning(new ErrorLogger($this->logger, new Json()));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function cleartextUrls(): array
    {
        return [
            'http to container' => ['http://nginx/rest/V1/x'],
            'uppercase scheme' => ['HTTP://nginx/rest/V1/x'],
            'http to private ip' => ['http://192.168.1.10/rest/V1/x'],
            'http to non-loopback 128.x' => ['http://128.0.0.1/rest/V1/x'],
            'no scheme with port' => ['nginx:443/rest/V1/x'],
            'no scheme at all' => ['nginx'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function safeUrls(): array
    {
        return [
            'https to container' => ['https://nginx:443/rest/V1/x'],
            'uppercase https' => ['HTTPS://nginx/rest/V1/x'],
            'localhost' => ['http://localhost/rest/V1/x'],
            'uppercase localhost' => ['http://LOCALHOST/rest/V1/x'],
            'ipv6 loopback' => ['http://[::1]/rest/V1/x'],
            'ipv4 loopback' => ['http://127.0.0.1/rest/V1/x'],
            'ipv4 loopback range' => ['http://127.1.2.3/rest/V1/x'],
        ];
    }

    #[DataProvider('cleartextUrls')]
    #[Test]
    public function itWarnsWhenTheTokenTravelsUnencrypted(string $url): void
    {
        $this->warning->warnIfNeeded($url);

        self::assertCount(1, $this->logger->getMessages());
        self::assertStringContainsString('InternalAPI cleartext token warning', $this->logger->getMessages()[0]);
    }

    #[DataProvider('safeUrls')]
    #[Test]
    public function itStaysSilentForEncryptedOrLoopbackUrls(string $url): void
    {
        $this->warning->warnIfNeeded($url);

        self::assertSame([], $this->logger->getMessages());
    }

    #[Test]
    public function itNamesTheOffendingHost(): void
    {
        $this->warning->warnIfNeeded('http://nginx/rest/V1/x');

        self::assertStringContainsString('non-loopback host "nginx"', $this->logger->getMessages()[0]);
    }

    #[Test]
    public function itWarnsOncePerHost(): void
    {
        $this->warning->warnIfNeeded('http://nginx/rest/V1/a');
        $this->warning->warnIfNeeded('http://nginx/rest/V1/b');
        $this->warning->warnIfNeeded('http://web/rest/V1/a');

        self::assertCount(2, $this->logger->getMessages());
    }
}
