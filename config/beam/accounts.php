<?php

/*
|--------------------------------------------------------------------------
| beam-accounts host overrides (merged over the package defaults)
|--------------------------------------------------------------------------
|
| This starter owns its committed auth schema: UUID users, password resets,
| sessions, passkeys, permissions and personal access tokens. App\Models\User
| uses HasRoles, and DatabaseSeeder provisions demo team roles and realm grants.
|
| The package's full auth migration estate also includes tenant userables,
| guest tokens and sign-offs. This starter does not adopt those tenant tables.
| Keep the host-owned schema without publishing the package's full estate.
*/

return [
    // 'absent' excludes the full package estate; false would claim EVERY member is committed here.
    // BeamAccountsServiceProvider::estateDeclaredAbsent() honours this explicit host choice.
    // PublishGateCoverageAudit still reports overlapping stems with our committed auth tables as
    // advisory evidence. It does not require the unadopted tenant tables to silence that warning.
    'publish_auth_migrations' => 'absent',
    // Teams publishing stays enabled for the package's teams/memberships/invitations estate,
    // which the demo subjects and their team roles use.
    // Committed members live in migrations/shared/, matching the publisher destination;
    // a copy at the migration root would be missed and published a second time.
    'register_migrations' => true,

    // Entitlement bundles (Frame OS ADR-0013 §3). The DefaultEntitlementResolver grants a STAFF principal
    // the `staff` bundle below — the operator/OS/authoring capabilities — so /operator + /os resolve OOTB
    // for the seeded staff user. Staff is NOT a flag: there is no `is_staff` column any more (retired,
    // particle-identity-resources ticket 04); the resolver derives it from the realm-grant cascade
    // (ACC-01) — a Team's `manage` grant on a realm's root. Declared in full because mergeConfigFrom is
    // shallow at this level (must restate default_staff_bundle / staff_roles alongside `bundles`). Keep
    // the keys in sync with config/app.php `entitlements` and config/beam/core.php `realm_gates`.
    'entitlements' => [
        'bundles' => [
            'staff' => ['ux.author', 'os.enter', 'os.operate'],
        ],
        'default_staff_bundle' => 'staff',
        'staff_roles' => ['staff', 'operator'],
    ],
];
