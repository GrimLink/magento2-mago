/*
 * Copyright © Mago Assistant
 */

import {expect, test} from '@playwright/test';
import ChatPanel from 'Pages/backend/ChatPanel';
import PageBuilderStage from 'Pages/backend/PageBuilderStage';

const chatPanel = new ChatPanel();
const pageBuilderStage = new PageBuilderStage();

const ABOUT_US_PAGE_TITLE = 'About us';

test('Closes full-screen Page Builder when Mago was opened first', async ({page}) => {
  await pageBuilderStage.openCmsPage(page, ABOUT_US_PAGE_TITLE);

  await chatPanel.open(page);
  await pageBuilderStage.switchToPageBuilder(page);

  await pageBuilderStage.closeFullScreenButton(page).click();

  await expect(pageBuilderStage.stage(page)).not.toHaveClass(/stage-full-screen/);
});

test('Lines the full-screen stage header up with the stage beside the panel', async ({page}) => {
  await pageBuilderStage.openCmsPage(page, ABOUT_US_PAGE_TITLE);

  await chatPanel.open(page);
  await pageBuilderStage.switchToPageBuilder(page);

  const stageBox = await pageBuilderStage.stage(page).boundingBox();
  const headerBox = await pageBuilderStage.fullScreenHeader(page).boundingBox();

  expect(Math.abs((headerBox.x + headerBox.width) - (stageBox.x + stageBox.width))).toBeLessThan(2);
});
