/*
 * Copyright © Maggy Assistant
 */

import {expect, test} from '@playwright/test';
import AdminModals from 'Pages/backend/AdminModals';
import ChatPanel from 'Pages/backend/ChatPanel';
import PageBuilderStage from 'Pages/backend/PageBuilderStage';

const ADMIN_PATH = process.env.ADMIN_PATH || 'admin';

/* data-e2e-injected-modal marks this wrapper as the spec's own, so removeBlockingModal() cannot
   accidentally remove a genuine (even if empty and hidden) div.modals-wrapper the admin theme
   already left in the DOM, which document.querySelector('div.modals-wrapper') would otherwise
   match first. */
const MODALS_WRAPPER_HTML = `
  <div class="modals-wrapper" data-e2e-injected-modal="1">
    <aside role="dialog" class="modal-popup confirm _show" data-role="modal" data-type="popup"
           style="position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,.5);">
      <div class="modal-inner-wrap">
        <header class="modal-header">
          <h1 class="modal-title" data-role="title">Attention</h1>
        </header>
        <div class="modal-content" data-role="content">Something went wrong.</div>
      </div>
    </aside>
  </div>
`;

const PAGE_BUILDER_SLIDE_MODAL_HTML = `
  <div class="modals-wrapper" data-e2e-injected-modal="1">
    <aside role="dialog" class="modal-slide template-manager-save _show" data-role="modal" data-type="slide"
           style="position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,.5);">
      <div class="modal-inner-wrap">
        <header class="modal-header">
          <h1 class="modal-title" data-role="title">Save Content as Template</h1>
        </header>
        <div class="modal-content" data-role="content">Name your template.</div>
      </div>
    </aside>
  </div>
`;

const clickTarget = async (page, extraHtml: string = ''): Promise<void> => {
  await page.setContent('<button id="target">Click me</button>' + extraHtml);
};

const injectBlockingModal = async (page): Promise<void> => {
  await page.evaluate((html) => document.body.insertAdjacentHTML('beforeend', html), MODALS_WRAPPER_HTML);
};

const removeBlockingModal = async (page): Promise<void> => {
  await page.evaluate(() => document.querySelector('div.modals-wrapper[data-e2e-injected-modal]')?.remove());
};

test('throws immediately when a modal is showing over the element instead of waiting for the test timeout', async ({page}) => {
  const adminModals = new AdminModals();

  await clickTarget(page, MODALS_WRAPPER_HTML);

  const startedAt = Date.now();
  const attempt = adminModals.guard(page, () => page.locator('#target').click({timeout: 1000}));

  await expect(attempt).rejects.toThrow();

  expect(Date.now() - startedAt).toBeLessThan(5000);
});

test("names the blocking modal's title and body text in the thrown error", async ({page}) => {
  const adminModals = new AdminModals();

  await clickTarget(page, MODALS_WRAPPER_HTML);

  await expect(
    adminModals.guard(page, () => page.locator('#target').click({timeout: 1000}))
  ).rejects.toThrow(/Attention.*Something went wrong\./s);
});

test('attaches the failing admin XHR url, status and body excerpt when the modal is the Attention alert', async ({page}) => {
  const adminModals = new AdminModals();

  await page.route('**/' + ADMIN_PATH + '/mui/index/render/**', (route) => route.fulfill({
    status: 500,
    contentType: 'application/json',
    body: JSON.stringify({message: 'Grid data source is unavailable right now.'}),
  }));

  await page.goto('/');

  /* The guard only ever learns about a failed admin request through a listener attached by
     watch(), so it has to be armed before the request fires - exactly like ChatPanel and
     PageBuilderStage arm it before their own navigation, and well before any interaction. */
  adminModals.watch(page);

  await clickTarget(page);
  await page.evaluate(
    (path) => fetch(path).then((response) => response.text()).catch(() => undefined),
    '/' + ADMIN_PATH + '/mui/index/render/namespace/notification_area'
  );
  await page.evaluate((html) => {
    document.body.insertAdjacentHTML('beforeend', html);
  }, MODALS_WRAPPER_HTML);

  /* Recording the failed request happens asynchronously off the 'response' event, so poll
     rather than assume it has already landed by the time the fetch promise above settles. */
  await expect.poll(async () => {
    try {
      await adminModals.guard(page, () => page.locator('#target').click({timeout: 300}));

      return 'resolved without throwing';
    } catch (error) {
      return (error as Error).message;
    }
  }, {timeout: 5000}).toMatch(/500.*mui\/index\/render.*Grid data source is unavailable right now\./s);
});

test('rethrows the original error unchanged when the interaction times out with no modal showing', async ({page}) => {
  const adminModals = new AdminModals();

  await page.setContent('<button id="target" style="display:none">Click me</button>');

  let thrown: Error | null = null;

  try {
    await adminModals.guard(page, () => page.locator('#target').click({timeout: 500}));
  } catch (error) {
    thrown = error as Error;
  }

  expect(thrown).not.toBeNull();
  expect(thrown?.message).not.toMatch(/modal/i);
});

