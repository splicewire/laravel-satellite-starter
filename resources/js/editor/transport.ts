// The host-supplied entry-body transport — the ONE injection seam the promoted editor + Mainframe host
// load/save an entry body through. Mirrors audiostud's `beam-ux-services` puckClient: a same-origin,
// cookie-authed load/save over beam-ux's versioned ParticleWriter (`/beam/ux/entries/{slug}/body`). Retry
// off — a failed authoring load/save should surface.
//
// **It is SLUG-addressed, and it no longer claims otherwise.** This was declared `bodyClient:
// UxBuilderClient` — an interface whose `loadBody`/`saveBody` have been ID-addressed since ADR-0214 §2
// — while fetching `/beam/ux/entries/{slug}/body` with a slug. `tsc` never flagged it, and could not:
// a slug and an id are both `string`, so the whole mismatch is invisible to the compiler (the trap
// beam-docs-satellite ticket 33 recorded, measured live in all three starters at ticket 37). Dropping
// the annotation is the smallest change that stops the type system asserting something false.
//
// The real fix is to move onto the two id-addressed `beam-ux-entry` operations, as `splicewire/www` has
// (ADR-0214 §1) — the starter chain's own migration, not this file's job to fake.

/** Read the Laravel `XSRF-TOKEN` cookie for the stateful mutating PUT. */
function csrfToken(): string {
    const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return m ? decodeURIComponent(m[1]) : '';
}

export const bodyClient = {
    loadBody: async (slug: string): Promise<unknown> => {
        const res = await fetch(`/beam/ux/entries/${slug}/body`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!res.ok) {
            throw new Error(`load ${res.status}`);
        }

        return (await res.json()).data;
    },
    saveBody: async (slug: string, body: Record<string, unknown>): Promise<unknown> => {
        const res = await fetch(`/beam/ux/entries/${slug}/body`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': csrfToken(), Accept: 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ body }),
        });

        if (!res.ok) {
            throw new Error(`save ${res.status}`);
        }

        return (await res.json()).data;
    },
};
