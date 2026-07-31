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

The starter runs ONE thin `tests/Feature/Topology/DeclaredTopologyTest` (`rushing/laravel-package-topology`'s
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
