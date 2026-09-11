<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Api\Skill;

/**
 * A write action that cannot be undone once it ran (a delete, a cancellation, a refund).
 *
 * The chat panel asks for these with the "cannot be undone" card: an impact list and a ticked
 * acknowledgement instead of a plain Allow button. Implement this on top of ActionInterface for
 * every action whose effect has no reverse.
 * @api
 */
interface IrreversibleActionInterface extends ActionInterface
{
    /**
     * What the action will do to the store, in plain words, one consequence per line.
     * Read the current state where it helps ("7 of these products are in 41 open orders");
     * never change anything here.
     *
     * @param array $params Tool call parameters, as the action will receive them
     * @param int $adminUserId Current admin user ID
     * @return string[]
     */
    public function getImpacts(array $params, int $adminUserId): array;
}