test('does not throw when no modal is present', async ({page}) => {
  const adminModals = new AdminModals();

  await clickTarget(page);

  await expect(adminModals.guard(page, () => page.locator('#target').click({timeout: 1000}))).resolves.toBeUndefined();
});

test("does not throw for Page Builder's own slide-in modals", async ({page}) => {
  const adminModals = new AdminModals();

  await clickTarget(page, PAGE_BUILDER_SLIDE_MODAL_HTML);

  let thrown: Error | null = null;

  try {
    await adminModals.guard(page, () => page.locator('#target').click({timeout: 1000}));
  } catch (error) {
    thrown = error as Error;
  }

  expect(thrown).not.toBeNull();
  expect(thrown?.message).not.toMatch(/Save Content as Template/);
});

test('does not dismiss, close or wait away the blocking modal', async ({page}) => {
  const adminModals = new AdminModals();

  await clickTarget(page, MODALS_WRAPPER_HTML);

  await expect(
    adminModals.guard(page, () => page.locator('#target').click({timeout: 1000}))
  ).rejects.toThrow();

  await expect(page.locator('aside.modal-popup._show')).toBeVisible();
});

test('guards opening the chat panel, asking a question and closing the panel', async ({page}) => {
  const chatPanel = new ChatPanel();

  /* The admin's own notifications grid genuinely fails intermittently in this environment - see
     _plan.md's "blocker B". Stub its request so this test only ever sees the modal it injects
     itself, rather than occasionally tripping on a real, unrelated occurrence of the same alert. */
  await page.route('**/' + ADMIN_PATH + '/mui/index/render/**', (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({items: [], totalRecords: 0}),
  }));

  await page.goto('/' + ADMIN_PATH + '/admin/dashboard', {waitUntil: 'load'});
  await injectBlockingModal(page);

  const openStartedAt = Date.now();

  await expect(chatPanel.open(page)).rejects.toThrow(/Attention.*Something went wrong\./s);
  expect(Date.now() - openStartedAt, 'open() must fail fast, not wait out the test timeout').toBeLessThan(10000);

  await removeBlockingModal(page);
  await chatPanel.open(page);

  await injectBlockingModal(page);

  const askStartedAt = Date.now();

  await expect(chatPanel.ask(page, 'Which products contain candle?')).rejects.toThrow(/Attention.*Something went wrong\./s);
  expect(Date.now() - askStartedAt, 'ask() must fail fast, not wait out the test timeout').toBeLessThan(10000);

  await removeBlockingModal(page);
  await injectBlockingModal(page);

  const closeStartedAt = Date.now();

  await expect(chatPanel.close(page)).rejects.toThrow(/Attention.*Something went wrong\./s);
  expect(Date.now() - closeStartedAt, 'close() must fail fast, not wait out the test timeout').toBeLessThan(10000);
});

test('guards the Content flyout click', async ({page}) => {
  const pageBuilderStage = new PageBuilderStage();

  /* openCmsPage() navigates to the dashboard itself, so a modal inserted before calling it would
     be wiped out by that goto. addInitScript re-runs on every navigation of this page, including
     that internal one, so the modal is still there for the very first click it attempts. */
  await page.addInitScript((html) => {
    window.addEventListener('DOMContentLoaded', () => document.body.insertAdjacentHTML('beforeend', html));
  }, MODALS_WRAPPER_HTML);

  const startedAt = Date.now();

  await expect(pageBuilderStage.openCmsPage(page, 'About us')).rejects.toThrow(/Attention.*Something went wrong\./s);
  expect(
    Date.now() - startedAt,
    'the Content flyout click must fail fast, not wait out the test timeout'
  ).toBeLessThan(10000);
});

test('reports the real Attention alert, with the failing admin XHR, when a grid provider request fails', async ({page}) => {
  const pageBuilderStage = new PageBuilderStage();

  /* Reproduces blocker B for real, rather than injecting markup: every admin page renders a
     Magento_Ui/js/grid/provider listing (the notifications area), and the CMS Pages grid is one
     too, so failing mui/index/render makes Magento's own alert.js raise the exact modal this
     guard exists for, wherever in openCmsPage() it happens to be showing. */
  await page.route('**/' + ADMIN_PATH + '/mui/index/render/**', (route) => route.fulfill({
    status: 500,
    contentType: 'application/json',
    body: JSON.stringify({message: 'Grid data source is unavailable right now.'}),
  }));

  const startedAt = Date.now();

  await expect(pageBuilderStage.openCmsPage(page, 'About us')).rejects.toThrow(
    /Attention.*Something went wrong\..*500.*mui\/index\/render/s
  );
  expect(Date.now() - startedAt, 'the guarded click must fail fast, not wait out the test timeout').toBeLessThan(15000);
});

test('fails with an explaining message when the chat panel toggle never binds its click handler', async ({page}) => {
  const chatPanel = new ChatPanel();

  await page.setContent('<button id="maggy-toggle"></button>');

  await expect(chatPanel.open(page, 500)).rejects.toThrow(/never bound its click handler/);
});
