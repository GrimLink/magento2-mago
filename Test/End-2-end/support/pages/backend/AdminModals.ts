/*
 * Copyright © Mago Assistant
 */

import {errors, type Locator, type Page, type Response} from '@playwright/test';

/* The `confirm` class is what separates the alert/confirm family from Page Builder's own dialogs,
   which share `modal-popup` but carry their own modalClass. The release-notification popup is the
   other known blocker and carries neither, so it is named explicitly rather than widened to
   `.modal-popup._show`, which would catch Page Builder too. */
const BLOCKING_MODAL_SELECTOR = 'div.modals-wrapper aside.modal-popup.confirm._show, '
  + 'div.modals-wrapper aside.modal-popup.release-notification-modal._show';
const ATTENTION_ALERT_TITLE = 'Attention';
const FAILED_REQUEST_BODY_EXCERPT_LENGTH = 200;

interface FailedAdminRequest {
  url: string;
  status: number;
  bodyExcerpt: string;
}

/**
 * Watches an admin page for the `Attention / Something went wrong.` alert and the rest of the
 * `Magento_Ui/js/modal/alert` / `Magento_Ui/js/modal/confirm` family, and turns a bounded
 * interaction that times out because one of them is covering the page into an immediate, named
 * failure instead of the full Playwright test timeout on "intercepts pointer events". It never
 * clicks, closes or waits the modal away, so a genuine application error still fails the spec.
 */
export default class AdminModals {
  private readonly watchedPages = new WeakSet<Page>();
  private readonly lastFailedAdminRequests = new WeakMap<Page, FailedAdminRequest>();

  /**
   * Starts recording failed admin XHRs on the page. Safe to call more than once for the same
   * page: only the first call attaches a listener. Call it as early as possible - both known
   * blockers appear asynchronously, and the request that produces the "Something went wrong."
   * alert can resolve before the interaction it will eventually block even starts.
   */
  watch(page: Page): void {
    if (this.watchedPages.has(page)) {
      return;
    }

    this.watchedPages.add(page);
    page.on('response', (response) => {
      void this.recordIfFailedAdminRequest(page, response);
    });
  }

  async guard<T>(page: Page, interaction: () => Promise<T>): Promise<T> {
    this.watch(page);

    try {
      return await interaction();
    } catch (error) {
      throw await this.toBlockingModalError(page, error);
    }
  }

  private async recordIfFailedAdminRequest(page: Page, response: Response): Promise<void> {
    if (response.ok() || !this.isAdminRequest(response.url())) {
      return;
    }

    const body = await response.text().catch(() => '');

    this.lastFailedAdminRequests.set(page, {
      url: response.url(),
      status: response.status(),
      bodyExcerpt: body.slice(0, FAILED_REQUEST_BODY_EXCERPT_LENGTH),
    });
  }

  private isAdminRequest(url: string): boolean {
    const adminPath = process.env.ADMIN_PATH || 'admin';

    return new URL(url).pathname.includes('/' + adminPath + '/');
  }

  private async toBlockingModalError(page: Page, error: unknown): Promise<unknown> {
    if (!(error instanceof errors.TimeoutError)) {
      return error;
    }

    const modal = page.locator(BLOCKING_MODAL_SELECTOR);

    if (await modal.count() === 0) {
      return error;
    }

    return new Error(await this.describeBlockingModal(page, modal.first()));
  }

  private async describeBlockingModal(page: Page, modal: Locator): Promise<string> {
    const title = (await modal.locator('.modal-title').textContent())?.trim() ?? '';
    const body = (await modal.locator('.modal-content').textContent())?.trim() ?? '';
    const message = `A "${title}" modal is blocking the interaction: "${body}"`;

    if (title !== ATTENTION_ALERT_TITLE) {
      return message;
    }

    const failedRequest = this.lastFailedAdminRequests.get(page);

    if (failedRequest === undefined) {
      return message;
    }

    return message + ` Failing admin request: ${failedRequest.status} ${failedRequest.url} - ${failedRequest.bodyExcerpt}`;
  }
}
