<?php

return [
    /*
     | The host UI package dir, relative to the Laravel base path — the React+Vite estate the
     | @splicewire/beam-ux-prototype runtime is wired into. Every path below is resolved under this.
     */
    'ui_path' => 'ui',

    /*
     | The host UI package.json — carries the `@splicewire/beam-ux-prototype` dependency, the
     | `verify:prod-boundary` script, and the `prototype.outDir` key. Relative to the base path.
     */
    'package_json' => 'ui/package.json',

    /*
     | Where the `createPrototypeRoutes(import.meta.glob(...))` wiring lives. The audit greps this file
     | (and, if absent there, scans `ui/src` for the call) for the glob macro under an intact
     | `import.meta.env.DEV` guard — the load-bearing seam the runtime cannot own.
     */
    'router' => 'ui/src/app/router.tsx',

    /*
     | The host stylesheet that must define the PrototypeDesk CSS token contract in `:root`.
     */
    'tokens_css' => 'ui/src/index.css',

    /*
     | The prototype root the InstallStep stamps its starter + nav.ts stubs into.
     */
    'prototype_dir' => 'ui/src/_prototype',

    /*
     | The host brand lockup the `_chrome` desk wrapper injects — filled into the published
     | host-bound convention instance so a new host knows which component to wire.
     */
    'brand_import' => '@/components/brand/BrandLockup',

    /*
     | The PrototypeDesk token contract (README source of truth). The audit fails if any is missing
     | from the host `:root`; `dotted-bg` is a utility that reads `--dotted-dot`.
     */
    'required_tokens' => [
        '--sidebar-deep',
        '--sidebar-foreground',
        '--sidebar-active-foreground',
        '--sidebar-accent',
        '--sidebar-primary',
        '--sidebar-avatar',
        '--dotted-dot',
    ],

    /*
     | The npm script the boundary build shells out to (on-demand `--boundary` check only).
     */
    'boundary_command' => 'npm run verify:prod-boundary',
];
