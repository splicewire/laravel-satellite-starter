<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Versioned Schema Base URI
    |--------------------------------------------------------------------------
    |
    | The authority of every versioned `$id` this host mints, and the origin that
    | SERVES those schemas — the same string on purpose, so an `$id` says who
    | answers for it.
    |
    | The package ships no default: a fleet-wide default can only be one vendor's
    | domain stamped onto every other vendor's schemas, which is how the dead
    | `https://schemas.splicewire.app` reached hosts across three vendors. Leaving
    | this unset THROWS (`MissingSchemaBaseUri`) the moment any class implementing
    | `Schemastud\DataSchemas\Contracts\SchemaIdentity` is generated — an `$id` is
    | write-once, so a guess is unrecoverable.
    |
    | THIS IS A STARTER, so the value here is inherited by every host cloned from
    | it — which rules out both a real authority (one vendor's domain baked into
    | everyone's clone) and leaving it unset (a fresh clone red out of the box).
    | So it ships ORIGIN-LESS (beam-facade ticket 83): `/schemas` is path-shaped
    | and carries no host, so a minted `$id` is a relative reference that resolves
    | against whatever origin actually serves the document — it cannot bake a
    | wrong authority the way a derived `app.url` default would (ticket 82
    | declined exactly that, because artifacts freeze on a dev machine and would
    | carry `.test` forever).
    |
    | A host cloned from this starter SHOULD replace this with its own absolute
    | authority (`https://app.example.com/schemas`) before it freezes anything it
    | intends to serve — a relative `$id` is a safe default, not a destination.
    |
    | Only this key is set; every other `data-schemas` setting falls through to
    | the package default via `mergeConfigFrom`.
    |
    */
    'base_uri' => env('SCHEMA_BASE_URI', '/schemas'),

];
