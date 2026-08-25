/*
 * Copyright © Maggy Assistant
 */

import {expect} from "@playwright/test";

export default class BackendLogin {
  async login(page) {
    const adminPath = process.env.ADMIN_PATH || 'admin';
    const username = process.env.ADMIN_USERNAME || 'exampleuser';
    const password = process.env.ADMIN_PASSWORD || 'examplepassword123';

    await page.goto('/' + adminPath);

    await page.getByLabel('Username').fill(username);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', {name: 'Sign in'}).click();

    await page.waitForURL('**/' + adminPath + '/**/dashboard/**');

    await expect(
      page.locator('#maggy-toggle'),
      'The Maggy chat panel is not rendered. Run: bin/magento config:set maggy/general/enabled 1'
    ).toBeAttached();
  }
}
