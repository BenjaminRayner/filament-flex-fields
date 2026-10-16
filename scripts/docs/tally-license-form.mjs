import { createHash } from 'node:crypto';
import { writeFileSync } from 'node:fs';

// Stable field identities preserve response references when updating this form.
const id = (name) => {
    const hex = createHash('sha256').update(`flex-fields:license-request:v1:${name}`).digest('hex');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-5${hex.slice(13, 16)}-a${hex.slice(17, 20)}-${hex.slice(20, 32)}`;
};

export function buildLicenseRequestForm() {
    const blocks = [];
    const questions = {};
    const add = (key, type, payload, groupType = type, groupKey = key) => {
        const block = { uuid: id(`block:${key}`), type, groupUuid: id(`group:${groupKey}`), groupType, payload };
        blocks.push(block);
        return block;
    };
    const text = (key, html, hidden = false) => add(key, 'TEXT', { html, isHidden: hidden });
    const page = (key, index, name, label, thankYou = false) => add(key, 'PAGE_BREAK', {
        index, name, isFirst: index === 0, isLast: key === 'page:contact', isThankYouPage: thankYou,
        isQualifiedForThankYouPage: thankYou, button: { label },
    });
    const radio = (key, title, options, hidden = false) => {
        const titleBlock = add(`${key}:title`, 'TITLE', { html: title, isHidden: hidden }, 'QUESTION');
        const optionBlocks = options.map(([value, label], index) => add(`${key}:${value}`, 'MULTIPLE_CHOICE_OPTION', {
            text: label, index, isFirst: index === 0, isLast: index === options.length - 1,
            isRequired: true, isHidden: hidden, allowMultiple: false, badgeType: 'OFF', hasBadge: false,
        }, 'MULTIPLE_CHOICE', key));
        const field = {
            uuid: id(`group:${key}`), type: 'INPUT_FIELD', questionType: 'MULTIPLE_CHOICE_OPTION',
            blockGroupUuid: id(`group:${key}`), title,
        };
        questions[key] = { field, blocks: [titleBlock.uuid, ...optionBlocks.map(b => b.uuid)],
            options: Object.fromEntries(options.map(([value], index) => [value, optionBlocks[index].uuid])) };
    };
    const input = (key, title, type, required, placeholder) => {
        add(`${key}:title`, 'TITLE', { html: title }, 'QUESTION');
        add(key, type, { isRequired: required, placeholder, name: key,
            ...(type === 'INPUT_TEXT' || type === 'TEXTAREA' ? { hasMaxCharacters: true, maxCharacters: type === 'TEXTAREA' ? 3000 : 250 } : {}),
        });
    };
    const condition = (key, value) => ({ uuid: id(`condition:${key}:${value}`), type: 'SINGLE',
        payload: { field: questions[key].field, comparison: 'IS', value: questions[key].options[value] } });
    const group = (operator, conditions) => ({ uuid: id(`condition-group:${JSON.stringify(conditions)}:${operator}`),
        type: 'GROUP', payload: { logicalOperator: operator, conditionals: conditions } });
    const and = (...conditions) => group('AND', conditions);
    const or = (...conditions) => group('OR', conditions);
    const inverse = (condition) => condition.type === 'GROUP'
        ? group(condition.payload.logicalOperator === 'AND' ? 'OR' : 'AND', condition.payload.conditionals.map(inverse))
        : { ...condition, uuid: id(`inverse:${condition.uuid}`), payload: { ...condition.payload, comparison: 'IS_NOT' } };
    const visibility = (key, condition, blockIds) => {
        for (const [state, match] of [['SHOW', condition], ['HIDE', inverse(condition)]]) {
            add(`${key}:${state}`, 'CONDITIONAL_LOGIC', {
                logicalOperator: 'AND', conditionals: [match],
                actions: [{ uuid: id(`action:${key}:${state}`), type: `${state}_BLOCKS`, payload: { [state === 'SHOW' ? 'showBlocks' : 'hideBlocks']: blockIds } }],
            });
        }
    };

    add('title', 'FORM_TITLE', { title: 'FLEX Fields — License request', html: 'FLEX Fields — License request', button: { label: 'Continue' } }, 'TEXT');
    add('heading:use', 'HEADING_2', { html: 'Find the right license for your project' });
    text('intro', 'Answer a few questions about your project. We’ll suggest a starting point, then you can ask for a quote or confirm your scope.');
    radio('use', 'How will you use FLEX Fields?', [
        ['personal', 'Personal projects, learning or research'],
        ['internal', 'An internal tool for my own business'],
        ['hosted', 'A commercial hosted product or SaaS'],
        ['client', 'Bespoke work for a client'],
        ['distribution', 'Customer installations, reusable kits, OEM or resale'],
        ['evaluation', 'Private evaluation before a product launch'],
        ['mixed', 'More than one use, or I’m not sure yet'],
    ]);

    page('page:scope', 0, 'License scope', 'See my starting point');
    add('heading:scope', 'HEADING_2', { html: 'A little more about your project' });
    radio('client_use', 'How will your client use the application?', [
        ['internal', 'Only for the client’s own internal operations'],
        ['hosted', 'As a commercial hosted product / SaaS'],
        ['distribution', 'Delivering software or installations to its customers'],
        ['unknown', 'Not confirmed yet'],
    ], true);
    radio('direct_client', 'Is this genuine bespoke work with the client obtaining the official package directly under its own rights?', [
        ['yes', 'Yes — I work as the client’s authorized contractor'],
        ['unsure', 'No, or I need help confirming this'],
    ], true);
    radio('free_scope', 'Is this solely personal, educational, research or internal business use, rather than a software service supplied to others?', [
        ['yes', 'Yes — the application is only for that qualifying use'],
        ['unsure', 'No, or I’m not sure'],
    ], true);
    radio('private_evaluation', 'Will evaluation remain private and outside production, with no live customer operations or paid pilots?', [
        ['yes', 'Yes — this is private, non-production evaluation'],
        ['unsure', 'No, or I’m not sure'],
    ], true);
    radio('hosted_scope', 'Will customers receive only login or API access to a product owned and hosted by its licensee?', [
        ['yes', 'Yes — no customer-controlled installation, source delivery or independent resale'],
        ['unsure', 'No, or I need help confirming the delivery model'],
    ], true);
    radio('ownership', 'Who owns and operates the hosted products?', [
        ['one', 'One person or one legal entity'],
        ['group', 'A parent and subsidiaries it owns 100%, including voting rights'],
        ['other', 'Other or separate owners — including unrelated clients'],
    ], true);
    radio('products', 'How many independently offered hosted products need coverage?', [
        ['one', 'One product'], ['two', 'Two independent products'], ['portfolio', 'Three or more, or a growing portfolio'],
    ], true);
    const customNote = text('scope:custom', 'Customer installations, reusable kits and independent reseller or OEM rights need an individually agreed Custom scope. Describe your delivery model on the next page.', true);
    const mixedNote = text('scope:mixed', 'Different projects may need different coverage. Describe each intended use and its owner on the next page so we can review them separately.', true);
    const hosted = or(condition('use', 'hosted'), and(condition('use', 'client'), condition('client_use', 'hosted')));
    const client = condition('use', 'client');
    const freeUse = or(condition('use', 'personal'), condition('use', 'internal'), and(client, condition('client_use', 'internal')));
    const custom = or(condition('use', 'distribution'), and(client, condition('client_use', 'distribution')));
    visibility('show:client', client, questions.client_use.blocks);
    visibility('show:direct-client', and(client, or(condition('client_use', 'internal'), condition('client_use', 'hosted'))), questions.direct_client.blocks);
    visibility('show:free', freeUse, questions.free_scope.blocks);
    visibility('show:evaluation', condition('use', 'evaluation'), questions.private_evaluation.blocks);
    for (const key of ['hosted_scope', 'ownership', 'products']) visibility(`show:${key}`, hosted, questions[key].blocks);
    visibility('show:custom', custom, [customNote.uuid]);
    visibility('show:mixed', condition('use', 'mixed'), [mixedNote.uuid]);

    page('page:contact', 1, 'Your recommendation and contact details', 'Send license request');
    add('heading:recommendation', 'HEADING_2', { html: 'Your starting point' });
    const validHosted = and(hosted, condition('hosted_scope', 'yes'), or(condition('use', 'hosted'), condition('direct_client', 'yes')));
    const free = or(
        and(or(condition('use', 'personal'), condition('use', 'internal')), condition('free_scope', 'yes')),
        and(condition('use', 'evaluation'), condition('private_evaluation', 'yes')),
        and(client, condition('client_use', 'internal'), condition('direct_client', 'yes'), condition('free_scope', 'yes')),
    );
    const outcomes = [
        ['free', free, '<strong>May qualify for free use</strong><br>No commercial purchase is required if the relevant free-use conditions hold. Tell us about your project if you would like to confirm the scope.'],
        ['single', and(validHosted, condition('ownership', 'one'), condition('products', 'one')), '<strong>Single Product · $249</strong><br>For one named hosted product and one licensee. One-time purchase; unlimited users, developers and environments within scope.'],
        ['two', and(validHosted, condition('ownership', 'one'), condition('products', 'two')), '<strong>Two Single Product licenses · $498</strong><br>At current prices, two Singles cost less. Unlimited Company ($699) is an alternative if you want coverage for a growing portfolio owned by that same entity.'],
        ['unlimited', and(validHosted, condition('ownership', 'one'), condition('products', 'portfolio')), '<strong>Unlimited Company · $699</strong><br>For current and future eligible hosted products owned and operated by one named entity. Separate Single Product licenses remain an option.'],
        ['enterprise', and(validHosted, condition('ownership', 'group')), '<strong>Enterprise Group · $1,499</strong><br>For a named parent and its qualifying 100%-owned subsidiaries. Separate adequate licenses can also be an alternative. This plan does not add OEM or distribution rights.'],
        ['separate', and(validHosted, condition('ownership', 'other')), '<strong>Separate licenses or an agreed arrangement</strong><br>Unrelated clients, partly owned companies and other separate owners do not automatically share one license. Describe the ownership model so we can help.'],
        ['custom', custom, '<strong>Custom / OEM · individually quoted</strong><br>Quotes start at $2,999 for a narrowly defined scope. Pricing, delivery rights and terms depend on your project; this is not a fixed price or unlimited distribution grant.'],
    ];
    outcomes.push(['review', inverse(or(...outcomes.map(([, match]) => match))), '<strong>Let’s confirm your scope</strong><br>Your answers need an individual review. Describe the intended uses, owners and delivery model below.']);
    for (const [key, match, html] of outcomes) {
        const result = text(`result:${key}`, html, true);
        visibility(`show:result:${key}`, match, [result.uuid]);
    }
    text('recommendation:note', 'This is a guide to License v2.0, not a license grant. Prices are in USD before tax. Existing licenses retain their agreed terms. We’ll confirm scope and price before any payment.');
    add('heading:contact', 'HEADING_2', { html: 'Where should we send your reply?' });
    input('contact_name', 'Your name', 'INPUT_TEXT', true, 'Full name');
    input('contact_email', 'Email address', 'INPUT_EMAIL', true, 'you@company.com');
    input('licensee', 'Person or company that will hold the license', 'INPUT_TEXT', true, 'Your legal name, or your client’s legal name');
    input('country', 'Billing country', 'INPUT_TEXT', true, 'Country');
    input('project_url', 'Project website — optional', 'INPUT_LINK', false, 'https://');
    input('project_details', 'Tell us about the project and what you need', 'TEXTAREA', true, 'What does it do? Who owns it? How will customers access it? Include any other intended uses.');
    input('existing_license', 'Existing license reference — optional', 'INPUT_TEXT', false, 'For an upgrade, transfer or scope question');
    text('privacy', 'We’ll use these details to reply to your licensing enquiry. The form is hosted by Tally. Sending it does not place an order or commit you to a purchase.');

    return { status: 'PUBLISHED', blocks, settings: {
        language: 'en', hasProgressBar: true, hasSelfEmailNotifications: true,
        hasRespondentEmailNotifications: false, hasPartialSubmissions: false,
        pageAutoJump: false, saveForLater: false,
        styles: { theme: 'CUSTOM', color: {
            background: '#ffffff', text: '#242136', accent: '#5b49d6', buttonBackground: '#5b49d6', buttonText: '#ffffff',
        }, direction: 'ltr' },
    } };
}

if (process.argv[2] === '--write' && process.argv[3]) {
    writeFileSync(process.argv[3], `${JSON.stringify(buildLicenseRequestForm(), null, 2)}\n`);
}
