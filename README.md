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

## Package-topology stop-gap

Instead of bespoke tests, the starter carries the **package-topology conformance**
(`rushing/laravel-package-topology`) as the cross-package guard that catches a misplaced refactor —
a package depending UP, a namespace leaking across the vendor seam, or a require cycle. A green
`tests/Feature/Topology/` suite means "the tier hierarchy holds". Contracts accumulate down the fork
chain (this tier inherits the beam vendor-seam rules and adds the satellite-tier ones).

```bash
composer test              # app boot/auth tests + topology stop-gap
composer test:packages     # runs each co-dev tier package's OWN suite (overlay must be active)
```

## License

MIT.
