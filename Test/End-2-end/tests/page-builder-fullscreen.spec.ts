/*
 * Copyright © Maggy Assistant
 */

import {expect, test} from '@playwright/test';
import ChatPanel from 'Pages/backend/ChatPanel';
import PageBuilderStage from 'Pages/backend/PageBuilderStage';

const chatPanel = new ChatPanel();
const pageBuilderStage = new PageBuilderStage();

const ABOUT_US_PAGE_TITLE = 'About us';

test('Closes full-screen Page Builder when Maggy was opened first', async ({page}) => {
  await pageBuilderStage.openCmsPage(page, ABOUT_US_PAGE_TITLE);

  await chatPanel.open(page);
  await pageBuilderStage.switchToPageBuilder(page);

  await pageBuilderStage.closeFullScreenButton(page).click();

  await expect(pageBuilderStage.stage(page)).not.toHaveClass(/stage-full-screen/);
});

test('Closes full-screen Page Builder when it was opened before Maggy', async ({page}) => {
  await pageBuilderStage.openCmsPage(page, ABOUT_US_PAGE_TITLE);
  await pageBuilderStage.switchToPageBuilder(page);

  await chatPanel.open(page);

  await pageBuilderStage.closeFullScreenButton(page).click();

  await expect(pageBuilderStage.stage(page)).not.toHaveClass(/stage-full-screen/);
});
