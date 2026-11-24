import { Head } from '@inertiajs/react';
import { AgreementsAndConsents } from '@splicewire/beam-agreements';
import type { ResourceKey } from '@schemastud/frame';

export default function AccountAgreements({ resource }: { resource: ResourceKey }) {
    return (
        <>
            <Head title="Agreements & consents" />
            <div className="p-6">
                <AgreementsAndConsents resource={resource} />
            </div>
        </>
    );
}
