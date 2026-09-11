<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Api\Tool;

/**
 * Tool that can tell, per invocation, whether the call is irreversible and what it will do.
 *
 * ChatService asks this before it sends the confirmation to the panel, so an irreversible call is
 * shown with its impact list and an acknowledgement checkbox rather than a plain Allow button.
 * Tools that do not implement it are treated as reversible writes.
 * @api
 */
interface IrreversibleToolInterface extends ToolInterface
{
    /**
     * Whether the given invocation cannot be undone once it ran
     *
     * @param array $input The tool call input parameters
     * @return bool
     */
    public function isIrreversibleAction(array $input): bool;

    /**
     * Consequences of the given invocation, one per line, for the confirmation card
     *
     * @param array $input The tool call input parameters
     * @param int $adminUserId Current admin user ID
     * @return string[]
     */
    public function getImpacts(array $input, int $adminUserId): array;
}
