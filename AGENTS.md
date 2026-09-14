> You are in **splicewire/laravel-satellite-starter** — start-from-scratch ground for a Splicewire satellite site.

Forks `laravel-beam-starter` and adds the `splicewire/laravel-satellite` backend stack (generation
pipeline + `splicewire/laravel-connector` connector) on top of the beam-tier base.

## Vendored family-package conventions

Any repo that vendors another family repo's code (composer `vendor/<vendor>/<pkg>/`, npm
`node_modules/<vendor>/<pkg>/`) checks that vendored repo's own `AGENTS.md` for conventions it
ships with itself before editing through into it.

## Starter lineage — upstream is `laravel-beam-starter`

```
laravel-beam-starter  →  laravel-satellite-starter (you)  →  laravel-tower-starter
```

This is real git ancestry, not a convention — restored by replaying this repo's history onto
`laravel-beam-starter@369cf18` (beam-docs-satellite ticket 12). Catch up with:

```bash
git remote add upstream https://github.com/splicewire/laravel-beam-starter.git   # once
git fetch upstream && git merge upstream/main
```

**Propagation is strictly downstream. Nothing merges upward.** A fix that applies beyond the
satellite tier belongs on `laravel-beam-starter` first, then rides down here — authoring it here
strands it, because beam can never merge it back.

A catch-up merge **takes everything**, including files for packages this tier doesn't require —
packages gate behaviour, so an inert file is not a wrong file. Rejecting content to keep the tier
"clean" is what makes every later merge re-propose the same files. The genuine tier deltas are
`composer.json`, `composer.local.json.dist`, `AGENTS.md`, `README.md`, the satellite-tier deploy
workflow, and the satellite tier code itself.
