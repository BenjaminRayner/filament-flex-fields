<?php

declare(strict_types=1);

return [
    'title' => 'License guide',
    'license_label' => 'Licensing',
    'scope_internal' => 'Personal & internal use · Free',
    'scope_hosted' => 'Commercial SaaS · Standard',
    'scope_custom' => 'Distribution · Custom',
    'notice_title' => 'The right license for your project.',
    'notice' => 'Personal use and qualifying internal tools are free. Commercial SaaS needs a Standard license; customer installations, reusable kits and OEM need agreed Custom rights.',
    'check_scope' => 'Find your license',
    'intro' => 'Choose how you plan to use FLEX Fields. We’ll help you identify the scope described in LICENSE v2.0.',
    'selection_hint' => 'Select every intended use and confirm its conditions. We assess each use separately, including your products and a client’s products. Product count follows independent product identities, not users, domains or servers.',
    'assess' => 'Check my use',
    'assessing' => 'Checking…',
    'result' => 'Your scope guide',
    'contact' => 'Discuss scope & purchase',
    'plans' => 'Plans & purchasing',
    'client_note' => 'For bespoke client-owned applications, the license belongs to the client. An agency’s portfolio license does not cover its clients.',
    'terms_note' => 'This guide summarizes LICENSE v2.0 and COMMERCIAL.md; it does not issue or activate a license. The full terms, your License Confirmation and any agreed Custom scope govern. USD prices exclude applicable tax; scope is confirmed before payment.',
    'existing_note' => 'Already licensed? Existing grants retain their agreed version and scope. This guide does not determine whether a prior or Custom grant covers your use.',
    'breakdown' => 'Assessment by use',
    'mixed_note' => 'Each result applies only to the use shown beside it. A Custom requirement for one project does not replace or expand the rights needed for another.',
    'client_use' => [
        'internal' => [
            'label' => 'Client’s internal operations',
            'description' => 'A bespoke application for that client’s own operations, not a software product it supplies to others.',
        ],
        'hosted' => [
            'label' => 'Client’s commercial hosted product',
            'description' => 'The client owns its SaaS and customers receive runtime access only. The client needs its own suitable license.',
        ],
        'distribution' => [
            'label' => 'Client distributes software or customer installations',
            'description' => 'The client supplies package-containing copies, reusable kits, installations or independent OEM rights to its own customers.',
        ],
    ],
    'questions' => [
        'client_use' => 'How will the client use its application?',
        'client_ownership' => 'Who owns and operates the client’s hosted products?',
        'client_products' => 'How many hosted products does the client own?',
        'uses' => 'What will you use FLEX for?',
        'conditions' => 'Confirm the relevant conditions',
        'ownership' => 'Who owns and operates your hosted products?',
        'products' => 'How many hosted products do you own?',
    ],
    'uses' => [
        'personal' => [
            'label' => 'Personal, educational or research use',
            'description' => 'A private project, learning or research, rather than operating a commercial software product.',
        ],
        'internal' => [
            'label' => 'My own business operations',
            'description' => 'Your staff CRM, shop, bookings or business administration. A back office qualifies only when it administers the business independently of a software service you sell.',
        ],
        'evaluation' => [
            'label' => 'Private development & evaluation',
            'description' => 'Testing a prospective product before live customer operations, paid pilots or production.',
        ],
        'bespoke_runtime' => [
            'label' => 'Bespoke internal tool operated for a client',
            'description' => 'A documented work-for-hire engagement; you operate it and the client receives login/API access only.',
        ],
        'bespoke_client' => [
            'label' => 'Bespoke application owned by the client',
            'description' => 'The client obtains the official package under its own rights; you work as its authorized contractor.',
        ],
        'hosted' => [
            'label' => 'Commercial hosted product / SaaS',
            'description' => 'A monetized or freemium software service. A staff-only screen that configures that service also belongs to this scope.',
        ],
        'distribution' => [
            'label' => 'Customer installations or package/source delivery',
            'description' => 'Your own product on a customer’s server/cloud, containers or package-containing source. Excludes the genuine bespoke direct-client route above.',
        ],
        'kits' => [
            'label' => 'Reusable starter, template or developer kit',
            'description' => 'A reusable product built around FLEX Fields, even if buyers install the official dependency themselves.',
        ],
        'oem' => [
            'label' => 'OEM, sublicensing or independent resale',
            'description' => 'Another business can install, independently operate, redistribute or sublicense your Software-based product.',
        ],
    ],
    'conditions' => [
        'free_scope' => [
            'label' => 'Personal/internal use meets §1.7(a)',
            'description' => 'Not supplying the application itself as a commercial software product; retain notices and stay within the grant.',
        ],
        'evaluation_private' => [
            'label' => 'Evaluation is private and non-production',
            'description' => 'No live customer operations, paid pilot or production launch under the evaluation exception.',
        ],
        'bespoke_runtime_scope' => [
            'label' => 'The runtime-only engagement meets §1.7(b)',
            'description' => 'Identified client and written bespoke scope; you remain responsible operator; internal use, no client software resale or package delivery.',
        ],
        'direct_client' => [
            'label' => 'The direct-client route meets §5.6',
            'description' => 'Genuine bespoke work; client obtains the official dependency directly and authorizes you. You do not supply your own package copy/private fork or a reusable product.',
        ],
        'client_free_scope' => [
            'label' => 'The client independently qualifies for free internal use',
            'description' => 'The client meets §1.7(a) in its own right. It uses the application for its operations, rather than supplying a software service to others.',
        ],
        'hosted_scope' => [
            'label' => 'Commercial customers receive runtime access only',
            'description' => 'For every selected hosted use, the product’s licensee owns and operates it, directly or through authorized personnel. Customers get runtime access only, with no customer-controlled installation, package/source delivery or independent reseller rights.',
        ],
    ],
    'ownership' => [
        'one' => [
            'label' => 'One person or legal entity',
            'description' => 'Includes departments and brands of that same entity; excludes separately incorporated companies.',
        ],
        'group' => [
            'label' => 'Parent + wholly owned subsidiaries',
            'description' => '100% ownership interests and voting rights through the named parent’s qualifying chain.',
        ],
        'other' => [
            'label' => 'Other owners/operators',
            'description' => 'Partially owned companies, sister companies, joint ventures or unrelated clients; assess their grants separately.',
        ],
    ],
    'products' => [
        'one' => [
            'label' => 'One product',
            'description' => 'Versions, languages, regions and tenant instances of the same product are included.',
        ],
        'two' => [
            'label' => 'Two independent products',
            'description' => 'Separate product identities, rather than modules or domains of the same application.',
        ],
        'portfolio' => [
            'label' => 'Three or more / future portfolio',
            'description' => 'Multiple independently offered products owned and operated by the licensee.',
        ],
    ],
    'outcomes' => [
        'mixed' => [
            'title' => 'Different uses need different coverage',
            'detail' => 'There is no single recommendation covering every selected activity. Review each result below, including conditions still requiring confirmation.',
        ],
        'free' => [
            'title' => 'May qualify for free use',
            'detail' => 'No commercial purchase is required when every relevant free-use condition holds. Redistribution restrictions and notices still apply.',
        ],
        'single' => [
            'title' => 'Single Product · $249',
            'detail' => 'One named product and one licensee. One-time Standard grant, unlimited users, tenants, developers and environments within scope.',
        ],
        'two_single' => [
            'title' => 'Two Single Product grants · $498',
            'detail' => 'Two Singles cost less at current list prices. Unlimited Company ($699) is an alternative covering the same entity’s current and future portfolio.',
        ],
        'unlimited' => [
            'title' => 'Unlimited Company · $699',
            'detail' => 'One named entity’s eligible current and future hosted products. At three products it costs less than three Singles; separate Singles remain an option.',
        ],
        'enterprise' => [
            'title' => 'Enterprise Group · $1,499',
            'detail' => 'A common grant for the named parent and 100%-owned subsidiaries. Adequate separate grants can be an alternative; group scope does not include OEM or customer delivery.',
        ],
        'separate' => [
            'title' => 'Separate grants or negotiated coverage',
            'detail' => 'There is no automatic shared license for these entities. Appropriate Single/Unlimited grants per owner or expressly agreed coverage may fit; contact us with the ownership model.',
        ],
        'custom' => [
            'title' => 'Custom / OEM · individually quoted',
            'detail' => 'Quotations start at $2,999 for a narrowly specified scope, not unlimited rights or a fixed fee. Standard plans do not authorize this activity.',
        ],
        'review' => [
            'title' => 'Confirm the scope before proceeding',
            'detail' => 'A missing or ambiguous condition prevents a free-use or Standard recommendation. Contact the author with the ownership and delivery model.',
        ],
        'incomplete' => [
            'title' => 'A little more information is needed',
            'detail' => 'Select your intended use and the relevant ownership/product options.',
        ],
    ],
    'reasons' => [
        'mixed_uses' => 'Your selection combines activities with different licensing routes or unconfirmed conditions.',
        'combined_uses' => 'Every selected use has been assessed separately; the results below share the same licensing category.',
        'mixed_owners' => 'Your own hosted products and client-owned hosted products need separate adequate coverage. One agency grant does not cover both owners.',
        'client_distribution' => 'The client needs expressly agreed Custom rights for distribution to its own customers. The bespoke agency exception does not grant those rights.',
        'client_free_scope' => 'Confirm that the client independently meets every applicable internal-use condition.',
        'choose_use' => 'Choose at least one intended use.',
        'choose_products' => 'Choose the number of independent products.',
        'choose_ownership' => 'Identify the owner/operator.',
        'distribution' => 'Customer copies, reusable kits and independent OEM/reseller rights need expressly agreed distribution scope.',
        'direct_client' => 'Choose the client’s use and confirm the genuine bespoke direct-client conditions. An agency’s license cannot substitute for the client’s rights.',
        'hosted_scope' => 'Confirm product ownership, vendor-controlled hosting and runtime-only customer access. Customer-controlled delivery follows a different route.',
        'group' => 'The selected commercial products span a qualifying wholly owned corporate group.',
        'other_entities' => 'The selected owners/operators are outside one entity and the qualifying wholly owned group.',
        'one_product' => 'The selected use is a commercial hosted product owned/operated by one licensee.',
        'two_products' => 'You selected two independent hosted commercial products of the same licensee.',
        'portfolio' => 'You selected three or more products, or portfolio coverage for future products.',
        'evaluation' => 'Evaluation is free only before production, live customer operations and paid pilots.',
        'bespoke_runtime' => 'The documented engagement, internal use and runtime-only access conditions must all hold.',
        'free_scope' => 'Confirm that the application is for qualifying personal/internal operations, not a software product supplied to others.',
        'client_free' => 'A genuine bespoke client-owned internal application can qualify under the client’s own free-use entitlement.',
        'permitted_free' => 'Your selected use and confirmed conditions fit the summarized free-use routes.',
    ],
];
