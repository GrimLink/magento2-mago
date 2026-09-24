<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Skills\Sales;

use MagoAssistant\Mago\Service\Skills\AbstractSkill;

class OrderManager extends AbstractSkill
{
    public function getName(): string
    {
        return 'order_manager';
    }

    protected function getBaseDescription(): string
    {
        return 'Manage orders: add comments, update status, create shipments with tracking, create invoices, process refunds, and cancel orders.';
    }

    public function getMagentoAcl(array $input = []): string
    {
        return 'Magento_Sales::sales_order';
    }

    protected function getBaseInstructions(): string
    {
        return 'Always resolve order_number (increment_id like "000000549") to entity_id before API calls. '
            . 'Use sales_data lookup_order if you need to find the entity_id. '
            . 'Order operations are irreversible — always confirm details with the user. '
            . 'After creating a shipment or invoice, include the new entity ID and admin URL in the response. '
            . 'notify_customer sends the customer a real e-mail: set it only when the user asks to inform '
            . 'the customer. Never send the same customer e-mail more than once and never send e-mail in bulk: '
            . 'refuse requests like "send 100 confirmation e-mails" and explain that Mago sends each kind of '
            . 'e-mail for an order at most once per interval. There is no action that resends the order '
            . 'confirmation e-mail; say so instead of substituting another e-mail. When an e-mail was refused '
            . 'or failed, report it and do not suggest retrying.';
    }
}
