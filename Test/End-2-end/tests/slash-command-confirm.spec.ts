/*
 * Copyright © Mago Assistant
 */

import {expect, test} from '@playwright/test';
import ChatPanel from 'Pages/backend/ChatPanel';
import {PROVIDER_ROUND_TRIP_TIMEOUT} from 'Config/timeouts';

const chatPanel = new ChatPanel();

const CACHE_TYPE = 'config_webservice';

/**
 * A typed slash write goes through the same confirmation card as a write the model proposes
 * (issue #150): the real Stream controller stages it, nothing runs until Allow, and the Confirm
 * controller then runs cache_manager and asks the provider (WireMock) for the follow-up turn.
 * wiremock/mappings/slash-confirm.json only answers "clean again" when that turn carries the
 * tool's own "has been flushed" result, so the reply proves the clean actually ran.
 */
test.describe('Slash command confirmation', () => {
  test('Cleans a cache type only after the admin allows it', async ({page}) => {
    await chatPanel.openOnDashboard(page);
    await chatPanel.ask(page, '/cache clean ' + CACHE_TYPE);

    await expect(chatPanel.confirmButton(page)).toBeVisible({timeout: PROVIDER_ROUND_TRIP_TIMEOUT});
    await expect(chatPanel.lastAssistantMessage(page)).toContainText('cache_manager');
    await expect(chatPanel.lastAssistantMessage(page)).toContainText(CACHE_TYPE);
    await expect(chatPanel.lastAssistantMessage(page)).not.toContainText('Cleaned');

    await chatPanel.confirmButton(page).click();

    await expect(chatPanel.lastAssistantMessage(page)).toContainText(
      'The ' + CACHE_TYPE + ' cache is clean again.',
      {timeout: PROVIDER_ROUND_TRIP_TIMEOUT}
    );
    await expect(chatPanel.lastAssistantMessage(page)).not.toContainText('Slash confirm fallback');
  });

  test('Asks what changed instead of flushing every cache', async ({page}) => {
    await chatPanel.openOnDashboard(page);
    await chatPanel.ask(page, '/cache flush');

    await expect(chatPanel.lastAssistantMessage(page)).toContainText(
      'Flushing every cache is rarely what a change needs',
      {timeout: PROVIDER_ROUND_TRIP_TIMEOUT}
    );
    await expect(chatPanel.lastAssistantMessage(page)).toContainText('/cache clean full_page');
    await expect(chatPanel.confirmActions(page)).toHaveCount(0);
  });

  test('Asks which index is meant instead of reindexing everything', async ({page}) => {
    await chatPanel.openOnDashboard(page);
    await chatPanel.ask(page, '/index reindex');

    await expect(chatPanel.lastAssistantMessage(page)).toContainText(
      'Reindexing everything rebuilds every index',
      {timeout: PROVIDER_ROUND_TRIP_TIMEOUT}
    );
    await expect(chatPanel.lastAssistantMessage(page)).toContainText('catalog_product_price');
    await expect(chatPanel.confirmActions(page)).toHaveCount(0);
  });
});
