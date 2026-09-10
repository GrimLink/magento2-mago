<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Ai;

use MagoAssistant\Mago\Service\Ai\AnswerWidgets;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AnswerWidgetsTest extends TestCase
{
    /**
     * Every type the guide lists must exist in mago-ui.js, otherwise the model is taught a
     * widget the panel cannot draw. Kept in step by hand; this test guards the list.
     */
    private const PANEL_TYPES = [
        'stat', 'stats', 'sparkline', 'meter', 'ring', 'composition', 'rankedBars', 'columns', 'lines',
        'stackedColumns', 'heatmap', 'funnel', 'entityList', 'table', 'record', 'confirmWrite', 'toolTrace',
        'callout', 'suggestions', 'answerFooter', 'empty', 'skeleton', 'skillAsk', 'skillRunning', 'skillLine',
        'skillFailed', 'readLine', 'skillIrreversible', 'skillBulk', 'skillPlan', 'paramPrompt', 'undoCallout',
        'sessionLog', 'skillMenu',
    ];

    #[Test]
    public function itOpensWithTheMarkerAndNamesTheFence(): void
    {
        $section = (new AnswerWidgets())->toPromptSection();

        self::assertStringStartsWith(AnswerWidgets::MARKER . ' ', $section);
        self::assertStringContainsString('language "mago"', $section);
        self::assertStringContainsString('```mago', $section);
    }

    #[Test]
    public function itOnlyTeachesTypesThePanelCanRender(): void
    {
        $guide = new AnswerWidgets();

        self::assertNotEmpty($guide->getTypes());
        foreach ($guide->getTypes() as $type) {
            self::assertContains($type, self::PANEL_TYPES, "Unknown widget type {$type}");
            self::assertStringContainsString('{"type":"' . $type . '"', $guide->toPromptSection());
        }
    }

    #[Test]
    public function itLeavesInteractiveCardsToThePanel(): void
    {
        $types = (new AnswerWidgets())->getTypes();

        $cards = ['skillAsk', 'skillRunning', 'skillBulk', 'skillIrreversible', 'skillPlan', 'confirmWrite'];
        foreach ($cards as $card) {
            self::assertNotContains($card, $types);
        }
    }

    #[Test]
    public function everyExampleShapeIsValidJson(): void
    {
        $section = (new AnswerWidgets())->toPromptSection();

        preg_match_all('/^- (\{.*?\})(?= — )/m', $section, $matches);

        self::assertNotEmpty($matches[1]);
        foreach ($matches[1] as $shape) {
            // <stat> placeholders stand for a nested example, not literal JSON
            $shape = str_replace('<stat>', '{"type":"stat"}', $shape);
            self::assertIsArray(json_decode($shape, true), "Shape is not valid JSON: {$shape}");
        }
    }

    #[Test]
    public function itTellsTheModelToUseToolDataOnly(): void
    {
        $section = (new AnswerWidgets())->toPromptSection();

        self::assertStringContainsString('never invent', $section);
        self::assertStringContainsString('never put HTML', $section);
    }
}
