import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
    FrameProvider,
    DefaultFormBody,
    ListShell,
    EditShell,
    createWidgetRegistry,
    useBrowserUrlState,
} from '@schemastud/frame';
import type {
    FrameInjection,
    ContextManifest,
    FormBodySlotProps,
} from '@schemastud/frame';
import { shadcnEditSlots, shadcnListSlots } from '@schemastud/frame/shadcn';
import type { SchemaNode } from '@schemastud/seam';
import {
    Button,
    Input,
    Label,
    Badge,
    Skeleton,
    Popover,
    PopoverTrigger,
    PopoverContent,
    SimpleSelect,
} from '@schemastud/ui';
import {
    frameJson,
    sitemapTransport,
    SitemapRequestError,
    SitemapTextBox,
} from '@/examples/sitemap-transport';
import { useHostMessages } from '@/lib/messages';
import type { SitemapExamplePageData } from '@/generated/App/Data/Pages';

const Box = ({ children }: { children?: ReactNode }) => <div>{children}</div>;
function ExampleFormBody(props: FormBodySlotProps) {
    const messages = useHostMessages();
    // Presentation labels only; the served schema, field names and validation rules are preserved.
    const schema = useMemo(() => {
        // the form's field titles, through the seam (they were a module-level map of literals: copy the ratchet's scanner does
        // not count); inside the memo, which follows the messages, so a language switch re-reads them
        const fieldTitles: Record<string, string> = {
            id: messages.t('sitemap_example.field_id'),
            label: messages.t('sitemap_example.field_label'),
            href: messages.t('sitemap_example.field_path'),
            order: messages.t('sitemap_example.field_order'),
            externalUrl: messages.t('sitemap_example.field_external_url'),
        };

        return {
            ...props.schema,
            title: messages.t('sitemap_example.form_title'),
            properties: Object.fromEntries(
                Object.entries(props.schema.properties ?? {}).map(
                    ([name, field]) => [
                        name,
                        { ...field, title: fieldTitles[name] ?? field.title },
                    ],
                ),
            ),
        };
    }, [props.schema, messages]);

    return <DefaultFormBody {...props} schema={schema} />;
}
const primitives: FrameInjection['primitives'] = {
    Button,
    Input,
    Label,
    Badge,
    Skeleton,
    Popover,
    PopoverTrigger,
    PopoverContent,
    SimpleSelect,
    Table: Box,
    SidePanel: Box,
};

