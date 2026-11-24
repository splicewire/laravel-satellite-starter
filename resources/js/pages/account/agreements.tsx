import { Head } from '@inertiajs/react';
import { AgreementsAndConsents } from '@splicewire/beam-agreements';
import type { ResourceKey } from '@schemastud/frame';
import { useHostMessages } from '@/lib/messages';

export default function AccountAgreements({
    resource,
}: {
    resource: ResourceKey;
}) {
    const messages = useHostMessages();

    return (
        <>
            <Head title={messages.t('account.agreements_title')} />
            <div className="p-6">
                <AgreementsAndConsents resource={resource} />
            </div>
        </>
    );
}
