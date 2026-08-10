> You are in **splicewire/laravel-satellite-starter** — start-from-scratch ground for a Splicewire satellite site.

Forks `laravel-beam-starter` and adds the `splicewire/laravel-satellite` backend stack (generation
pipeline + `splicewire/laravel-connector` connector) on top of the beam-tier base.

## Vendored family-package conventions

Any repo that vendors another family repo's code (composer `vendor/<vendor>/<pkg>/`, npm
`node_modules/<vendor>/<pkg>/`) checks that vendored repo's own `AGENTS.md` for conventions it
ships with itself before editing through into it.
