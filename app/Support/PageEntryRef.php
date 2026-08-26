<?php

namespace App\Support;

use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Splicewire\Beam\Ux\Type\UxType;

/**
 * The `entry` prop a hand-written Inertia page shares so its Mainframe host can address its beam-ux
 * entry **by id** — `{id, slug}`, or null when the page is bound to no entry.
 *
 * ## Why this exists at all
 *
 * `@splicewire/beam-mainframe`'s `createMainframeHost` resolves the entry a page is editing from three
 * places, and only one of them can carry an id: the page's own props. The other two are the
 * `?beam_entry=<slug>` authoring override (a human types a key, never a uuid) and a host-authored,
 * COMPILE-TIME `componentToEntry` map — and no frontend map can carry a per-database uuid, because it
 * differs in every database this starter is installed into, including the fresh one it is FOR.
 *
 * So the migration off the slug-addressed `Route::beamUxEntries()` macro (ADR-0214 §6) does not happen
 * in the frontend. It happens HERE: the server, which is the only party that knows which row a page
 * means, says so in the page's props. Ported from `splicewire/www`'s helper of the same name
 * (beam-docs-satellite tickets 37 and 40).
 *
 * ## The tiebreak is written down, on purpose
 *
 * ADR-0214 §2 deleted an ambiguous `first()` that served the WRONG row — measured live at `splicewire/www`,
 * where one slug existed twice under different namespaces. This does not guess: a starter page entry is
 * `type = page` AND `namespace = config('beam.ux.namespace')` (the same namespace `AuthPagesSeeder` and
 * `App\Beam\EntryBody` already read), and anything else is not the row a route means. A slug matching
 * neither resolves to null — the page renders its own default tree (`editor/defaults.ts`) and is simply
 * not authorable, which is the honest answer on a database that was never seeded.
 */
class PageEntryRef
{
    /**
     * The `{id, slug}` ref for a page entry in this host's beam-ux namespace, or null when no such row
     * exists.
     *
     * @return array{id: string, slug: string}|null
     */
    public static function for(string $slug): ?array
    {
        $id = BeamUxEntry::query()
            ->where('slug', $slug)
            ->where('type', UxType::Page)
            ->where('namespace', config('beam.ux.namespace', 'starter'))
            ->value('id');

        return $id === null ? null : ['id' => (string) $id, 'slug' => $slug];
    }
}
