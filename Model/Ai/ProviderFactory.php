<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Model\Ai;

use MaggyAssistant\Base\Api\Ai\ProviderInterface;
use MaggyAssistant\Base\Api\Config\RepositoryInterface as ConfigRepository;

class ProviderFactory
{
    /** @var array<string, ProviderInterface> */
    private array $providers;

    /**
     * @param ConfigRepository $configRepository
     * @param array<string, ProviderInterface> $providers
     */
    public function __construct(
        private readonly ConfigRepository $configRepository,
        array $providers = []
    ) {
        $this->providers = $providers;
    }

    public function create(?string $providerName = null): ProviderInterface
    {
        $providerName = $providerName ?: $this->configRepository->getProvider();

        if (!isset($this->providers[$providerName])) {
            throw new \InvalidArgumentException(
                sprintf('AI provider "%s" is not configured. Available: %s', $providerName, implode(', ', array_keys($this->providers)))
            );
        }

        return $this->providers[$providerName];
    }
}
