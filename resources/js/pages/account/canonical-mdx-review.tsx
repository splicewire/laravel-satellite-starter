import { useState } from 'react';
import { router } from '@inertiajs/react';
import { bodyClient } from '@splicewire/beam-inertia';
import { ReleasePanel } from '@splicewire/beam-ux';
import type { ReleaseRecord, ReleaseCommand } from '@splicewire/beam-ux';
import { useHostMessages } from '@/lib/messages';
import type { CanonicalMdxReviewPageData } from '@/generated/App/Data/Pages';

/** A version chooser and the shipped ReleasePanel, never an MDX canvas editor. */
export default function CanonicalMdxReviewPage({
    entryId,
    title,
    versions,
    releases,
}: CanonicalMdxReviewPageData) {
    const messages = useHostMessages();
    const [release, setRelease] = useState<ReleaseRecord | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const create = async () => {
        setBusy(true);
        setError('');

        try {
            if (!bodyClient.createRelease) {
                throw new Error(
                    messages.t('mdx_review.release_creation_unavailable'),
                );
            }

            setRelease(
                await bodyClient.createRelease(
                    entryId,
                    messages.t('mdx_review.release_default_title', { title }),
                ),
            );
        } catch (failure) {
            setError(
                failure instanceof Error
                    ? failure.message
                    : messages.t('mdx_review.release_not_created'),
            );
        } finally {
            setBusy(false);
        }
    };
    const command = async (input: ReleaseCommand) => {
        if (!release || !bodyClient.releaseCommand) {
            throw new Error(
                messages.t('mdx_review.release_commands_unavailable'),
            );
        }

        setRelease(await bodyClient.releaseCommand(release.id, input));
    };
    const refresh = async () => {
        if (!release || !bodyClient.listReleases) {
            return;
        }

        setBusy(true);
        setError('');

        try {
            const current = (await bodyClient.listReleases()).find(
                (item) => item.id === release.id,
            );

            if (!current) {
                throw new Error(
                    messages.t('mdx_review.this_release_unavailable'),
                );
            }

            setRelease(current);
        } catch (failure) {
            setRelease(null);
            setError(
                failure instanceof Error
                    ? failure.message
                    : messages.t('mdx_review.release_unavailable'),
            );
        } finally {
            setBusy(false);
        }
    };

    return (
        <main className="mx-auto max-w-5xl space-y-4 p-6">
            <h1 className="text-2xl font-semibold">
                {messages.t('mdx_review.title')}
            </h1>
            <p>{title}</p>
            <p>{messages.t('mdx_review.choose_version')}</p>
            <p>{messages.t('mdx_review.publication_note')}</p>
            {error && <p role="alert">{error}</p>}
            <label className="block min-w-0">
                {messages.t('mdx_review.existing_release')}{' '}
                <select
                    aria-label={messages.t('mdx_review.existing_release')}
                    className="block w-full max-w-full min-w-0 rounded border bg-background p-2"
                    value={release?.id ?? ''}
                    onChange={(event) =>
                        setRelease(
                            releases.find(
                                (item) => item.id === event.target.value,
                            ) ?? null,
                        )
                    }
                >
                    <option value="">
                        {messages.t('mdx_review.choose_release')}
                    </option>
                    {releases.map((item) => (
                        <option key={item.id} value={item.id}>
                            {item.title} ({item.status})
                        </option>
                    ))}
                </select>
            </label>
            <div className="flex flex-wrap gap-3">
                <button
                    type="button"
                    disabled={busy || release !== null}
                    onClick={() => void create()}
                    className="rounded-md border bg-background px-3 py-2"
                >
                    {messages.t('mdx_review.create_release')}
                </button>
                <button
                    type="button"
                    disabled={busy}
                    onClick={() => router.reload({ only: ['versions'] })}
                    className="rounded-md border bg-background px-3 py-2"
                >
                    {messages.t('mdx_review.reload_versions')}
                </button>
                {release && (
                    <button
                        type="button"
                        disabled={busy}
                        onClick={() => void refresh()}
                        className="rounded-md border bg-background px-3 py-2"
                    >
                        {messages.t('mdx_review.refresh_release')}
                    </button>
                )}
            </div>
            {release && (
                <ReleasePanel
                    release={release}
                    entries={[{ id: entryId, title, versions }]}
                    onCommand={command}
                />
            )}
        </main>
    );
}
