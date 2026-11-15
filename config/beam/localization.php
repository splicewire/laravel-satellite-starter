<?php

/*
 * beam localization (localization 06; ADR-0246), the `beam.localization` Config Section. This starter OPTS IN to the
 * localized HTTP profile, so a request's `Accept-Language` is negotiated against the languages below and every response
 * states its language (`Content-Language`, `Vary: Accept-Language`, `<html lang>`).
 */
return [
    // The languages this starter ships. Add a tag here when its lang files exist (`lang/es/…`, `lang/vendor/<ns>/es/…`).
    'supported' => ['en'],

    'fallback' => 'en',

    // The pseudo-locale `en-XA` (English, marked and padded) for a walkthrough: any text that shows up unmarked under it
    // did not come through the catalog. Off unless BEAM_LOCALIZATION_PSEUDO is set.
    'pseudo' => (bool) env('BEAM_LOCALIZATION_PSEUDO', false),

    // Ticket 11's brand adapter reads this; null states no brand language.
    'brand_default' => null,

    'http_profile' => true,

    // Null: a contested translator namespace throws outside production and is reported in it.
    'throw_on_contested_namespace' => null,
];
