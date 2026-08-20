import { Link, usePage } from '@inertiajs/react';
import { AccountShell } from '@splicewire/beam-ux/account';
import type { AccountNavItem } from '@splicewire/beam-ux/account';
import { ChevronsUpDown, LayoutGrid, Settings } from 'lucide-react';
import type { ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';

/**
 * A context-free user footer for the packaged <AccountShell>.
 *
 * NOTE: the starter's own `<NavUser>` cannot be used here — it calls `useSidebar()` from the host's
 * `@/components/ui/sidebar` (shadcn) SidebarProvider, but <AccountShell> mounts the DIFFERENT
 * @schemastud/ui Sidebar provider, so the host hook throws "useSidebar must be used within a
 * SidebarProvider". This footer uses only a plain dropdown (no sidebar context), so it works inside
 * either shell.
 */
function BeamNavUser() {
    const { auth } = usePage<{ auth: { user: { name: string; email: string; avatar?: string } | null } }>().props;

    if (!auth.user) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="flex w-full items-center gap-2 rounded-md p-2 text-left text-sm hover:bg-accent"
                    data-test="sidebar-menu-button"
                >
                    <UserInfo user={auth.user} />
                    <ChevronsUpDown className="ml-auto size-4" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="min-w-56 rounded-lg" align="end" side="top">
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/**
 * The `account`-realm app shell — a THIN host wrapper over the generic `@splicewire/beam-ux/account`
 * `<AccountShell>`. The package owns the sidebar/inset chrome (the @schemastud/ui Sidebar + brand
 * header + data-driven nav group + opt-in plan/profile sections + user footer slot); this file supplies
 * only host specifics: the brand, Inertia's `<Link>`, active-URL matching, neutral row icons, and the
 * `<NavUser>` footer. This is the "wrote only config" proof for the account realm.
 *
 * The nav is DATA-DRIVEN from the shared `accountNav` prop; the plan/profile blocks are driven by the
 * shared `accountShell` prop (both projected by HandleInertiaRequests). Add an account-realm row to
 * resources/beam-ux/nav.yml and it appears here — no edit to this file.
 */
type AccountNavTree = { items: { title: string; href: string | null }[] };
type ShellData = {
    plan: { tier: string; label: string; credits?: number | null; max?: number | null };
    profile: { handle: string; avatar?: string | null; metrics: { label: string; value: string }[] };
    account: { email: string; paymentMethodLabel?: string | null };
    upsells: { key: string; label: string; href?: string | null }[];
};

export function AppSidebarBeam({ children }: { children: ReactNode }) {
    const page = usePage<{
        accountNav?: AccountNavTree;
        accountShell?: ShellData | null;
        sidebarOpen?: boolean;
    }>();
    const realmItems = page.props.accountNav?.items ?? [];
    const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';

    // The nav rows come entirely from the `account` sitemap (Dashboard / Profile in nav.yml) — no
    // hardcoded row, so adding an account-realm page to nav.yml is the only edit needed.
    const navItems: AccountNavItem[] = realmItems.filter(
        (item): item is { title: string; href: string } => !!item.href,
    );

    return (
        <AccountShell
            nav={{ items: navItems }}
            navLabel="Account"
            linkComponent={Link}
            brand={<AppLogo />}
            brandHref="/dashboard"
            isActive={(href) => currentPath === href || (href !== '/' && currentPath.startsWith(href))}
            navItemIcon={(item) => (item.href === '/dashboard' ? <LayoutGrid /> : <Settings />)}
            shell={page.props.accountShell ?? null}
            sections={{ plan: true, profile: true }}
            sectionLabels={{ plan: 'Plan', profile: 'Profile' }}
            user={<BeamNavUser />}
            defaultOpen={page.props.sidebarOpen ?? true}
        >
            {children}
        </AccountShell>
    );
}
