import { Head } from '@inertiajs/react';
import { bodyClient } from '@splicewire/beam-inertia';
import { ThemeEditor, UxBuilderProvider } from '@splicewire/beam-ux';
import type { UxBuilderServices } from '@splicewire/beam-ux';
import { useMemo } from 'react';
import { toast } from 'sonner';
import type { EntryPageData } from '@/generated/App/Data/Pages';

/**
 * The ACCOUNT-realm THEME surface (G2-BEAM-THEME-NAV).
 *
 * The whole page body is `@splicewire/beam-ux`' own <ThemeEditor> — the load, the real
 * `@schemastud/seam` SchemaForm over the server's theme schema, the edit buffer, Save and Discard all
 * travel inside the package. This file supplies the two things only a host can: the transport
 * (`@splicewire/beam-inertia`'s `bodyClient`, the same one the in-place editor dock saves through) and
 * the feedback sink. The same "wrote only config" shape `account/tokens` and `account/team` make.
 *
 * The <AccountShell> chrome and the nav rail come from app.tsx's layout resolution for `account/*`
 * pages, so this file is pure content.
 *
 * `entry` is null only on a database that was never seeded (`ThemeSeeder` mints the central theme
 * row). Saying so is the honest answer — a form with no entry id behind it would look authored and
 * silently save nowhere.
 */
export default function AccountTheme({ entry }: EntryPageData) {
    const services = useMemo<UxBuilderServices>(
        () => ({
            client: bodyClient,
            notify: (event) =>
                event.type === 'success'
                    ? toast.success(event.message)
                    : toast.error(event.message),
        }),
        [],
    );

    return (
        <>
            <Head title="Theme" />
            <div className="p-6">
                {entry ? (
                    <UxBuilderProvider services={services}>
                        <ThemeEditor entryId={entry.id} />
                    </UxBuilderProvider>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        This site has no theme entry yet — seed one with{' '}
                        <code className="font-mono">php artisan db:seed --class=ThemeSeeder</code>.
                    </p>
                )}
            </div>
        </>
    );
}
