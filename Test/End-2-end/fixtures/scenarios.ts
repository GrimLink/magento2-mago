/*
 * Copyright © Mago Assistant
 */

import {type ChatScenario, textDeltas} from 'Actions/backend/ChatMock';

const CONVERSATION_ID = 4242;
const MESSAGE_ID = 100501;

export const createCmsPage: ChatScenario = {
    stream: [
        { event: 'conversation', data: { conversation_id: CONVERSATION_ID, admin_user: 'Tester' } },
        ...textDeltas('Sure, I will create that CMS page for you. '),
        {
            event: 'tool_call',
            data: {
                id: 'toolu_cms_1',
                name: 'cms_data',
                input: {
                    action: 'create_page',
                    identifier: 'summer-sale',
                    title: 'Summer Sale',
                    content: '<p>Everything must go.</p>',
                    is_active: true,
                },
            },
        },
        {
            event: 'confirm',
            data: {
                tools: [
                    {
                        name: 'cms_data',
                        description: 'Manage CMS pages and blocks',
                        input: { action: 'create_page', identifier: 'summer-sale', title: 'Summer Sale' },
                    },
                ],
            },
        },
        {
            event: 'done',
            data: { message_id: MESSAGE_ID, conversation_id: CONVERSATION_ID, pending_confirmation: true },
        },
    ],
    status: { message_id: MESSAGE_ID },
    confirm: [
        {
            event: 'tool_call',
            data: { id: 'toolu_cms_1', name: 'cms_data', input: { action: 'create_page' } },
        },
        ...textDeltas('Done. The page "Summer Sale" is live at /summer-sale.'),
        { event: 'done', data: { conversation_id: CONVERSATION_ID } },
    ],
}

export const createCoupon: ChatScenario = {
    stream: [
        { event: 'conversation', data: { conversation_id: CONVERSATION_ID, admin_user: 'Tester' } },
        ...textDeltas('I can set up a cart price rule with that coupon code. '),
        {
            event: 'tool_call',
            data: {
                id: 'toolu_coupon_1',
                name: 'coupon_manager',
                input: {
                    action: 'create_rule',
                    name: 'Summer 20',
                    discount_type: 'percent',
                    discount_amount: 20,
                    coupon_code: 'SUMMER20',
                },
            },
        },
        {
            event: 'confirm',
            data: {
                tools: [
                    {
                        name: 'coupon_manager',
                        description: 'Manage cart price rules and coupon codes',
                        input: {
                            action: 'create_rule',
                            name: 'Summer 20',
                            discount_type: 'percent',
                            discount_amount: 20,
                            coupon_code: 'SUMMER20',
                        },
                    },
                ],
            },
        },
        {
            event: 'done',
            data: { message_id: MESSAGE_ID, conversation_id: CONVERSATION_ID, pending_confirmation: true },
        },
    ],
    status: { message_id: MESSAGE_ID },
    confirm: [
        ...textDeltas('Created cart price rule "Summer 20" with coupon code SUMMER20.'),
        { event: 'done', data: { conversation_id: CONVERSATION_ID } },
    ],
    reject: { success: true },
}

export const declineProductCreation: ChatScenario = {
    stream: [
        { event: 'conversation', data: { conversation_id: CONVERSATION_ID, admin_user: 'Tester' } },
        ...textDeltas(
            'I cannot create products. My catalog tools are read-only, so you will have to add the '
            + 'Anti-musquito candle through Catalog > Products yourself.'
        ),
        {
            event: 'done',
            data: { message_id: MESSAGE_ID, conversation_id: CONVERSATION_ID, pending_confirmation: false },
        },
    ],
}

export const lookupProduct: ChatScenario = {
    stream: [
        { event: 'conversation', data: { conversation_id: CONVERSATION_ID, admin_user: 'Tester' } },
        {
            event: 'tool_call',
            data: { id: 'toolu_product_1', name: 'product_data', input: { action: 'search', query: 'candle' } },
        },
        ...textDeltas('I found 2 products matching "candle".'),
        {
            event: 'done',
            data: { message_id: MESSAGE_ID, conversation_id: CONVERSATION_ID, pending_confirmation: false },
        },
    ],
}

export const streamFailure: ChatScenario = {
    stream: [
        { event: 'conversation', data: { conversation_id: CONVERSATION_ID, admin_user: 'Tester' } },
        { event: 'error', data: { error: 'API error (HTTP 429) rate limit exceeded' } },
        { event: 'done', data: { conversation_id: CONVERSATION_ID } },
    ],
}
