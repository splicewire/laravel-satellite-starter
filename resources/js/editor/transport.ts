// The host-supplied entry-body transport — the ONE injection seam the promoted editor + Mainframe host
// load/save an entry body through. Mirrors audiostud's `beam-ux-services` puckClient: a same-origin,
// cookie-authed load/save over beam-ux's versioned ParticleWriter (`/beam/ux/entries/{slug}/body`). Retry
// off — a failed authoring load/save should surface.
import type { UxBuilderClient } from '@splicewire/beam-ux';

/** Read the Laravel `XSRF-TOKEN` cookie for the stateful mutating PUT. */
function csrfToken(): string {
    const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return m ? decodeURIComponent(m[1]) : '';
}

export const bodyClient: UxBuilderClient = {
    loadBody: async (slug) => {
        const res = await fetch(`/beam/ux/entries/${slug}/body`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!res.ok) {
            throw new Error(`load ${res.status}`);
        }

        return (await res.json()).data;
    },
    saveBody: async (slug, body) => {
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
