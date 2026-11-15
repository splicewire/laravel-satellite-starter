/**
 * The transport half of `@splicewire/beam-accounts`' <TeamPage>.
 *
 * The component owns its own react-query data logic, its own presentation and its own DTO typing; the
 * ONE thing it takes from a host is a client (contract §1), and this file is the whole of it for this
 * starter. Nothing about an invitation is decided here. The tokens surface needs no client here: the
 * package ships `createTokensClient`, and `pages/account/tokens.tsx` hands it frame's session request
 * and the endpoints laravel-beam-accounts builds from its routes (frame-foundation 51).
 *
 * ⚠️ **These URLs are literals and the mount they point at is CONFIG-DRIVEN** —
 * `config('beam.accounts.api_root')`, mounted by `Route::splicewireAccountApiRoutes()` in
 * `routes/web.php`. Wayfinder is retired fleet-wide (beam-runbook ADR-0004), so nothing links these
 * strings to that config: change the root and every call here 404s silently. The same warning
 * `@splicewire/beam-inertia`'s frame transport carries, for the same reason. If the team surface
 * shows an empty table, diff these paths against `route:list` BEFORE looking anywhere else.
 */
import type { TeamClient } from '@splicewire/beam-accounts';

const ROOT = '/beam/accounts';

function csrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : null;
}

function headers(): Record<string, string> {
    const token = csrfToken();

    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'X-XSRF-TOKEN': token } : {}),
    };
}

/**
 * One request, returning the `{data: …}` envelope's data slot.
 *
 * A non-2xx REJECTS, and the rejection carries the server's own `message` where it sent one. That is
 * load-bearing rather than polish: every mutation in both packaged surfaces routes its failure
 * through the injected `onError`, and a refusal these endpoints state precisely ("A team must keep
 * at least one owner.", "You cannot archive the token you are using.") is worth strictly more to the
 * reader than "request failed". A validation failure (422) keeps its field errors on `.errors`, so a
 * form can show them against the input instead of replacing what was typed.
 */
async function call<T>(
    method: string,
    path: string,
    body?: unknown,
): Promise<T> {
    const res = await fetch(`${ROOT}${path}`, {
        method,
        headers: headers(),
        credentials: 'same-origin',
        ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });

    const payload = (await res.json().catch(() => null)) as {
        data?: T;
        message?: string;
        errors?: Record<string, string[]>;
    } | null;

    if (!res.ok) {
        const error = new Error(
            payload?.message ||
                `${method} ${ROOT}${path} failed (${res.status})`,
        ) as Error & { status?: number; errors?: Record<string, string[]> };
        error.status = res.status;
        error.errors = payload?.errors;

        throw error;
    }

    return payload?.data as T;
}

export const teamClient: TeamClient = {
    members: () => call('GET', '/members'),
    invitations: () => call('GET', '/invitations'),
    roles: () => call('GET', '/members/roles'),
    updateRole: ({ userId, role }) =>
        call('PUT', `/members/${userId}/role`, { role }),
    removeMember: (id) => call('DELETE', `/members/${id}`),
    sendInvitation: (body) => call('POST', '/invitations', body),
    revokeInvitation: (id) => call('DELETE', `/invitations/${id}`),
    resendInvitation: (id) => call('POST', `/invitations/${id}/resend`),
};
