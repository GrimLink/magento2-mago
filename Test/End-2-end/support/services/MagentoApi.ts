/*
 * Copyright © Mago Assistant
 */

import {type APIRequestContext} from '@playwright/test';

export default class MagentoApi {
  private token: string | null = null;

  private async getToken(request: APIRequestContext): Promise<string> {
    if (this.token === null) {
      const response = await request.post('/rest/V1/integration/admin/token', {
        data: {
          username: process.env.ADMIN_USERNAME || 'exampleuser',
          password: process.env.ADMIN_PASSWORD || 'examplepassword123',
        },
      });

      this.token = await response.json();
    }

    return this.token;
  }

  async findCmsPage(request: APIRequestContext, identifier: string) {
    const response = await request.get('/rest/V1/cmsPage/search', {
      headers: {Authorization: 'Bearer ' + await this.getToken(request)},
      params: {
        'searchCriteria[filterGroups][0][filters][0][field]': 'identifier',
        'searchCriteria[filterGroups][0][filters][0][value]': identifier,
      },
    });

    const result = await response.json();

    return result.items?.[0] ?? null;
  }

  async deleteCmsPage(request: APIRequestContext, identifier: string) {
    const page = await this.findCmsPage(request, identifier);

    if (page === null) {
      return;
    }

    await request.delete('/rest/V1/cmsPage/' + page.id, {
      headers: {Authorization: 'Bearer ' + await this.getToken(request)},
    });
  }
}
