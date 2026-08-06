# Laravel Satellite Starter

A **start-from-scratch ground** for a Splicewire **satellite** site — a consumer app that rides the
Splicewire platform over HTTP.

Forked from [`laravel-beam-starter`](https://github.com/splicewire/laravel-beam-starter) (Laravel 13 +
React/Inertia), it adds the **`splicewire/laravel-satellite`** backend stack: the generation pipeline,
frozen-composition model concern, attribute beat-grammar, schema-spine resolver, and the HTML lifecycle
runner over the `splicewire/client` connector.

## Tier ladder

```
beam  (free-tier foundation)  ⊂  satellite  (consumer-app backend stack)  ⊂  tower  (self-hosted host)
 laravel-beam-starter              laravel-satellite-starter                  laravel-tower-starter
```

Each starter forks the tier below it and layers on one package family plus that tier's topology
stop-gap.

## Co-dev overlay

Local package development is driven by a parked overlay. To work against your local checkouts in
`~/Workspaces/laravel/packages/**` (symlinked into `vendor/`):

```bash
cp composer.local.json.off composer.local.json
composer update
```

The overlay's `type: path` repositories win over the git `repositories` in `composer.json`
(via `wikimedia/composer-merge-plugin`). Delete `composer.local.json` to go back to git-resolved.

## Install (satellite tier — cascades to beam)

`composer setup` runs the satellite tier installer — **`splicewire:satellite:install`** — instead of a
bare `migrate`. The satellite installer **chains down** to `splicewire:beam:install` first (the base
tier), then publishes + migrates the satellite tier on top:

```bash
composer setup
# or, standalone, after key:generate:
php artisan splicewire:satellite:install --no-interaction --force
#   → INFO satellite tier: chaining splicewire:beam:install (base tier).
#   → beam stack installed.
#   → INFO satellite tier installed.
```

### Local-package run loop

```bash
cp composer.local.json.off composer.local.json   # engage the overlay (gitignored active copy)
composer update                                   # resolve to local checkouts
php artisan splicewire:satellite:install --no-interaction --force   # cascades satellite → beam

rm composer.local.json && composer update         # reset to git-resolved
```

## Exposing a model in the admin — drop one annotated Data class

A model shows up in the Frame admin by dropping **one** `#[ParticleResource]`-annotated Data class into
`app/Data/`. That is the whole declaration — no second declaration, no `registerClass()` in a provider,
no handler-map entry. Beam's boot-time attributed discovery scans `app/Data` (config
`frame.discover_paths`), reflects the attribute into the admin manifest, and the resource appears at
`/frame/manifest` with its editor route wired.

The unified `#[ParticleResource]` carries the full manifest field set on the Data class itself — label,
model, nav placement (`group` / `section` / `navOrder` / `icon`), and route identity (`routeName`):

```php
#[ParticleResource(
    key: 'sitemap',
    model: SitemapRecord::class,
    label: 'Sitemap',           // a non-empty label marks the resource FRAMED (navigable + editable)
    group: 'Site',
    section: 'links',
    navOrder: 99,
    routeName: 'frame.resources.sitemap',
)]
class SitemapData extends Data { /* … Column-annotated fields … */ }
```

See `app/Data/SitemapData.php` for the worked example (the editable sitemap resource). Adding your own
resource is the same one-file move: annotate a Data class, drop it in `app/Data/`, done.

## Package-topology stop-gap

The starter runs ONE thin `tests/Feature/Topology/DeclaredTopologyTest` (`rushing/php-package-topology`'s
`AssertsDeclaredTopology`) that **declares no rules itself**. Each *package* owns the invariants about its
own place in the graph in its `composer.json` `extra.package-topology` block; `DeclaredContractSource`
merges every installed fragment into one contract and asserts it. A green suite means "the declared
hierarchy holds" — a package depending UP, a namespace crossing the vendor seam, or a require cycle fails
here. Correcting a rule is a one-line edit in the package that owns it; this test follows automatically.

```bash
composer test              # app boot/auth tests + topology stop-gap
composer test:packages     # runs each co-dev tier package's OWN suite (overlay must be active)
```

## License

MIT.
