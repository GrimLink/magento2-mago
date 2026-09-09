/*
 * Copyright © Maggy Assistant
 */

import {errors, type Locator, type Page} from '@playwright/test';
import AdminModals from 'Pages/backend/AdminModals';

/* These clicks act on the already-loaded panel, so a click that has not landed in five seconds
   is blocked rather than slow. Kept short deliberately: it is what lets a covered panel fail with
   a named error in seconds instead of waiting out the suite's 25s test timeout. */
const INTERACTION_TIMEOUT_MS = 5000;

/* Below the suite's 25s test timeout (playwright.config.ts) so a stuck bind still fails with an
   explaining message instead of surfacing as a bare test timeout, but with enough headroom that
   a busy dev instance loading chat-panel.js under require(['marked'], ...) does not trip it. */
const TOGGLE_BIND_TIMEOUT_MS = 20000;

export default class ChatPanel {
  private readonly adminModals = new AdminModals();

  async openOnDashboard(page: Page) {
    const adminPath = process.env.ADMIN_PATH || 'admin';

    this.adminModals.watch(page);

    /* Wait for stylesheets, not just markup. Until the CSS applies the toggle sits in the
       header as an unstyled button rather than the icon next to search. */
    await page.goto('/' + adminPath + '/admin/dashboard', {waitUntil: 'load'});

    await this.open(page);
  }

  /**
   * chat-panel.js is pulled in through require(['marked'], ...), so the toggle exists in
   * the markup well before its click handler is bound. Clicking in that window is a silent
   * no-op, which shows up as the panel never opening.
   */
  async open(page: Page, toggleBindTimeout: number = TOGGLE_BIND_TIMEOUT_MS) {
    await this.waitForToggleBound(page, toggleBindTimeout);

    await this.adminModals.guard(page, () => page.locator('#maggy-toggle').click({timeout: INTERACTION_TIMEOUT_MS}));
    await page.locator('#maggy-chat.is-open').waitFor();
  }

  async close(page: Page) {
    await this.adminModals.guard(page, () => page.locator('#maggy-close').click({timeout: INTERACTION_TIMEOUT_MS}));
  }

  async ask(page: Page, question: string) {
    await this.input(page).fill(question);
    await this.adminModals.guard(page, () => this.sendButton(page).click({timeout: INTERACTION_TIMEOUT_MS}));
  }

  panel(page: Page): Locator {
    return page.locator('#maggy-chat');
  }

  welcome(page: Page): Locator {
    return page.locator('#maggy-welcome');
  }

  input(page: Page): Locator {
    return page.locator('#maggy-input');
  }

  sendButton(page: Page): Locator {
    return page.locator('#maggy-send');
  }

  userMessages(page: Page): Locator {
    return page.locator('#maggy-messages .maggy-message.is-user');
  }

  assistantMessages(page: Page): Locator {
    return page.locator('#maggy-messages .maggy-message.is-assistant:not(#maggy-loading)');
  }

  lastAssistantMessage(page: Page): Locator {
    return this.assistantMessages(page).last();
  }

  toolTags(page: Page): Locator {
    return page.locator('#maggy-messages .maggy-tool-tag');
  }

  confirmActions(page: Page): Locator {
    return page.locator('#maggy-messages .maggy-confirm-actions');
  }

  confirmButton(page: Page): Locator {
    return page.locator('#maggy-messages .maggy-btn--confirm');
  }

  rejectButton(page: Page): Locator {
    return page.locator('#maggy-messages .maggy-btn--reject');
  }

  slashMenu(page: Page): Locator {
    return page.locator('#maggy-slash-menu');
  }

  slashItems(page: Page): Locator {
    return page.locator('#maggy-slash-menu .maggy-slash-item');
  }

  private async waitForToggleBound(page: Page, timeout: number): Promise<void> {
    try {
      await page.waitForFunction(() => {
        const toggle: HTMLElement | null = document.querySelector('#maggy-toggle');

        return toggle !== null && toggle.onclick !== null;
      }, undefined, {timeout});
    } catch (error) {
      if (!(error instanceof errors.TimeoutError)) {
        throw error;
      }

      throw new Error(
        'The chat panel toggle (#maggy-toggle) never bound its click handler within ' + timeout + 'ms.'
      );
    }
  }
}
