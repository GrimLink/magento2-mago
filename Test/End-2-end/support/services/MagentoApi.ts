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

  async findCustomer(request: APIRequestContext, email: string) {
    const response = await request.get('/rest/V1/customers/search', {
      headers: {Authorization: 'Bearer ' + await this.getToken(request)},
      params: {
        'searchCriteria[filterGroups][0][filters][0][field]': 'email',
        'searchCriteria[filterGroups][0][filters][0][value]': email,
      },
    });

    const result = await response.json();

    return result.items?.[0] ?? null;
  }

  async createCustomer(
    request: APIRequestContext,
    customer: {email: string, firstname: string, lastname: string, city: string, telephone: string}
  ) {
    const response = await request.post('/rest/V1/customers', {
      headers: {Authorization: 'Bearer ' + await this.getToken(request)},
      data: {
        customer: {
          email: customer.email,
          firstname: customer.firstname,
          lastname: customer.lastname,
          addresses: [
            {
              default_billing: true,
              default_shipping: true,
              firstname: customer.firstname,
              lastname: customer.lastname,
              street: ['Teststraat 1'],
              city: customer.city,
              postcode: '1234 AB',
              country_id: 'NL',
              telephone: customer.telephone,
            },
          ],
        },
      },
    });

    return response.json();
  }

  async deleteCustomer(request: APIRequestContext, email: string) {
    const customer = await this.findCustomer(request, email);

    if (customer === null) {
      return;
    }

    await request.delete('/rest/V1/customers/' + customer.id, {
      headers: {Authorization: 'Bearer ' + await this.getToken(request)},
    });
  }

  /**
   * The stored conversation as the raw JSON string the web API returns. Unlike the panel's own
   * mago/chat/load, this endpoint returns messages as persisted, without rehydrating vault
   * tokens, so it is the read path for asserting on the stored copy of a conversation.
   */
  async getStoredConversation(request: APIRequestContext, conversationId: number): Promise<string> {
    const response = await request.get('/rest/V1/mago/conversations/' + conversationId, {
      headers: {Authorization: 'Bearer ' + await this.getToken(request)},
    });

    return response.json();
  }
}
