<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Api;

use Magento\Framework\Serialize\Serializer\Json;
use MagoAssistant\Mago\Service\Api\InternalRequestOptions;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeConfigRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InternalRequestOptionsTest extends TestCase
{
    private const STORE_BASE_URL = 'https://shop.example.com/';
    private const STORE_URL = 'https://shop.example.com/rest/V1/orders';
    private const CONTAINER_URL = 'https://nginx:443/rest/V1/orders';
    private const TOKEN = 'secret-token';

    #[Test]
    public function itVerifiesTheCertificateAndHostByDefault(): void
    {
        $options = $this->optionsWith(new FakeConfigRepository());

        self::assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        self::assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
    }

    #[Test]
    public function itDisablesCertificateAndHostVerificationWhenConfiguredOff(): void
    {
        $options = $this->optionsWith((new FakeConfigRepository())->withInternalSslVerifyEnabled(false));

        self::assertFalse($options[CURLOPT_SSL_VERIFYPEER]);
        self::assertSame(0, $options[CURLOPT_SSL_VERIFYHOST]);
    }

    #[Test]
    public function itSendsTheStoreHostHeaderWhenTheInternalHostDiffers(): void
    {
        $options = $this->optionsWith(new FakeConfigRepository(), self::CONTAINER_URL);

        self::assertContains('Host: shop.example.com', $options[CURLOPT_HTTPHEADER]);
    }

    #[Test]
    public function itSendsTheStoreHostHeaderForLoopbackUrls(): void
    {
        $options = $this->optionsWith(new FakeConfigRepository(), 'https://127.0.0.1/rest/V1/orders');

        self::assertContains('Host: shop.example.com', $options[CURLOPT_HTTPHEADER]);
    }

    #[Test]
    public function itOmitsTheHostHeaderWhenTheInternalHostMatchesTheStore(): void
    {
        $options = $this->optionsWith(new FakeConfigRepository(), self::STORE_URL);

        self::assertSame([], $this->hostHeaders($options));
    }

    #[Test]
    public function itFallsBackToLocalhostWhenTheStoreUrlHasNoHost(): void
    {
        $options = $this->optionsWith(new FakeConfigRepository(), self::CONTAINER_URL, '');

        self::assertContains('Host: localhost', $options[CURLOPT_HTTPHEADER]);
    }

    #[Test]
    public function itSendsTheBearerTokenAndJsonHeaders(): void
    {
        $options = $this->optionsWith(new FakeConfigRepository());

        self::assertContains('Authorization: Bearer ' . self::TOKEN, $options[CURLOPT_HTTPHEADER]);
        self::assertContains('Content-Type: application/json', $options[CURLOPT_HTTPHEADER]);
        self::assertContains('Accept: application/json', $options[CURLOPT_HTTPHEADER]);
    }

    #[Test]
    public function itNeverFollowsRedirects(): void
    {
        $options = $this->optionsWith(new FakeConfigRepository());

        self::assertFalse($options[CURLOPT_FOLLOWLOCATION]);
    }

    #[Test]
    public function itSerializesThePostBody(): void
    {
        $options = $this->optionsWith(
            new FakeConfigRepository(),
            self::STORE_URL,
            self::STORE_BASE_URL,
            InternalRequestOptions::METHOD_POST,
            ['entity' => ['id' => 1]]
        );

        self::assertTrue($options[CURLOPT_POST]);
        self::assertSame('{"entity":{"id":1}}', $options[CURLOPT_POSTFIELDS]);
    }

    #[Test]
    public function itSendsAnEmptyJsonObjectForAPostWithoutBody(): void
    {
        $options = $this->optionsWith(
            new FakeConfigRepository(),
            self::STORE_URL,
            self::STORE_BASE_URL,
            InternalRequestOptions::METHOD_POST
        );

        self::assertSame('[]', $options[CURLOPT_POSTFIELDS]);
    }

    #[Test]
    public function itUsesACustomRequestForPut(): void
    {
        $options = $this->optionsWith(
            new FakeConfigRepository(),
            self::STORE_URL,
            self::STORE_BASE_URL,
            InternalRequestOptions::METHOD_PUT,
            ['status' => 'closed']
        );

        self::assertSame('PUT', $options[CURLOPT_CUSTOMREQUEST]);
        self::assertSame('{"status":"closed"}', $options[CURLOPT_POSTFIELDS]);
    }

    #[Test]
    public function itUsesACustomRequestWithoutBodyForDelete(): void
    {
        $options = $this->optionsWith(
            new FakeConfigRepository(),
            self::STORE_URL,
            self::STORE_BASE_URL,
            InternalRequestOptions::METHOD_DELETE
        );

        self::assertSame('DELETE', $options[CURLOPT_CUSTOMREQUEST]);
        self::assertArrayNotHasKey(CURLOPT_POSTFIELDS, $options);
    }

    #[Test]
    public function itSendsNoBodyForGet(): void
    {
        $options = $this->optionsWith(new FakeConfigRepository());

        self::assertArrayNotHasKey(CURLOPT_POST, $options);
        self::assertArrayNotHasKey(CURLOPT_POSTFIELDS, $options);
        self::assertArrayNotHasKey(CURLOPT_CUSTOMREQUEST, $options);
    }

    /**
     * @return array<int, mixed>
     */
    private function optionsWith(
        FakeConfigRepository $configRepository,
        string $url = self::STORE_URL,
        string $storeBaseUrl = self::STORE_BASE_URL,
        string $method = InternalRequestOptions::METHOD_GET,
        ?array $body = null
    ): array {
        return (new InternalRequestOptions($configRepository, new Json()))
            ->build($method, $url, $body, self::TOKEN, $storeBaseUrl);
    }

    /**
     * @param array<int, mixed> $options
     * @return string[]
     */
    private function hostHeaders(array $options): array
    {
        return array_values(array_filter(
            $options[CURLOPT_HTTPHEADER],
            static fn (string $header): bool => str_starts_with($header, 'Host:')
        ));
    }
}
