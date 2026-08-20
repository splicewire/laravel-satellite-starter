import { Head } from '@inertiajs/react';

/**
 * The operator back-office landing — a cross-model stats roll-up. An ordinary Inertia page the host owns
 * (the operator realm's front-end); the Frame/particle resource lists are served by Frame's generic CRUD
 * socket. Props are optional so the SAME component renders inside an OS window (which threads only shared
 * props) with sensible fallbacks.
 */
type Props = {
    staff?: { name: string; email: string };
    stats?: { users: number; sitemaps: number; entries: number };
};

function Stat({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-xl border border-border bg-card p-5">
            <div className="font-mono text-3xl font-semibold">{value}</div>
            <div className="mt-1 text-xs tracking-wide text-muted-foreground uppercase">{label}</div>
        </div>
    );
}

export default function OperatorDashboard({ staff, stats }: Props) {
    const s = staff ?? { name: 'Operator', email: 'operator@example.test' };
    const st = stats ?? { users: 0, sitemaps: 0, entries: 0 };

    return (
        <div className="mx-auto max-w-4xl px-6 py-10">
            <Head title="Operator" />
            <h1 className="font-serif text-3xl font-semibold">Operator</h1>
            <p className="mt-1 text-sm text-muted-foreground">
                Signed in as {s.name} ({s.email})
            </p>

            <div className="mt-6 grid grid-cols-3 gap-4">
                <Stat label="Users" value={st.users} />
                <Stat label="Sitemaps" value={st.sitemaps} />
                <Stat label="UX entries" value={st.entries} />
            </div>

            <p className="mt-8 text-sm text-muted-foreground">
                This is the operator realm's front-end, framed by the promoted{' '}
                <code className="rounded bg-muted px-1 py-0.5">@splicewire/beam-mainframe</code> Mainframe
                host. Resource lists are served by Frame's generic particle CRUD socket; this landing is a
                thin stats roll-up.
            </p>
        </div>
    );
}
