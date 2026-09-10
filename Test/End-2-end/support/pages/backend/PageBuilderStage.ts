/*
 * Copyright © Mago Assistant
 */

import {expect, type Locator, type Page} from '@playwright/test';
import AdminModals from 'Pages/backend/AdminModals';

/* Clicks that only move the admin UI around: a menu that has not opened in five seconds is
   blocked, not slow, so the bound stays short and a covered menu fails with a named error fast. */
const INTERACTION_TIMEOUT_MS = 5000;

/* Clicks that load a new admin page need considerably more room. The first navigation after a
   cache flush recompiles DI and view_preprocessed, and reliably takes over five seconds, so the
   short bound above would fail a click that was only slow. Still well under the suite's 25s test
   timeout, so a modal-blocked navigation is still named rather than timing out. */
const NAVIGATION_TIMEOUT_MS = 10000;

export default class PageBuilderStage {
  private readonly adminModals = new AdminModals();

  /**
   * Admin deep links carry a per-session secret key, so the CMS page edit URL can't be
   * built by hand. Navigate the same way an admin would: Content > Pages > row menu > Edit.
   */
  async openCmsPage(page: Page, title: string) {
    const adminPath = process.env.ADMIN_PATH || 'admin';

    this.adminModals.watch(page);

    await page.goto('/' + adminPath + '/admin/dashboard', {waitUntil: 'load'});

    const contentLink = page.locator('a:has-text("Content")').first();
    const pagesLink = page.locator('a:has-text("Pages")').first();

    // The flyout submenu occasionally doesn't open on the first click; retry rather
    // than let a one-off UI hiccup fail the whole test.
    for (let attempt = 0; !(await pagesLink.isVisible()); attempt++) {
      if (attempt >= 5) {
        throw new Error('The Content flyout menu never revealed the Pages link.');
      }

      await this.adminModals.guard(page, () => contentLink.click({timeout: INTERACTION_TIMEOUT_MS}));
      await pagesLink.waitFor({state: 'visible', timeout: 3000}).catch(() => undefined);
    }

    await this.adminModals.guard(page, () => pagesLink.click({timeout: NAVIGATION_TIMEOUT_MS}));
    await page.waitForLoadState('load');

    const row = page.locator('tr', {hasText: title});
    await this.adminModals.guard(page, () => row.locator('button:has-text("Select")').click({timeout: INTERACTION_TIMEOUT_MS}));
    await this.adminModals.guard(page, () => row.locator('a:has-text("Edit")').click({timeout: NAVIGATION_TIMEOUT_MS}));
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
