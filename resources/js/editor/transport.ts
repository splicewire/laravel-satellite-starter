// The host-supplied entry-body transport — the ONE injection seam the promoted editor + Mainframe host
// load/save an entry body through. Mirrors `splicewire/www`'s and `rushing/audiostud`'s: a same-origin,
// cookie-authed load/save over beam-ux's versioned ParticleWriter. Retry off — a failed authoring
// load/save should surface.
//
// ## Addressed by ID, as of beam-docs-satellite ticket 40
//
// It loads/saves over the id-addressed particle operations `beam-ux-entry.op.body` /
// `beam-ux-entry.op.save-body` (ADR-0214 §1). It used to fetch the LITERAL
// `/beam/ux/entries/${slug}/body` — the `Route::beamUxEntries()` macro — and that was wrong twice over:
//
//  - **Slug-addressed.** `UxBuilderClient.loadBody` has taken an ID since ADR-0214 §2, and this file
//    declared `bodyClient: UxBuilderClient` while feeding it a slug. A slug and an id are both `string`,
//    so `tsc` never once complained — the annotation asserted something untrue for months (ticket 37
//    dropped the annotation rather than fake it; this is the real fix).
//  - **A literal URL.** A literal silently stops matching the moment the mount moves. `splicewire/www`
//    was bitten by exactly that (ticket 07: the route moved under `api/` and the editor 404'd on every
//    page). The URL now comes from the generated Wayfinder helper, keyed by ROUTE NAME, which does not
//    move when the URI does — and this migration moved the URI, so the point is not hypothetical.
import type { UxBuilderClient } from '@splicewire/beam-ux';
import { body as showBody, saveBody as postSaveBody } from '@/routes/beam-ux-entry/op';

/** Read the Laravel `XSRF-TOKEN` cookie for the stateful mutating POST. */
function csrfToken(): string {
    const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return m ? decodeURIComponent(m[1]) : '';
}

async function readData(res: Response, what: string): Promise<unknown> {
    if (!res.ok) {
        throw new Error(`${what} ${res.status}`);
    }

    return (await res.json()).data;
}

export const bodyClient: UxBuilderClient = {
    /**
     * The read is a GET with NO query string, deliberately: `EntryBodyShowOp` declares `input: false`,
     * and beam's operation controller rejects a GET carrying any query key with a 422. The retired
     * `?namespace=` disambiguator is therefore not merely ignored — appending one fails loudly.
     */
    loadBody: async (id) => {
        const res = await fetch(showBody.url({ id }), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        return (await readData(res, 'load')) as Awaited<ReturnType<UxBuilderClient['loadBody']>>;
    },
    saveBody: async (id, body) => {
        const res = await fetch(postSaveBody.url({ id }), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': csrfToken(), Accept: 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ body }),
        });

        return (await readData(res, 'save')) as Awaited<ReturnType<UxBuilderClient['saveBody']>>;
    },
};
