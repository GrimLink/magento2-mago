<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Privacy;

use MagoAssistant\Mago\Service\Privacy\ConversationVault;
use MagoAssistant\Mago\Service\Privacy\PiiClassificationRegistry;
use MagoAssistant\Mago\Service\Privacy\PrivacyFilter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * V1 (issue #97): the legal preference is not-sending over masking. Direct identifiers are stripped
 * (never sent); only bare linkable ids are tokenised so the assistant can refer to a row across
 * turns. This proves that on the real output shapes of the PII-carrying tools.
 */
class PrivacyFilterTest extends TestCase
{
    private const LOOKUP_CUSTOMER_RESULT = [
        'results' => [
            [
                'entity_id' => 42,
                'name' => 'Jan Jansen',
                'email' => 'jan@example.com',
                'country' => 'NL',
                'city' => 'Amsterdam',
                'telephone' => '0612345678',
                'registered' => '2026-01-05 10:00:00',
                'admin_url' => 'https://shop.test/admin/customer/index/edit/id/42/key/abc123secret/',
            ],
        ],
    ];

    private const LOOKUP_ORDER_RESULT = [
        'order' => [
            'entity_id' => 7,
            'order_number' => '000000549',
            'total' => 149.95,
            'status' => 'processing',
            'customer' => 'Jan Jansen',
            'email' => 'jan@example.com',
            'items' => [
                ['sku' => 'ABC-1', 'name' => 'Helmet', 'qty' => 1, 'price' => 149.95, 'row_total' => 149.95],
            ],
            'date' => '2026-01-06 09:00:00',
            'admin_url' => 'https://shop.test/admin/sales/order/view/order_id/7/key/deadbeef/',
        ],
    ];

    private function filter(?ConversationVault $vault = null, bool $stripUnclassified = false): PrivacyFilter
    {
        return new PrivacyFilter(new PiiClassificationRegistry(), $vault ?? new ConversationVault(), $stripUnclassified);
    }

    #[Test]
    public function itStripsDirectIdentifiersOfACustomerLookup(): void
    {
        $record = $this->filter()->filter('lookup_customer', self::LOOKUP_CUSTOMER_RESULT)['results'][0];

        self::assertArrayNotHasKey('name', $record);
        self::assertArrayNotHasKey('email', $record);
        self::assertArrayNotHasKey('telephone', $record);
        self::assertArrayNotHasKey('admin_url', $record);
    }

    #[Test]
    public function itLeavesNoRawIdentifierInThePayloadTheLlmSees(): void
    {
        $json = (string)json_encode($this->filter()->filter('lookup_customer', self::LOOKUP_CUSTOMER_RESULT));

        self::assertStringNotContainsString('Jan Jansen', $json);
        self::assertStringNotContainsString('jan@example.com', $json);
        self::assertStringNotContainsString('0612345678', $json);
        self::assertStringNotContainsString('abc123secret', $json);
    }

    #[Test]
    public function itTokenisesOnlyTheBareCustomerIdSoTheAssistantCanStillReferToTheRow(): void
    {
        $record = $this->filter()->filter('lookup_customer', self::LOOKUP_CUSTOMER_RESULT)['results'][0];

        self::assertSame('[customer_1]', $record['entity_id']);
    }

    #[Test]
    public function itKeepsCoarsePublicFieldsSoQueriesLikeCityStillWork(): void
    {
        $record = $this->filter()->filter('lookup_customer', self::LOOKUP_CUSTOMER_RESULT)['results'][0];

        self::assertSame('Amsterdam', $record['city']);
        self::assertSame('NL', $record['country']);
        self::assertSame('2026-01-05 10:00:00', $record['registered']);
    }

    #[Test]
    public function itStripsOrderCustomerNameAndEmailButKeepsProductLinesAndTokenisesTheOrderId(): void
    {
        $order = $this->filter()->filter('lookup_order', self::LOOKUP_ORDER_RESULT)['order'];

        self::assertArrayNotHasKey('customer', $order);
        self::assertArrayNotHasKey('email', $order);
        self::assertSame('[order_1]', $order['entity_id']);
        self::assertSame('[order_2]', $order['order_number']);
        self::assertSame('processing', $order['status']);
        self::assertSame('Helmet', $order['items'][0]['name']); // product data is not customer PII
    }

    #[Test]
    public function theSameIdAlwaysGetsTheSameTokenWithinTheConversation(): void
    {
        $vault = new ConversationVault();
        $filter = $this->filter($vault);

        $first = $filter->filter('lookup_customer', self::LOOKUP_CUSTOMER_RESULT)['results'][0]['entity_id'];
        $second = $filter->filter('lookup_customer', self::LOOKUP_CUSTOMER_RESULT)['results'][0]['entity_id'];

        self::assertSame($first, $second);
    }

    #[Test]
    public function itStripsReviewFreeTextAndNicknameNeverMasksThem(): void
    {
        $result = $this->filter()->filter('list_pending', [
            'pending_reviews' => [
                ['review_id' => 5, 'title' => 'Great', 'nickname' => 'Jan', 'detail' => 'Call me on 0612345678', 'product_id' => 3, 'created_at' => '2026-01-01', 'admin_url' => 'x'],
            ],
            'total' => 1,
        ]);
        $review = $result['pending_reviews'][0];

        self::assertArrayNotHasKey('nickname', $review);
        self::assertArrayNotHasKey('title', $review);
        self::assertArrayNotHasKey('detail', $review);
        self::assertSame('[review_1]', $review['review_id']);
        self::assertSame(3, $review['product_id']);
        self::assertStringNotContainsString('0612345678', (string)json_encode($result));
    }

    #[Test]
    public function itPassesAnUnclassifiedPiiFreeToolThroughUnchangedInV1(): void
    {
        $aggregate = ['revenue' => 12345.67, 'order_count' => 88, 'top_products' => [['sku' => 'ABC-1', 'units' => 40]]];

        self::assertSame($aggregate, $this->filter()->filter('revenue', $aggregate));
    }

    #[Test]
    public function itKeepsTheErrorEnvelopeSoAFailedLookupCanStillBeExplained(): void
    {
        $result = $this->filter()->filter('lookup_order', ['error' => 'Order not found: 000000549']);

        self::assertSame(['error' => 'Order not found: 000000549'], $result);
    }

    #[Test]
    public function itStripsAdminUrlEvenFromAnUnclassifiedPassThroughTool(): void
    {
        $result = $this->filter()->filter('admin_navigator_deeplink', [
            'label' => 'Customer grid',
            'admin_url' => 'https://shop.test/admin/customer/index/key/abc123secret/',
        ]);

        self::assertSame(['label' => 'Customer grid'], $result);
        self::assertStringNotContainsString('abc123secret', (string)json_encode($result));
    }

    #[Test]
    public function itTokenisesTheCustomerIdAndKeepsThePeriodOnCustomerOrders(): void
    {
        $result = $this->filter()->filter('customer_orders', [
            'customer_id' => 42,
            'period' => '30days',
            'total_orders' => 2,
            'orders' => [['entity_id' => 7, 'order_number' => '000000549', 'total' => 10.0, 'status' => 'x', 'items' => 1, 'date' => 'd']],
        ]);

        self::assertSame('[customer_1]', $result['customer_id']);
        self::assertSame('30days', $result['period']);
        self::assertSame(2, $result['total_orders']);
        self::assertSame('[order_1]', $result['orders'][0]['entity_id']);
    }

    #[Test]
    public function itFailsClosedStrippingUnclassifiedToolsWhenStrictModeIsOn(): void
    {
        $filtered = $this->filter(null, true)->filter('some_third_party_tool', [
            'customer_email' => 'leak@example.com',
            'nested' => ['phone' => '0612345678'],
        ]);

        self::assertArrayNotHasKey('customer_email', $filtered);
        self::assertSame(['nested' => []], $filtered);
    }
}
