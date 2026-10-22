import { createResourceTransport, parseResourcePage } from '@schemastud/frame';
import type { ResourcePage, Row } from '@schemastud/frame';
import type { SchemaNode } from '@schemastud/seam';
import type { SitemapExamplePageData } from '@/generated/App/Data/Pages';
import type { SitemapReadData } from '@/generated/App/Data/Examples';

/** Raw Frame envelopes differ from the generated client's double-wrapped API adapter. */
export async function frameJson<T>(
    url: string,
    method = 'GET',
    body?: unknown,
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
        throw new Error(`${method} ${url} failed (${response.status})`);
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
) {
    const root = (resource: string) => {
        if (actor === null) {
            throw new Error('Sign in before using the Sitemap example.');
        }

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
                    await frameJson<ResourcePage<Result>>(
                        root(resource) + (query ? `?${query}` : ''),
                    ),
                );
            },
            async get(resource, id): Promise<Row> {
                root(resource);
                const response = await frameJson<{ data: SitemapReadData }>(
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

                return frameJson(
                    address.schemaUrl +
                        (purpose === 'read' ? '?purpose=read' : ''),
                );
            },
            async create<Result = Row>(
                resource: string,
                data: unknown,
            ): Promise<Result> {
                return (
                    await frameJson<{ data: Result }>(
                        root(resource),
                        'POST',
                        data,
                    )
                ).data;
            },
            async save(resource, id, data) {
                root(resource);

                return (
                    await frameJson<{ data: Row }>(recordUrl(id), 'PUT', data)
                ).data;
            },
            async remove(resource, id) {
                root(resource);
                await frameJson(recordUrl(id), 'DELETE');
            },
        },
        {
            resourceUrl: root,
            read: <T>(url: string, params?: Record<string, string>) => {
                const query = new URLSearchParams(params).toString();

                return frameJson<T>(url + (query ? `?${query}` : ''));
            },
        },
    );
}
