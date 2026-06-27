<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Skills\Analytics\CustomerData;

use MaggyAssistant\Base\Api\Skill\ActionInterface;
use MaggyAssistant\Base\Service\Api\InternalApiClient;
use MaggyAssistant\Base\Service\Url\SecureAdminUrl;

class LookupCustomerAction implements ActionInterface
{
    public function __construct(
        private readonly InternalApiClient $apiClient,
        private readonly SecureAdminUrl $secureAdminUrl
    ) {
    }

    public function getName(): string
    {
        return 'lookup_customer';
    }

    public function getDescription(): string
    {
        return 'Search for a customer by name or email';
    }

    public function getParameterSchema(): array
    {
        return [
            'search' => [
                'type' => 'string',
                'description' => 'Customer name or email to search for',
            ],
            'limit' => [
                'type' => 'integer',
                'description' => 'Number of results (default: 10)',
            ],
        ];
    }

    public function getAclResource(): ?string
    {
        return null;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getInstructions(): string
    {
        return '';
    }

    public function execute(array $params, int $adminUserId): array
    {
        $search = $params['search'] ?? '';
        if (empty(trim($search))) {
            return ['error' => 'search parameter is required for lookup_customer'];
        }

        if (!$adminUserId) {
            return ['error' => 'Admin user context is required'];
        }

        $limit = max(1, min((int)($params['limit'] ?? 10), 10));

        if (str_contains($search, '@')) {
            $searchParams = $this->apiClient->buildSearchCriteria(
                [['field' => 'email', 'value' => '%' . trim($search) . '%', 'condition_type' => 'like']],
                $limit,
                1,
                [['field' => 'created_at', 'direction' => 'DESC']]
            );
        } else {
            $searchParams = $this->apiClient->buildSearchCriteria(
                [['field' => 'firstname', 'value' => '%' . trim($search) . '%', 'condition_type' => 'like']],
                $limit,
                1,
                [['field' => 'created_at', 'direction' => 'DESC']]
            );
        }

        $result = $this->apiClient->get('customers/search', $searchParams, $adminUserId);

        if (isset($result['error'])) {
            return $result;
        }

        $items = $result['items'] ?? [];

        if (!str_contains($search, '@') && empty($items)) {
            $searchParams = $this->apiClient->buildSearchCriteria(
                [['field' => 'lastname', 'value' => '%' . trim($search) . '%', 'condition_type' => 'like']],
                $limit,
                1,
                [['field' => 'created_at', 'direction' => 'DESC']]
            );
            $result = $this->apiClient->get('customers/search', $searchParams, $adminUserId);
            $items = $result['items'] ?? [];
        }

        if (empty($items)) {
            return ['results' => [], 'message' => 'No customers found matching "' . $search . '"'];
        }

        $customers = [];
        foreach ($items as $customer) {
            $customerId = (int)($customer['id'] ?? 0);
            $address = $customer['addresses'][0] ?? [];

            $customers[] = [
                'entity_id' => $customerId,
                'name' => trim(($customer['firstname'] ?? '') . ' ' . ($customer['lastname'] ?? '')),
                'email' => $customer['email'] ?? '',
                'country' => $address['country_id'] ?? null,
                'city' => $address['city'] ?? null,
                'telephone' => $address['telephone'] ?? null,
                'registered' => $customer['created_at'] ?? '',
                'admin_url' => $this->secureAdminUrl->getUrl('customer/index/edit', ['id' => $customerId]),
            ];
        }

        return ['results' => $customers];
    }
}
