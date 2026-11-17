import { createResourceTransport, parseResourcePage } from '@schemastud/frame';
import type { ResourcePage, Row } from '@schemastud/frame';
import type { SchemaNode } from '@schemastud/seam';
import { createMessages } from '@schemastud/messages';
import { hostMessageDefaults } from '@/lib/messages';
import type { SitemapExamplePageData } from '@/generated/App/Data/Pages';
import type { SitemapReadData } from '@/generated/App/Data/Examples';

/**
 * localization 35 (S4): the transport is outside React and cannot read the served catalog, so the page that builds it passes the
 * seam's text function (`messages.t`); with none it speaks the English defaults, byte-identical to before. Its USER-FACING lines read
 * through it: the sign-in requirement and the REQUEST LINE ('GET /url failed (404)'), which users see in the page's own alert, in
 * Frame's list/edit error states and its save alert (review of 5a06e139). Only the single-resource guard stays English: it is a
 * programmer error (this example is wired to exactly one resource), never reachable by a user.
 */
export type SitemapKey =
    'sitemap_example.sign_in_required' | 'sitemap_example.request_failed';
export type SitemapText = (
    key: SitemapKey,
    params?: Record<string, string | number>,
) => string;
const english = createMessages(hostMessageDefaults);
const englishText: SitemapText = (key, params) => english.t(key, params);

/**
 * The page's text function for this transport, STABLE across catalogs: the transport (and Frame's injection) are built once and keep
 * this box's `text`; the page hands the box the current catalog's `t` (`use`) in an effect, so a locale switch never rebuilds them.
 * An error Frame ALREADY shows keeps the language it was raised in until it is retried (S4 confirm nit 2).
 */
export class SitemapTextBox {
    private current: SitemapText;

    constructor(initial: SitemapText) {
        this.current = initial;
    }

    use(next: SitemapText): void {
        this.current = next;
    }

    readonly text: SitemapText = (key, params) => this.current(key, params);
}

/** A failed request, typed, so a page can render it through the seam at RENDER time (a locale switch re-renders it). */
export class SitemapRequestError extends Error {
    constructor(
        readonly method: string,
        readonly url: string,
        readonly status: number,
        text: SitemapText = englishText,
    ) {
        super(text('sitemap_example.request_failed', { method, url, status }));
    }
}

/** Raw Frame envelopes differ from the generated client's double-wrapped API adapter. */
export async function frameJson<T>(
    url: string,
    method = 'GET',
    body?: unknown,
    text: SitemapText = englishText,
): Promise<T> {
    const cookie = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(cookie
                ? { 'X-XSRF-TOKEN': decodeURIComponent(cookie[1]) }
                : {}),
        },
        ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });

    if (!response.ok) {
        throw new SitemapRequestError(method, url, response.status, text);
    }

    return response.status === 204
        ? (undefined as T)
        : ((await response.json()) as T);
}

export function sitemapTransport(
    address: Pick<
        SitemapExamplePageData,
        'resourceUrl' | 'schemaUrl' | 'recordUrlTemplate'
    >,
    actor: string | null,
    text: SitemapText = englishText,
) {
    // every request of this transport carries the page's text function (its failure line reads the seam)
    const json = <T>(url: string, method = 'GET', body?: unknown) =>
        frameJson<T>(url, method, body, text);
    const root = (resource: string) => {
        if (actor === null) {
            throw new Error(text('sitemap_example.sign_in_required'));
        }

        // a PROGRAMMER error, never a user's: this example is wired to exactly one resource (not keyed, on purpose)
        if (resource !== 'sitemap') {
            throw new Error(
                'This example addresses only the Sitemap resource.',
            );
        }

        return address.resourceUrl;
    };
    const recordUrl = (id: string | number) =>
        address.recordUrlTemplate.replace(
            '__id__',
            encodeURIComponent(String(id)),
        );

    return createResourceTransport(
        {
            async list<Result>(
                resource: string,
                params: Record<string, string>,
            ): Promise<ResourcePage<Result>> {
                const query = new URLSearchParams(params).toString();

                return parseResourcePage(
                    await json<ResourcePage<Result>>(
                        root(resource) + (query ? `?${query}` : ''),
                    ),
                );
            },
            async get(resource, id): Promise<Row> {
                root(resource);
                const response = await json<{ data: SitemapReadData }>(
                    recordUrl(id),
                );

                return { ...response.data };
            },
            getFormSchema(
                resource,
                _form,
                purpose = 'write',
            ): Promise<SchemaNode> {
                root(resource);

                return json(
                    address.schemaUrl +
                        (purpose === 'read' ? '?purpose=read' : ''),
                );
            },
            async create<Result = Row>(
                resource: string,
                data: unknown,
            ): Promise<Result> {
                return (
                    await json<{ data: Result }>(root(resource), 'POST', data)
                ).data;
            },
            async save(resource, id, data) {
                root(resource);

                return (await json<{ data: Row }>(recordUrl(id), 'PUT', data))
                    .data;
            },
            async remove(resource, id) {
                root(resource);
                await json(recordUrl(id), 'DELETE');
            },
        },
        {
            resourceUrl: root,
            read: <T>(url: string, params?: Record<string, string>) => {
                const query = new URLSearchParams(params).toString();

                return json<T>(url + (query ? `?${query}` : ''));
            },
        },
    );
}