/** Existing app chrome and QueryClient surround this small host-owned Frame consumer. */
export default function SitemapExample(address: SitemapExamplePageData) {
    const messages = useHostMessages();
    // the seam's text for the transport, STABLE across catalogs (SitemapTextBox): the transport and Frame's injection are built once,
    // and a locale switch never rebuilds them (that would reset Frame's cache and could drop an open form); the effect hands the box
    // the current catalog's `t`
    const [textBox] = useState(() => new SitemapTextBox(messages.t));
    useEffect(() => {
        textBox.use(messages.t);
    }, [textBox, messages]);
    const text = textBox.text;
    const { auth } = usePage<{
        auth: { user: { id: string | number } | null };
    }>().props;
    const actor = auth.user ? String(auth.user.id) : null;
    const manifest = useQuery({
        queryKey: ['satellite-sitemap-example', actor, address.manifestUrl],
        queryFn: () =>
            frameJson<{ contexts: Record<string, ContextManifest> }>(
                address.manifestUrl,
                'GET',
                undefined,
                text,
            ),
        staleTime: 0,
    });
    const sourceContext = manifest.data?.contexts.sitemap;
    const context = useMemo(
        () =>
            sourceContext
                ? {
                      ...sourceContext,
                      // the record NOUN Frame composes into its own phrases ('New link', …). Its LIMIT, stated: a bare noun cannot
                      // carry gender, case or a plural; Frame's composition owns that (its package localization), not this host
                      singularLabel: messages.t(
                          'sitemap_example.singular_label',
                      ),
                  }
                : undefined,
        [sourceContext, messages],
    );
    // The transport's identity is Frame's cache authority boundary; keep it stable per actor/endpoint.
    const transport = useMemo(
        () =>
            sitemapTransport(
                {
                    resourceUrl: address.resourceUrl,
                    schemaUrl: address.schemaUrl,
                    recordUrlTemplate: address.recordUrlTemplate,
                },
                actor,
                // the transport's user-facing error reads the seam through the page (it is outside React itself)
                text,
            ),
        [
            text,
            actor,
            address.resourceUrl,
            address.schemaUrl,
            address.recordUrlTemplate,
        ],
    );
    const registry = useMemo(() => createWidgetRegistry(), []);
    const injection = useMemo<FrameInjection>(
        () => ({
            transport,
            registry,
            primitives,
            useUrlState: useBrowserUrlState,
            schemaFetcher: (ref: string): Promise<SchemaNode> =>
                frameJson(ref, 'GET', undefined, text),
            can: (action, resource) => {
                if (resource !== 'sitemap' || !context) {
                    return false;
                }

                if (action === 'view' || action === 'viewAny') {
                    return true;
                }

                // This example only creates and inspects; original /sitemap retains author update/delete.
                return action === 'create' && context.can?.create === true;
            },
            manifestFor: (resource) =>
                resource === 'sitemap' ? context : undefined,
            listSlots: shadcnListSlots,
            editSlots: { ...shadcnEditSlots, FormBody: ExampleFormBody },
        }),
        [transport, registry, context, text],
    );

    return (
        <div className="space-y-6 p-6">
            <Head title={messages.t('sitemap_example.title')} />
            <div className="flex items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        {messages.t('sitemap_example.title')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {messages.t('sitemap_example.intro')}
                    </p>
                </div>
                {address.mode !== 'list' && (
                    <Link href={address.listHref}>
                        {messages.t('sitemap_example.back_to_list')}
                    </Link>
                )}
            </div>
            {manifest.isPending ? (
                <p>{messages.t('sitemap_example.loading_permissions')}</p>
            ) : manifest.isError ? (
                <div role="alert">
                    <p>
                        {manifest.error instanceof SitemapRequestError
                            ? messages.t('sitemap_example.request_failed', {
                                  method: manifest.error.method,
                                  url: manifest.error.url,
                                  status: manifest.error.status,
                              })
                            : manifest.error.message}
                    </p>
                    <Button onClick={() => void manifest.refetch()}>
                        {messages.t('sitemap_example.retry_permissions')}
                    </Button>
                </div>
            ) : !context ? (
                <p role="alert">{messages.t('sitemap_example.unavailable')}</p>
            ) : (
                <FrameProvider value={injection}>
                    {address.mode === 'list' ? (
                        <ListShell
                            resource="sitemap"
                            columns={[]}
                            manifest={context}
                            onOpen={(row) => {
                                // @schemastud/frame 0.4.1 types the opened row as this resource's row Data
                                // (SitemapData), which declares no `id`; the wire row still carries the record
                                // key (SitemapWriteAuthorizationTest asserts `data.id`), and a new record opens
                                // as `{ id: null }`. Read the key from the wire shape.
                                const id = (row as Record<string, unknown>).id;
                                router.visit(
                                    id === null || id === undefined
                                        ? address.newHref
                                        : address.detailHrefTemplate.replace(
                                              '__id__',
                                              encodeURIComponent(String(id)),
                                          ),
                                );
                            }}
                        />
                    ) : (
                        <EditShell
                            key={`${address.mode}:${address.id}`}
                            resource="sitemap"
                            id={address.mode === 'create' ? null : address.id}
                            container="page"
                            readOnly={address.mode === 'read'}
                            schemaPurpose={
                                address.mode === 'read' ? 'read' : 'write'
                            }
                            onSaved={() => router.visit(address.listHref)}
                            onCancel={() => router.visit(address.listHref)}
                        />
                    )}
                </FrameProvider>
            )}
        </div>
    );
}
