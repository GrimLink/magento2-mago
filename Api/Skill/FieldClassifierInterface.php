<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Api\Skill;

/**
 * Optional: an action (or tool) that declares how its own output fields cross to the LLM under
 * privacy mode (issue #97), so a third-party tool can classify its result without editing the core
 * PiiClassificationRegistry. Implement this and register the instance in
 * PiiClassificationRegistry's `classifiers` argument via di.xml.
 *
 * This is deliberately opt-in rather than a required method on ActionInterface: a tool that does not
 * classify is still safe because the filter fails closed on the fields it does not recognise. The
 * built-in PII tools keep their classification in the registry defaults.
 *
 * @api
 */
interface FieldClassifierInterface
{
    /**
     * The action name this classification applies to (matches ActionInterface::getName()).
     */
    public function getClassifiedActionName(): string;

    /**
     * Field name => [PiiClass, tokenType?]. See PiiClass for the classes and the registry defaults
     * for the shape.
     *
     * @return array<string,array{0:string,1?:string}>
     */
    public function getFieldClassification(): array;
}
