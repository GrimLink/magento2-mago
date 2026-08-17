/*
 * Copyright © Maggy Assistant
 */

import {type Page, type Route} from '@playwright/test';

export type SseEvent = {
  event: string;
  data: Record<string, unknown>;
};

export type ChatScenario = {
  stream: SseEvent[];
  confirm?: SseEvent[];
  status?: Record<string, unknown>;
  reject?: Record<string, unknown>;
  history?: Record<string, unknown>;
};

const toSseBody = (events: SseEvent[]): string =>
  events.map(({event, data}) => `event: ${event}\ndata: ${JSON.stringify(data)}\n\n`).join('');

export const textDeltas = (sentence: string): SseEvent[] =>
  sentence.split(/(?<= )/).map((chunk) => ({event: 'text', data: {text: chunk}}));

export default class ChatMock {
  /**
   * The browser never talks to Claude or OpenAI. It reads an SSE stream of
   * text / tool_call / confirm / done / error events from maggy/chat/stream.
   * Replaying that stream keeps the tests deterministic and free.
   */
  async install(page: Page, scenario: ChatScenario) {
    await page.route(/\/maggy\/chat\/stream/, (route) => this.fulfilSse(route, scenario.stream));
    await page.route(/\/maggy\/chat\/status/, (route) => this.fulfilJson(route, scenario.status ?? {}));
    await page.route(/\/maggy\/chat\/history/, (route) => this.fulfilJson(route, scenario.history ?? {conversations: []}));
    await page.route(/\/maggy\/chat\/reject/, (route) => this.fulfilJson(route, scenario.reject ?? {success: true}));
    await page.route(/\/maggy\/chat\/confirm/, (route) => this.fulfilSse(route, scenario.confirm ?? []));
  }

  countRequestsTo(page: Page, pattern: RegExp) {
    let total = 0;

    page.on('request', (request) => {
      if (pattern.test(request.url())) {
        total++;
      }
    });

    return {total: () => total};
  }

  private fulfilSse(route: Route, events: SseEvent[]) {
    return route.fulfill({
      status: 200,
      headers: {'content-type': 'text/event-stream'},
      body: toSseBody(events),
    });
  }

  private fulfilJson(route: Route, payload: Record<string, unknown>) {
    return route.fulfill({
      status: 200,
      headers: {'content-type': 'application/json'},
      body: JSON.stringify(payload),
    });
  }
}
