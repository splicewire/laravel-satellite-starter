<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Resource discovery
    |--------------------------------------------------------------------------
    | Beam's boot-time resource discovery scans these paths for Data classes
    | carrying `#[ParticleResource]` and reflects each into the admin manifest —
    | no per-resource `registerClass()` in a provider. Dropping ONE annotated
    | Data class under a scanned path IS the wiring: it shows up in Frame's
    | `/frame/manifest` and gets its editor route with no further code.
    |
    | `resources` is the explicit class-string list (always honoured, cheap);
    | `discover_paths` is the filesystem scan (dev convenience). The unified
    | `#[ParticleResource]` attribute carries the full manifest field set
    | (label / model / nav placement / route identity), so the Data class is the
    | single source of the declaration.
    */

    'resources' => [],

    'discover_paths' => [
        app_path('Data'),
    ],

];
