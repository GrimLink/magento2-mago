<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Test\Unit\Service\Store;

use MaggyAssistant\Base\Service\Store\StoreScopeContext;
use MaggyAssistant\Base\Test\Unit\Fakes\BuildsStoreLayouts;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StoreScopeContextTest extends TestCase
{
    use BuildsStoreLayouts;

    #[Test]
    public function itListsWebsitesGroupsAndStoreViewsWithDefaults(): void
    {
        $websites = (new StoreScopeContext($this->multiStoreManager()))->getWebsites();

        self::assertSame([1], array_keys($websites));
        self::assertTrue($websites[1]['is_default']);
        self::assertTrue($websites[1]['groups'][1]['is_default']);
        self::assertSame([1, 2], array_keys($websites[1]['groups'][1]['stores']));
        self::assertTrue($websites[1]['groups'][1]['stores'][1]['is_default']);
        self::assertFalse($websites[1]['groups'][1]['stores'][2]['is_default']);
        self::assertSame('luma', $websites[1]['groups'][1]['stores'][2]['code']);
    }

    #[Test]
    public function itTellsSingleAndMultiStoreApart(): void
    {
        self::assertTrue((new StoreScopeContext($this->singleStoreManager()))->hasSingleStoreView());
        self::assertFalse((new StoreScopeContext($this->multiStoreManager()))->hasSingleStoreView());
    }

    #[Test]
    public function itAcceptsValidScopes(): void
    {
        $context = new StoreScopeContext($this->multiStoreManager());

        self::assertNull($context->validateScope('default', 0));
        self::assertNull($context->validateScope('websites', 1));
        self::assertNull($context->validateScope('stores', 2));
    }

    #[Test]
    public function itRejectsUnknownScopesAndIds(): void
    {
        $context = new StoreScopeContext($this->multiStoreManager());

        self::assertStringContainsString('Unknown scope "global"', (string)$context->validateScope('global', 0));
        self::assertStringContainsString('always uses scope_id 0', (string)$context->validateScope('default', 1));
        self::assertSame(
            'Unknown website id 9. Available websites: "Main Website" (id 1).',
            $context->validateScope('websites', 9)
        );
        self::assertSame(
            'Unknown store view id 7. Available store views: "Hyva" (id 1, code "default"), "Luma" (id 2, code "luma").',
            $context->validateScope('stores', 7)
        );
    }

    #[Test]
    public function itDescribesScopesForTheUser(): void
    {
        $context = new StoreScopeContext($this->multiStoreManager());

        self::assertSame('store view "Luma" (id 2, code "luma")', $context->describeScope('stores', 2));
        self::assertSame('website "Main Website" (id 1, code "base")', $context->describeScope('websites', 1));
        self::assertStringStartsWith('default scope', $context->describeScope('default', 0));
        self::assertSame('all store views', $context->describeStoreTarget(0));
        self::assertSame('store view "Luma" (id 2, code "luma")', $context->describeStoreTarget(2));
    }

    #[Test]
    public function itMapsStoreViewsToRestStoreCodes(): void
    {
        $context = new StoreScopeContext($this->multiStoreManager());

        self::assertSame('all', $context->getRestStoreCode(0));
        self::assertSame('luma', $context->getRestStoreCode(2));
        self::assertNull($context->getRestStoreCode(7));
        self::assertStringContainsString('Use 0 for all store views', $context->getUnknownStoreViewError(7));
    }

    #[Test]
    public function itWritesTheStoreLayoutAndScopeRulesIntoThePrompt(): void
    {
        $prompt = (new StoreScopeContext($this->multiStoreManager()))->toPromptSection();

        self::assertStringStartsWith('[Store scope] This installation has 1 website and 2 store views.', $prompt);
        self::assertStringContainsString('- Website "Main Website" (id 1, code "base", default)', $prompt);
        self::assertStringContainsString('  - Store group "Main Website Store" (id 1)', $prompt);
        self::assertStringContainsString('    - Store view "Hyva" (id 1, code "default", default)', $prompt);
        self::assertStringContainsString('    - Store view "Luma" (id 2, code "luma")', $prompt);
        self::assertStringContainsString('ask which scope to use before calling the tool', $prompt);
        self::assertStringContainsString('Always state the scope you used', $prompt);
    }

    #[Test]
    public function itTellsTheAssistantNotToAskAboutScopeOnASingleStoreView(): void
    {
        $prompt = (new StoreScopeContext($this->singleStoreManager()))->toPromptSection();

        self::assertStringStartsWith('[Store scope] This installation has a single store view: "Hyva"', $prompt);
        self::assertStringContainsString('never ask the user which store view to use', $prompt);
        self::assertStringNotContainsString('Scope rules', $prompt);
    }

    #[Test]
    public function itMarksDisabledStoreViews(): void
    {
        $hyva = $this->storeView(1, 'default', 'Hyva', 1, 1);
        $closed = $this->storeView(2, 'closed', 'Closed', 1, 1, false);
        $manager = $this->storeManagerWith(
            [$this->website(1, 'base', 'Main Website', 1)],
            [$this->group(1, 'Main Website Store', 1, 1)],
            [$hyva, $closed],
            $hyva
        );

        $prompt = (new StoreScopeContext($manager))->toPromptSection();

        self::assertStringContainsString('Store view "Closed" (id 2, code "closed", disabled)', $prompt);
    }
}
