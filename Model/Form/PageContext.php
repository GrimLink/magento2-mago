<?php
/**
 * Copyright © 2026 Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Model\Form;

/**
 * The normalized, trusted description of the admin page the administrator was looking at when a
 * chat message was sent. Everything here has already passed through PageContextNormalizer, so
 * unlike the raw client payload it carries no un-typed, unbounded or non-scalar data.
 */
class PageContext
{
    /**
     * @param array<int,array<string,mixed>> $fields Normalized field snapshots (path/label/type/value/...)
     */
    public function __construct(
        public readonly string $route,
        public readonly string $namespace,
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly bool $isNewEntity,
        public readonly ?string $storeId,
        public readonly array $fields,
        public readonly int $fieldCount
    ) {
    }

    /**
     * A short, value-free line for the system prompt: page label, route, entity type, entity id,
     * store scope and field count. Field values and labels are deliberately left out; they cost
     * tokens and belong in a dedicated read tool rather than every turn of every conversation.
     */
    public function toPromptLine(): string
    {
        return sprintf(
            'The administrator is currently on the %s page (%s), viewing %s%s, with %d field(s) visible.',
            $this->label(),
            $this->route,
            $this->entityDescriptor(),
            $this->storeDescriptor(),
            $this->fieldCount
        );
    }

    public function toLocation(): PageLocation
    {
        return new PageLocation(
            route: $this->route,
            namespace: $this->namespace,
            entityType: $this->entityType,
            entityId: $this->entityId,
            isNewEntity: $this->isNewEntity,
            storeId: $this->storeId
        );
    }

    private function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->entityType));
    }

    private function entityDescriptor(): string
    {
        return $this->toLocation()->entityDescriptor();
    }

    private function storeDescriptor(): string
    {
        return $this->toLocation()->storeDescriptor();
    }
}
