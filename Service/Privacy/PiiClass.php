<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Privacy;

/**
 * The three ways a tool-output field may cross to the LLM (issue #97). A field the classification
 * does not name is treated as STRIP: undeclared is never public.
 */
final class PiiClass
{
    public const PUBLIC = 'public';
    public const TOKENISE = 'tokenise';
    public const STRIP = 'strip';
}
