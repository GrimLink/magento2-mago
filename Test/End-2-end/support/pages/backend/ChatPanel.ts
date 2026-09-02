/*
 * Copyright © Maggy Assistant
 */

import {type Locator, type Page} from '@playwright/test';

export default class ChatPanel {
  async openOnDashboard(page: Page) {
    const adminPath = process.env.ADMIN_PATH || 'admin';

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
  async open(page: Page) {
    await page.waitForFunction(() => {
      const toggle: HTMLElement | null = document.querySelector('#maggy-toggle');

      return toggle !== null && toggle.onclick !== null;
    });

    await page.locator('#maggy-toggle').click();
    await page.locator('#maggy-chat.is-open').waitFor();
  }

  async close(page: Page) {
    await page.locator('#maggy-close').click();
  }

  async ask(page: Page, question: string) {
    await this.input(page).fill(question);
    await this.sendButton(page).click();
  }

  panel(page: Page): Locator {
    return page.locator('#maggy-chat');
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
}
