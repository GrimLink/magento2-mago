/*
 * Copyright © Maggy Assistant
 */

import {expect, test} from '@playwright/test';
import ChatPanel from 'Pages/backend/ChatPanel';

const chatPanel = new ChatPanel();

/**
 * No browser-level mocking here either: the request travels through the real controller and
 * ChatService, and only the provider call is answered by WireMock. The mappings in
 * wiremock/mappings/store-scope.json match on the request body, so the assistant's reply
 * tells us what the provider actually received (issue #38).
 */
test.describe('Store scope', () => {
  test('Sends the store layout to the provider with the request', async ({page}) => {
    await chatPanel.openOnDashboard(page);
    await chatPanel.ask(page, 'Please run the E2E store scope check.');

    /* WireMock answers "Store scope missing" when the request body lacks the [Store scope] section. */
    await expect(chatPanel.lastAssistantMessage(page)).toContainText('Store scope received');
    await expect(chatPanel.lastAssistantMessage(page)).not.toContainText('Store scope missing');
  });

  test('Refuses a config read on a store view that does not exist', async ({page}) => {
    await chatPanel.openOnDashboard(page);
    await chatPanel.ask(page, 'Please run the E2E unknown store view check.');

    /*
     * The mocked provider asks config_reader for store view 9999. The read-only tool runs without
     * confirmation and its result goes straight back to the provider, which only answers
     * "does not exist" when that result carries the "Unknown store view id 9999" error.
     */
    await expect(chatPanel.toolTags(page)).toHaveText([/config_reader/]);
    await expect(chatPanel.lastAssistantMessage(page)).toContainText('Store view 9999 does not exist');
    await expect(chatPanel.lastAssistantMessage(page)).not.toContainText('Configuration read succeeded');
  });
});
