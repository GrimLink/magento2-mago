/*
 * Copyright © Maggy Assistant
 */

import {expect, test} from '@playwright/test';
import ChatPanel from 'Pages/backend/ChatPanel';
import ChatMock from 'Actions/backend/ChatMock';
import {lookupProduct, streamFailure} from 'Fixtures/scenarios';

const chatPanel = new ChatPanel();
const chatMock = new ChatMock();

test('Greets the admin user when the panel is opened', async ({page}) => {
  await chatMock.install(page, lookupProduct);

  await chatPanel.openOnDashboard(page);

  await expect(chatPanel.assistantMessages(page)).toHaveCount(1);
  await expect(chatPanel.lastAssistantMessage(page)).toContainText('How can I help you with your store today?');
});

test('Keeps the conversation when the panel is closed and reopened', async ({page}) => {
  await chatMock.install(page, lookupProduct);

  await chatPanel.openOnDashboard(page);
  await chatPanel.ask(page, 'Which products contain candle?');
  await expect(chatPanel.assistantMessages(page)).toHaveCount(2);

  await chatPanel.close(page);
  await chatPanel.open(page);

  await expect(chatPanel.panel(page)).toHaveClass(/is-open/);
  await expect(chatPanel.assistantMessages(page)).toHaveCount(2);
});

test('Filters skills in the slash menu and fills the input on selection', async ({page}) => {
  await chatMock.install(page, lookupProduct);

  await chatPanel.openOnDashboard(page);
  await chatPanel.input(page).pressSequentially('/');

  await expect(chatPanel.slashMenu(page)).toHaveClass(/is-visible/);
  const skillCount = await chatPanel.slashItems(page).count();

  await chatPanel.input(page).pressSequentially('coupon');

  await expect(chatPanel.slashItems(page).first()).toContainText('/coupon_manager');
  expect(await chatPanel.slashItems(page).count()).toBeLessThan(skillCount);

  await chatPanel.input(page).press('Enter');

  await expect(chatPanel.input(page)).toHaveValue('Use the coupon_manager skill to ');
  await expect(chatPanel.slashMenu(page)).not.toHaveClass(/is-visible/);
  await expect(chatPanel.userMessages(page)).toHaveCount(0);
});

test('Surfaces a streamed error instead of failing silently', async ({page}) => {
  await chatMock.install(page, streamFailure);

  await chatPanel.openOnDashboard(page);
  await chatPanel.ask(page, 'Give me the revenue for last month');

  await expect(chatPanel.lastAssistantMessage(page)).toContainText('rate limit exceeded');
  await expect(chatPanel.sendButton(page)).toBeEnabled();
});
