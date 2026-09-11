/*
 * Copyright © Mago Assistant
 */

import {expect, test} from '@playwright/test';
import ChatPanel from 'Pages/backend/ChatPanel';
import ChatMock from 'Actions/backend/ChatMock';
import {declineProductCreation, lookupProduct} from 'Fixtures/scenarios';

const chatPanel = new ChatPanel();
const chatMock = new ChatMock();

test('Declines a product creation request instead of claiming success', async ({page}) => {
  await chatMock.install(page, declineProductCreation);

  await chatPanel.openOnDashboard(page);
  await chatPanel.ask(page, 'Create me a new product. Anti-musquito candle, price = €15');

  await expect(chatPanel.lastAssistantMessage(page)).toContainText('cannot create products');
  await expect(chatPanel.toolTags(page)).toHaveCount(0);
  await expect(chatPanel.confirmActions(page)).toHaveCount(0);
});

test('Runs a read-only catalog lookup without asking for confirmation', async ({page}) => {
  await chatMock.install(page, lookupProduct);

  await chatPanel.openOnDashboard(page);
  await chatPanel.ask(page, 'Which products contain candle?');

  await expect(chatPanel.toolTags(page)).toHaveText([/product_data/]);
  await expect(chatPanel.lastAssistantMessage(page)).toContainText('I found 2 products');
  await expect(chatPanel.confirmActions(page)).toHaveCount(0);
});
