import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
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
import type { SitemapExamplePageData } from '@/generated/App/Data/Pages';
import { frameJson, sitemapTransport } from '@/examples/sitemap-transport';

const Box = ({ children }: { children?: ReactNode }) => <div>{children}</div>;
const fieldTitles: Record<string, string> = {
    id: 'ID',
    label: 'Label',
    href: 'Path',
    order: 'Order',
    externalUrl: 'External URL',
};
function ExampleFormBody(props: FormBodySlotProps) {
    // Presentation labels only; the served schema, field names and validation rules are preserved.
    const schema = useMemo(
        () => ({
            ...props.schema,
            properties: Object.fromEntries(
                Object.entries(props.schema.properties ?? {}).map(
                    ([name, field]) => [
                        name,
                        { ...field, title: fieldTitles[name] ?? field.title },
                    ],
                ),
            ),
        }),
        [props.schema],
    );

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
    const { auth } = usePage<{
        auth: { user: { id: string | number } | null };
    }>().props;
    const actor = auth.user ? String(auth.user.id) : null;
    const manifest = useQuery({
        queryKey: ['satellite-sitemap-example', actor, address.manifestUrl],
        queryFn: () =>
            frameJson<{ contexts: Record<string, ContextManifest> }>(
                address.manifestUrl,
            ),
        staleTime: 0,
    });
    const sourceContext = manifest.data?.contexts.sitemap;
    const context = useMemo(
        () =>
            sourceContext
                ? { ...sourceContext, singularLabel: 'link' }
                : undefined,
        [sourceContext],
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
            ),
        [
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
            schemaFetcher: (ref: string): Promise<SchemaNode> => frameJson(ref),
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
        [transport, registry, context],
    );

    return (
        <div className="space-y-6 p-6">
            <Head title="Sitemap resource example" />
            <div className="flex items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Sitemap resource example
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Site-wide links, with an explicit read-only resource
                        view.
                    </p>
                </div>
                {address.mode !== 'list' && (
                    <Link href={address.listHref}>Back to list</Link>
                )}
            </div>
            {manifest.isPending ? (
                <p>Loading resource permissions…</p>
            ) : manifest.isError ? (
                <div role="alert">
                    <p>{manifest.error.message}</p>
                    <Button onClick={() => void manifest.refetch()}>
                        Retry permissions
                    </Button>
                </div>
            ) : !context ? (
                <p role="alert">
                    This resource is unavailable to your account.
                </p>
            ) : (
                <FrameProvider value={injection}>
                    {address.mode === 'list' ? (
                        <ListShell
                            resource="sitemap"
                            columns={[]}
                            manifest={context}
                            onOpen={(row) =>
                                router.visit(
                                    row.id === null
                                        ? address.newHref
                                        : address.detailHrefTemplate.replace(
                                              '__id__',
                                              encodeURIComponent(
                                                  String(row.id),
                                              ),
                                          ),
                                )
                            }
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
