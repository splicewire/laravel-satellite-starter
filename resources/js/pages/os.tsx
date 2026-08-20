// beam·os — the direct full-desktop `/os` entry. Mounts the shared OsShellDesktop (menu bar + dock +
// launcher + floating window manager) with the starter's realms as launchable apps and a NEUTRAL theme.
// Route-gated on `auth,verified` (production gates on the projected `os.enter` entitlement). The whole
// desktop (theme CSS + realm surfaces + window config) lives in `@/os/shell-config` so any other mount
// point reuses the SAME desktop. This page adds only the Inertia <Head>.
import { Head } from '@inertiajs/react';
import { OsShellDesktop } from '@/os/shell-config';

export default function Os() {
    return (
        <>
            <Head title="beam·os" />
            <OsShellDesktop />
        </>
    );
}
