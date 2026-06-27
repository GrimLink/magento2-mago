<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Skills\Content;

use Magento\Catalog\Api\ProductRepositoryInterface;
use MaggyAssistant\Base\Api\Tool\ToolInterface;

class ContentGenerator implements ToolInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    public function getName(): string
    {
        return 'content_generator';
    }

    public function getDescription(): string
    {
        return 'Generate product content (descriptions, meta tags). Returns generated text for merchant review before saving. Actions: "generate_description", "generate_meta", "generate_short_description".';
    }

    public function getParameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'enum' => ['generate_description', 'generate_meta', 'generate_short_description'],
                ],
                'sku' => [
                    'type' => 'string',
                    'description' => 'Product SKU to generate content for',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'The generated content to save (used when confirming a save)',
                ],
                'instructions' => [
                    'type' => 'string',
                    'description' => 'Additional instructions for content generation (tone, keywords, length)',
                ],
            ],
            'required' => ['action', 'sku'],
        ];
    }

    public function execute(array $params): array
    {
        $action = $params['action'] ?? '';
        $sku = $params['sku'] ?? '';

        if (!$sku) {
            return ['error' => 'SKU is required'];
        }

        try {
            $product = $this->productRepository->get($sku);
        } catch (\Throwable $e) {
            return ['error' => 'Product not found: ' . $sku];
        }

        // If content is provided, this is a save action
        if (!empty($params['content'])) {
            return $this->saveContent($product, $action, $params['content']);
        }

        // Otherwise return product context for the AI to generate content
        $context = [
            'sku' => $product->getSku(),
            'name' => $product->getName(),
            'price' => (float)$product->getPrice(),
            'type' => $product->getTypeId(),
            'current_description' => $product->getDescription() ?? '',
            'current_short_description' => $product->getShortDescription() ?? '',
            'current_meta_title' => $product->getMetaTitle() ?? '',
            'current_meta_description' => $product->getMetaDescription() ?? '',
            'url_key' => $product->getUrlKey() ?? '',
        ];

        // Get category names
        $categoryNames = [];
        $categories = $product->getCategoryCollection()->addAttributeToSelect('name');
        foreach ($categories as $category) {
            $categoryNames[] = $category->getName();
        }
        $context['categories'] = $categoryNames;

        return [
            'action' => $action,
            'product_context' => $context,
            'instructions' => $params['instructions'] ?? '',
            'message' => 'Use this product context to generate the requested content. Return the content and ask the merchant to confirm before saving.',
        ];
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function isReadOnlyAction(array $input): bool
    {
        return $this->isReadOnly();
    }

    public function getRequiredAcl(): string
    {
        return 'MaggyAssistant_Base::assistant_write';
    }

    public function getInstructions(): string
    {
        return '';
    }

    public function getMagentoAcl(): string
    {
        return 'Magento_Catalog::products';
    }

    private function saveContent($product, string $action, string $content): array
    {
        $field = match ($action) {
            'generate_description' => 'description',
            'generate_short_description' => 'short_description',
            'generate_meta' => 'meta_description',
            default => null,
        };

        if (!$field) {
            return ['error' => 'Unknown action: ' . $action];
        }

        $product->setData($field, $content);
        $this->productRepository->save($product);

        return [
            'success' => true,
            'sku' => $product->getSku(),
            'field' => $field,
            'message' => sprintf('Updated %s for product %s', $field, $product->getSku()),
        ];
    }
}
