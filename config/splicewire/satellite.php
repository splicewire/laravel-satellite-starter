<?php

use Rushing\Marquee\Middleware\EnforceSiteMode;

return [
    /*
    |--------------------------------------------------------------------------
    | Migrations — PUBLISH-ONLY
    |--------------------------------------------------------------------------
    |
    | The satellite migrations (webhook_receipts + the Scaffold Packs central
    | chain that came DOWN with the satellite relocation, recohere C21/T12) are
    | PUBLISH-ONLY .stub files: the package publishes them into the host via
    | spatie/laravel-package-tools' `->hasMigrations([...])` under the
    | `satellite-migrations` tag (`vendor:publish --tag=satellite-migrations`),
    | re-stamped to the install moment; the host's `migrate` pass runs the
    | copies. No `register_migrations` knob — a host that vendors these tables
    | elsewhere simply doesn't publish the tag.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default frozen status
    |--------------------------------------------------------------------------
    |
    | The status stamped on a record the moment it is frozen by
    | PersistsGeneratedComposition::freeze(). A satellite may override per call.
    |
    */
    'frozen_status' => 'generated',

    /*
    |--------------------------------------------------------------------------
    | Metered-capability pre-flight enforcement (Frame OS ticket 10, ADR-0013 §2/§5)
    |--------------------------------------------------------------------------
    |
    | The SECOND enforcement point in the entitlement spine: before the satellite
    | makes a metered external-capability call to the splicewire-remote (the point
    | that would bill), the connector-tier MeteredCapabilityGuard checks the acting
    | tenant/credential principal HOLDS the required entitlement (kernel
    | EntitlementResolver) AND its credential scope permits it (CredentialScope) —
    | the SAME entitlement vocabulary the host plane gates on (ticket 08), so the
    | two points agree by construction.
    |
    | OFF by default. The null kernel resolver reports the empty set (holds nothing),
    | which would block EVERY metered call for an unconfigured satellite — so a
    | satellite with no entitlement wiring stays byte-for-byte functional until it
    | binds an EntitlementResolver over its tenant/plan model and flips this on.
    |
    */
    'enforce_entitlements' => (bool) env('SPLICEWIRE_SATELLITE_ENFORCE_ENTITLEMENTS', false),

    /*
    |--------------------------------------------------------------------------
    | Realm overlays (Frame OS ticket 14, ADR-0014 §A2)
    |--------------------------------------------------------------------------
    |
    | A satellite ENRICHES an EXISTING realm — it never owns a realm or a tile. Each
    | declaration here registers an ADDITIVE overlay onto beam's RealmOverlayRegistry,
    | which the RealmManifestProjector folds into the target realm's descriptor
    | PHP-side, BEFORE the manifest emits (so the JS frame stays satellite-agnostic —
    | it sees only realms, some enriched). Enrichment reuses the laravel-data-schemas
    | overlay primitive; capabilities entitlement-gated here SOFT-LOCK (present +
    | upsell) for a principal that does not hold them.
    |
    | Each entry:
    |   [
    |     'realm'        => 'site',            // the EXISTING realm key to enrich
    |     'overlay'      => [ 'overlay' => '1.0.0', 'actions' => [
    |                          ['target' => '$.badge', 'override' => '…'],
    |                        ]],               // a data-schema overlay document
    |     'capabilities' => [
    |       ['key' => 'music.render.voice', 'entitlement' => 'go-songwriter',
    |        'upsell' => ['title' => 'Go Songwriter', 'cta' => 'Upgrade']],
    |     ],
    |     'satellite'    => 'audiostud',       // provenance for diagnostics only —
    |                                          // NOT projected (no identity leaks)
    |   ]
    |
    | EMPTY by default ⇒ INERT: an unconfigured satellite registers nothing and the
    | manifest is byte-for-byte. A satellite whose target realm is not registered is a
    | no-op — an overlay can never conjure a realm/tile. A satellite may also register
    | imperatively from its own provider via SatelliteRealmOverlay::register().
    |
    */
    'realm_overlays' => [],

    /*
    |--------------------------------------------------------------------------
    | Brokered-tenant provisioning (ADR-0043)
    |--------------------------------------------------------------------------
    |
    | Used by ProvisionsCustomerTenants when a satellite provisions a Brokered
    | Tenant for one of its customers. `provision_status` is the status stamped on
    | the recorded row; the poll settings bound the synchronous create→active wait
    | when the satellite polls instead of awaiting the status webhook.
    |
    */
    'provision_status' => 'active',
    'provision_poll_attempts' => 30,
    'provision_poll_interval' => 2,

    /*
    |--------------------------------------------------------------------------
    | Platform connection (the pairing surface)
    |--------------------------------------------------------------------------
    |
    | Where `Route::splicewirePlatformConnectionRoutes()` mounts the four pairing
    | operations, and what stands in front of them. Both are facts about an
    | exposure that mints and stores a bearer credential, so a host may override
    | them — but the defaults are not "open": `can:entitlement:os.operate` is the
    | same entitlement the operations themselves declare, so the door and the
    | operation agree and a direct POST from a member is refused rather than
    | merely hidden from the navigation rail.
    |
    | ⚠️ Narrowing this middleware is safe; emptying it is not. The ops keep
    | their own `ability:` check, but `web` is what gives them a session to read
    | an actor from at all.
    |
    | WHERE it pairs TO is not configured here — that is the connector's
    | `splicewire.connector.device_flow.central_url` / `.base_url` pair, read
    | through `Pairing\PlatformEndpoints`, so the screen and
    | `php artisan splicewire:connect` resolve one platform from one place.
    |
    */
    'platform_connection' => [
        'prefix' => 'operator',
        'middleware' => ['web', 'auth', 'verified', 'can:entitlement:os.operate'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Marquee site-mode gate (rushing/laravel-marquee)
    |--------------------------------------------------------------------------
    |
    | The normalized launch mechanism for every satellite: deploy dark to
    | production in `soon`, then `php artisan marquee:mode live` to launch with
    | no redeploy. When auto_register is on, the base package inserts the gate
    | middleware into the `web` group after the framework's core web middleware
    | (session/auth run first so the staff bypass resolves) and ahead of any
    | app render middleware (Inertia). `splicewire:satellite:doctor` verifies the
    | gate is actually enforced. A satellite wanting explicit ordering can turn
    | this off and register the middleware itself in bootstrap/app.php instead.
    |
    */
    'marquee' => [
        'auto_register' => true,
        'middleware' => EnforceSiteMode::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sitemap + robots (SEO, gate-aware)
    |--------------------------------------------------------------------------
    |
    | The sitemap seam relocated DOWN to `laravel-beam-sitemap` (ADR-0166): its
    | config lives under `beam.sitemap.*` (merged by the arm) — `enabled`, `path`,
    | `cache_ttl`, `route_name_patterns`. The satellite still owns the
    | Marquee-gated /sitemap.xml + /robots.txt ROUTES (routes/sitemap.php), which
    | read `beam.sitemap.path`. No sitemap keys live here anymore.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Engine grounding key (deterministic, internal-trust)
    |--------------------------------------------------------------------------
    |
    | The reproducible beam-accounts key the Splicewire engine presents when it
    | PULLS grounding back from THIS satellite's own REST endpoint during
    | composition (ADR-0076 pull convention / ADR-0093 loopback / satellite-seed-
    | convention). `splicewire:satellite:mint-engine-key` mints it idempotently;
    | `DeterministicToken::bearer()` is a pure function of (id, plaintext), so the
    | same values yield the same bearer on every host and survive a migrate:fresh
    | — the credential is re-minted, never handed out. The printed bearer is set on
    | the engine as the satellite's grounding token.
    |
    | A satellite OPTS IN by filling this in (`enabled` + a `tokenable.model` and a
    | `where` that identifies the internal service row): the abilities are the
    | satellite's own tool abilities (e.g. `<vendor>.explain-number.private`, the
    | internal-trust ability that unredacts its licensed corpus). Off by default —
    | a satellite with no grounding pull needs no key.
    |
    */
    'engine_key' => [
        'enabled' => (bool) env('SATELLITE_ENGINE_KEY_ENABLED', false),
        // NOT cast to int: on a uuid-keyed `personal_access_tokens` this pins a uuid, and the id is a
        // wire-format field — a Sanctum bearer IS `{id}|{plaintext}` and findToken() looks the row up
        // by key. The bigint default stays for a stock-Sanctum host (beam-docs-satellite ticket 25).
        'id' => env('SATELLITE_ENGINE_KEY_ID', 900000001),
        'plaintext' => env('SATELLITE_ENGINE_KEY_PLAINTEXT', 'satellite-engine-grounding-key'),
        'name' => env('SATELLITE_ENGINE_KEY_NAME', 'splicewire-engine-grounding'),
        'abilities' => [],
        // The service identity the key is tokenable to. `model` is the host's
        // Sanctum tokenable (its User); `where` uniquely identifies (and, on first
        // mint, firstOrCreate's) the row; `attributes` seed it on create (a random
        // password is injected when the model has one and none is given — the row
        // never logs in).
        'tokenable' => [
            'model' => null,
            'where' => [],
            'attributes' => [],
        ],
    ],
];
