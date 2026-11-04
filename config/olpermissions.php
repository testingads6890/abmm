<?php

/**
 * Role & permission catalogue.
 * Permission key = "<module>.<action>". Super Admin implicitly has everything (Gate::before).
 * A user can only grant permissions that he/she holds himself (see User::grantablePermissions()).
 */
return [
    'roles' => [
        'member'            => ['label' => 'App Member',        'rank' => 0],
        'super_admin'       => ['label' => 'Super Admin',       'rank' => 3],
        'state_admin'       => ['label' => 'State Sub-Admin',   'rank' => 2],
        'district_operator' => ['label' => 'District Admin', 'rank' => 1],
        // Global hierarchy (Milestone A): bound to Country / City / Zone instead of Pradesh.
        'country_admin'     => ['label' => 'Country Admin',     'rank' => 2],
        'city_admin'        => ['label' => 'City Admin',        'rank' => 1],
        'zone_coordinator'  => ['label' => 'Zone Admin',  'rank' => 1],
    ],

    // Roles that are bound to country/city/zone rather than pradesh/district/tehsil.
    'global_roles' => ['country_admin', 'city_admin', 'zone_coordinator'],

    // Which role may create which role (hierarchy).
    'can_create' => [
        'super_admin'       => ['country_admin', 'state_admin', 'district_operator', 'city_admin', 'zone_coordinator'],
        'state_admin'       => ['district_operator'],
        'district_operator' => ['city_admin'],
        'country_admin'     => ['city_admin', 'zone_coordinator'],
        'city_admin'        => ['zone_coordinator'],
        'zone_coordinator'  => [],
    ],

    // Which data is locked to the user's Pradesh/District/Tehsil. Observed on the real site: a State Sub-Admin sees only own-area
    // families/members, but matrimony and committee lists are national. Flip to true to lock them too (records need a pradesh then).
    'area_scoped' => ['Family' => true, 'Member' => true, 'CommitteeMember' => false, 'MatrimonyProfile' => false, 'Business' => false],

    'modules' => [
        'families'      => ['label' => 'Families & Members', 'actions' => ['view', 'create', 'edit', 'archive', 'delete', 'export', 'print', 'import']],
        'committee'     => ['label' => 'Committee',          'actions' => ['view', 'create', 'edit', 'delete', 'import']],
        'matrimony'     => ['label' => 'Matrimony',          'actions' => ['view', 'create', 'edit', 'status']],
        'business'      => ['label' => 'Business Directory', 'actions' => ['view', 'create', 'edit', 'status', 'ads']],
        'notifications' => ['label' => 'Push Notifications', 'actions' => ['view', 'send', 'delete']],
        'events'        => ['label' => 'Events',             'actions' => ['view', 'create', 'delete']],
        'donations'     => ['label' => 'Donations',          'actions' => ['view', 'status', 'export']],
        'reports'       => ['label' => 'Reports',            'actions' => ['view', 'export']],
        'import'        => ['label' => 'Data Import',        'actions' => ['run']],
        'users'         => ['label' => 'Team / Sub-Admins',  'actions' => ['view', 'create', 'edit', 'toggle', 'reset_password']],
        'masters'       => ['label' => 'Master Data (dropdowns)', 'actions' => ['view', 'create', 'edit', 'delete']],
        'trusts'        => ['label' => 'Trusts & Department Submission', 'actions' => ['view', 'create', 'edit', 'delete', 'export', 'submit']],
        'customfields'  => ['label' => 'Custom Form Fields', 'actions' => ['view', 'create', 'edit', 'delete']],
        'appusers'      => ['label' => 'Mobile App Registrations (state-wise)', 'actions' => ['view', 'export']],
        'engagement'    => ['label' => 'Engagement (greetings, daily message, festivals)', 'actions' => ['view', 'create', 'edit', 'delete']],
        'feed'          => ['label' => 'Achievements & News (community feed)', 'actions' => ['view', 'create', 'edit', 'delete', 'approve']],
        'help'          => ['label' => 'Help & Support requests', 'actions' => ['view', 'edit', 'delete']],
        'directory'     => ['label' => 'Samaj Directory (stats & switch)', 'actions' => ['view', 'edit']],
        'jobs'          => ['label' => 'Jobs board (moderation)', 'actions' => ['view', 'edit', 'delete']],
        'downloads'     => ['label' => 'App Downloads list', 'actions' => ['view', 'create', 'edit', 'delete']],
        'settings'      => ['label' => 'Site Settings',      'actions' => ['manage']],
        // ---- Global platform (Milestone A) ----
        'geography'     => ['label' => 'Global Geography (countries, zones)', 'actions' => ['view', 'create', 'edit', 'delete']],
        'plans'         => ['label' => 'Plans, Coupons & Subscriptions', 'actions' => ['view', 'create', 'edit', 'delete']],
        'payments'      => ['label' => 'Payments, Refunds & Receipts', 'actions' => ['view', 'refund', 'reconcile', 'export']],
        'forms'         => ['label' => 'Online Forms (builder & approvals)', 'actions' => ['view', 'create', 'edit', 'delete', 'approve']],
        'verification'  => ['label' => 'Member Verification & Membership ID', 'actions' => ['view', 'verify']],
        'messaging'     => ['label' => 'WhatsApp / SMS / Email messaging', 'actions' => ['view', 'send', 'configure']],
        'revenue'       => ['label' => 'Revenue dashboard', 'actions' => ['view', 'export']],
        // ---- Engagement (Milestone B) ----
        'polls'         => ['label' => 'Polls & Surveys', 'actions' => ['view', 'create', 'edit', 'delete']],
        'rsvp'          => ['label' => 'Event RSVP, QR pass & attendance', 'actions' => ['view', 'edit', 'scan']],
        'points'        => ['label' => 'Points, badges, rewards & certificates', 'actions' => ['view', 'edit', 'redeem']],
        'corrections'   => ['label' => 'Data correction requests', 'actions' => ['view', 'decide']],
        // ---- Community & commerce (Milestone C) ----
        'community'     => ['label' => 'Member card, benefits, networking moderation', 'actions' => ['view', 'create', 'edit', 'delete', 'redeem', 'moderate']],
        'directories'   => ['label' => 'Directories (bhavan, hotel, hospital, clinic, doctor)', 'actions' => ['view', 'create', 'edit', 'delete', 'verify']],
        'adsmgr'        => ['label' => 'Ad packages & paid ads', 'actions' => ['view', 'create', 'edit', 'delete', 'approve']],
        'marketplace'   => ['label' => 'Business categories, B2B marketplace, groups', 'actions' => ['view', 'create', 'edit', 'delete', 'verify']],
        'ticketing'     => ['label' => 'Event tickets & online events', 'actions' => ['view', 'create', 'edit', 'delete', 'scan', 'refund']],
        'vendors'       => ['label' => 'Event vendors', 'actions' => ['view', 'create', 'edit', 'verify']],
        'parichay'      => ['label' => 'Parichay safety (reports, fraud flags, blocklist)', 'actions' => ['view', 'remove']],
        'partners'      => ['label' => 'Partner dashboards (bureau / organiser / recruiter)', 'actions' => ['view', 'create', 'edit', 'verify']],
        // ---- Milestone E ----
        'careers'       => ['label' => 'Careers: job packs, placement drives, mentors, counselling', 'actions' => ['view', 'create', 'edit', 'delete', 'approve']],
        'blood'         => ['label' => 'Blood donors, requests & camps', 'actions' => ['view', 'create', 'edit', 'delete', 'approve']],
        'giving'        => ['label' => 'Donation campaigns, 80G, relief & volunteers', 'actions' => ['view', 'create', 'edit', 'delete', 'approve']],
        'invest'        => ['label' => 'Investments, pitches & showcase', 'actions' => ['view', 'create', 'edit', 'delete', 'approve']],
        'academy'       => ['label' => 'Learning Academy (courses, live classes, library)', 'actions' => ['view', 'create', 'edit', 'delete', 'approve']],
        'exams'         => ['label' => 'Weekly exams & question bank', 'actions' => ['view', 'create', 'edit', 'delete', 'approve']],
        'housie'        => ['label' => 'Housie & sponsors', 'actions' => ['view', 'create', 'edit', 'delete', 'approve']],
    ],

    // Starting permission set when a user of that role is created (can be edited by the creator).
    'defaults' => [
        // Mirrors the real State Sub-Admin menu seen on abmmsampark.org: Dashboard, Member, Committee upload, Reports,
        // Notification, My Meetings, Mahesh Vandana. No Admin tools at all (businesses, ads, events, donations, import, team, master data, settings).
        'state_admin' => [
            'users.view', 'users.create', 'users.edit', 'users.toggle', 'users.reset_password', 'geography.view', 'geography.create', 'geography.edit', 'geography.delete',
            'families.view', 'families.create', 'families.edit', 'families.archive', 'families.export', 'families.print', 'families.import',
            'committee.view', 'committee.create', 'committee.edit', 'committee.import',
            'matrimony.view', 'matrimony.edit', 'matrimony.status',
            'notifications.view', 'notifications.send',
            'reports.view', 'reports.export',
            'appusers.view', 'appusers.export',
            'engagement.view',
            'feed.view', 'feed.edit', 'feed.approve',
            'help.view', 'help.edit',
            'jobs.view', 'jobs.edit',
            'directory.view',
            'polls.view', 'polls.create', 'polls.edit', 'polls.delete', 'rsvp.view', 'rsvp.edit', 'rsvp.scan', 'points.view', 'corrections.view', 'corrections.decide',
            'community.view', 'community.edit', 'community.redeem', 'community.moderate', 'directories.view', 'directories.create', 'directories.edit', 'directories.verify', 'adsmgr.view', 'adsmgr.approve', 'marketplace.view', 'marketplace.verify', 'ticketing.view', 'ticketing.create', 'ticketing.edit', 'ticketing.scan', 'vendors.view', 'partners.view', 'vendors.edit', 'vendors.verify', 'partners.edit', 'partners.verify', 'parichay.view', 'parichay.remove', 'careers.view', 'careers.create', 'careers.edit', 'careers.delete', 'careers.approve', 'blood.view', 'blood.create', 'blood.edit', 'blood.delete', 'blood.approve', 'giving.view', 'giving.create', 'giving.edit', 'giving.delete', 'giving.approve', 'invest.view', 'invest.create', 'invest.edit', 'invest.delete', 'invest.approve', 'academy.view', 'academy.create', 'academy.edit', 'academy.delete', 'academy.approve', 'exams.view', 'exams.create', 'exams.edit', 'exams.delete', 'exams.approve', 'housie.view', 'housie.create', 'housie.edit', 'housie.delete', 'housie.approve',
        ],
        'district_operator' => [
            'users.view', 'users.create', 'users.edit', 'users.toggle', 'users.reset_password', 'geography.view', 'geography.create', 'geography.edit', 'geography.delete',
            'families.view', 'families.create', 'families.edit', 'families.print', 'families.import', 'families.export',
            'matrimony.view', 'matrimony.create', 'business.view', 'business.create', 'reports.view',
        ],
        'country_admin' => [
            'families.view', 'families.create', 'families.edit', 'families.archive', 'families.export', 'families.print', 'families.import',
            'committee.view', 'matrimony.view', 'matrimony.edit', 'matrimony.status', 'business.view',
            'notifications.view', 'notifications.send', 'reports.view', 'reports.export', 'appusers.view', 'appusers.export',
            'geography.view', 'geography.create', 'geography.edit', 'plans.view', 'payments.view', 'payments.export',
            'forms.view', 'forms.approve', 'verification.view', 'verification.verify', 'messaging.view', 'revenue.view', 'revenue.export',
            'users.view', 'users.create', 'users.edit', 'users.toggle', 'users.reset_password',
            'help.view', 'help.edit', 'jobs.view', 'jobs.edit', 'feed.view', 'feed.edit', 'feed.approve',
            'polls.view', 'polls.create', 'polls.edit', 'polls.delete', 'rsvp.view', 'rsvp.edit', 'rsvp.scan', 'points.view', 'points.edit', 'points.redeem', 'corrections.view', 'corrections.decide',
            'community.view', 'community.edit', 'community.redeem', 'community.moderate', 'directories.view', 'directories.create', 'directories.edit', 'directories.verify', 'adsmgr.view', 'adsmgr.approve', 'marketplace.view', 'marketplace.verify', 'ticketing.view', 'ticketing.create', 'ticketing.edit', 'ticketing.scan', 'vendors.view', 'partners.view', 'vendors.edit', 'vendors.verify', 'partners.edit', 'partners.verify', 'parichay.view', 'parichay.remove', 'careers.view', 'careers.create', 'careers.edit', 'careers.delete', 'careers.approve', 'blood.view', 'blood.create', 'blood.edit', 'blood.delete', 'blood.approve', 'giving.view', 'giving.create', 'giving.edit', 'giving.delete', 'giving.approve', 'invest.view', 'invest.create', 'invest.edit', 'invest.delete', 'invest.approve', 'academy.view', 'academy.create', 'academy.edit', 'academy.delete', 'academy.approve', 'exams.view', 'exams.create', 'exams.edit', 'exams.delete', 'exams.approve', 'housie.view', 'housie.create', 'housie.edit', 'housie.delete', 'housie.approve',
        ],
        'city_admin' => [
            'geography.create', 'geography.edit', 'geography.delete', 'users.reset_password',
            'families.view', 'families.create', 'families.edit', 'families.print', 'families.import', 'families.export', 'matrimony.view', 'reports.view',
            'notifications.view', 'notifications.send', 'geography.view', 'forms.view', 'forms.approve',
            'verification.view', 'verification.verify', 'payments.view', 'revenue.view',
            'users.view', 'users.create', 'users.edit', 'users.toggle',
            'help.view', 'help.edit', 'jobs.view', 'jobs.edit', 'feed.view', 'feed.approve',
            'polls.view', 'polls.create', 'polls.edit', 'rsvp.view', 'rsvp.edit', 'rsvp.scan', 'points.view', 'corrections.view', 'corrections.decide',
            'community.view', 'community.redeem', 'directories.view', 'marketplace.view', 'ticketing.view', 'ticketing.scan', 'vendors.view', 'partners.view', 'blood.view', 'blood.approve', 'careers.view', 'academy.view', 'exams.view', 'housie.view',
        ],
        'zone_coordinator' => [
            'families.view', 'families.create', 'families.edit', 'reports.view', 'notifications.view', 'notifications.send',
            'forms.view', 'forms.approve', 'verification.view', 'verification.verify',
            'rsvp.view', 'rsvp.scan', 'corrections.view', 'corrections.decide',
        ],
    ],
];
