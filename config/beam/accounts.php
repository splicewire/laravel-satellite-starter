<?php

/*
|--------------------------------------------------------------------------
| beam-accounts host overrides (merged over the package defaults)
|--------------------------------------------------------------------------
|
| OOTB-wiring note (frontend-surfaces): this starter is a `laravel new`
| derivative that already ships its OWN bigint-keyed `users` /
| `password_reset_tokens` / `sessions` migrations. `laravel-beam-accounts`
| ALSO registers a full auth-migration estate that includes a *uuid-keyed*
| `create_users_table` (the "C2 auth estate"), which collides with the
| host's own users table on migrate.
|
| Until the host adopts the package's uuid-user identity wholesale, we keep
| the host's own auth tables and OPT OUT of the package's auth-migration
| estate. The front-end realm surfaces (site / account chrome) do not depend
| on the uuid-user estate; they need the beam-ux entry/sitemap substrate and
| a bound AccountShellProvider, both of which stand on their own.
*/

return [
    // Auth estate OFF — this laravel-starter-kit app owns its OWN auth schema (bigint users, its own
    // passkeys table, no spatie roles). The beam-accounts auth estate (uuid users + its passkeys + a
    // uuid-keyed spatie roles table) conflicts with it, so adopting it is a deeper migration than a
    // starter warrants. Consequence: the OOTB DemoTeamSeeder seeds its demo USERS (enough for the
    // login-as buttons) but its team-ROLE assignment is skipped (see DatabaseSeeder).
    'register_auth_migrations' => false,
    // Teams estate ON — the engine's teams/memberships/invitations tables, needed by the OOTB
    // beam-accounts demo team (login-as subjects) the login page's quick-login buttons enter.
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
