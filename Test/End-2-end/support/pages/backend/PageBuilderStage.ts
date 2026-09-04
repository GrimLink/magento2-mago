/*
 * Copyright © Maggy Assistant
 */

import {expect, type Locator, type Page} from '@playwright/test';

export default class PageBuilderStage {
  /**
   * Admin deep links carry a per-session secret key, so the CMS page edit URL can't be
   * built by hand. Navigate the same way an admin would: Content > Pages > row menu > Edit.
   */
  async openCmsPage(page: Page, title: string) {
    const adminPath = process.env.ADMIN_PATH || 'admin';

    await page.goto('/' + adminPath + '/admin/dashboard', {waitUntil: 'load'});

    const contentLink = page.locator('a:has-text("Content")').first();
    const pagesLink = page.locator('a:has-text("Pages")').first();

    // The flyout submenu occasionally doesn't open on the first click; retry rather
    // than let a one-off UI hiccup fail the whole test.
    for (let attempt = 0; !(await pagesLink.isVisible()); attempt++) {
      if (attempt >= 5) {
        throw new Error('The Content flyout menu never revealed the Pages link.');
      }

      await contentLink.click();
      await pagesLink.waitFor({state: 'visible', timeout: 3000}).catch(() => undefined);
    }

    await pagesLink.click();
    await page.waitForLoadState('load');

    const row = page.locator('tr', {hasText: title});
    await row.locator('button:has-text("Select")').click();
    await row.locator('a:has-text("Edit")').click();
    await page.waitForLoadState('load');

    const contentTitle = page.locator('[data-index="content"] .fieldset-wrapper-title');

    if (await contentTitle.getAttribute('data-state-collapsible') === 'closed') {
      await contentTitle.click();
    }
  }

  /**
   * Converting plain content to Page Builder lands straight in full-screen mode, so this
   * doubles as "enter full screen" for a page that hasn't used Page Builder before.
   */
  async switchToPageBuilder(page: Page) {
    await page.getByRole('button', {name: 'Edit with Page Builder'}).click();

    await expect(this.stage(page)).toHaveClass(/stage-full-screen/);
  }

  stage(page: Page): Locator {
    return page.locator('.pagebuilder-stage-wrapper');
  }

  closeFullScreenButton(page: Page): Locator {
    return page.getByTitle('Close Full Screen');
  }

  fullScreenHeader(page: Page): Locator {
    return page.locator('.pagebuilder-stage-wrapper.stage-full-screen .pagebuilder-header');
  }
}
