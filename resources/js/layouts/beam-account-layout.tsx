import type { ReactNode } from 'react';
import { AppSidebarBeam } from '@/components/app-sidebar-beam';

/**
 * The `account`-realm layout — mounts the packaged `<AccountShell>` (via <AppSidebarBeam>) around the
 * page body. Wired as a named layout in app.tsx for `account/*` pages. Kept parallel to the starter's
 * own `app-layout` (the shadcn kit) so this is a clean demonstration of the OOTB account surface.
 */
export default function BeamAccountLayout({ children }: { children: ReactNode }) {
    return <AppSidebarBeam>{children}</AppSidebarBeam>;
}
