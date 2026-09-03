<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Test\Unit\Fakes;

use Magento\Framework\AuthorizationInterface;
use MaggyAssistant\Base\Api\Skill\ActionInterface;
use MaggyAssistant\Base\Service\Skills\AbstractSkill;

/**
 * Mixed read/write skill built from FakeAction instances
 */
final class FakeSkill extends AbstractSkill
{
    /**
     * @param ActionInterface[] $actions keyed by action name
     */
    public function __construct(
        private readonly string $name,
        AuthorizationInterface $authorization,
        array $actions
    ) {
        parent::__construct($authorization, $actions);
    }

    public function getName(): string
    {
        return $this->name;
    }

    protected function getBaseDescription(): string
    {
        return 'Fake ' . $this->name . '.';
    }
}
