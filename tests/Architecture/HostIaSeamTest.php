<?php

namespace Tests\Architecture;

use Splicewire\Beam\Testing\AssertsHostIaSeam;
use Tests\TestCase;

/**
 * The host IA seam at the satellite starter (ux-walkthrough SPEC §5.1, UX-01). It passes only when this host's violations
 * equal the ratchet below; every entry names the UX ticket that removes it, and that ticket deletes the entry in the
 * same commit. Never writes.
 */
class HostIaSeamTest extends TestCase
{
    use AssertsHostIaSeam;

    protected function hostIaPlays(): array
    {
        return ['client'];
    }

    protected function hostIaRatchet(): array
    {
        return [
            'T1 dashboard-backing-concrete' => 'UX-13: DashboardBacking resolves the FrameNavContributor port',
            'T1 nav-port Splicewire\\Beam\\Ux\\Frame\\FrameNavContribution' => 'UX-06: bind IaCheckedNavContributor over the host contributor',
            'T2 I1 nav.yml:operator-seat' => 'UX-12b: the account pipeline retires (structural until UX-06)',
            'T3 fortify-home-literal' => 'UX-11: Landing::for() replaces the literal doors (structural until UX-11)',
            'T3 landing-resolver-missing' => 'UX-11: Landing::for() replaces the literal doors (structural until UX-11)',
            'T5 class App\\Beam\\OperatorRailSeat' => 'UX-09: operator task sections replace OperatorRailSeat',
            'T5 class App\\Beam\\RealmRegistry' => 'UX-12b: App\\Beam\\RealmRegistry is deleted',
            'T5 nav.yml:account-team realm=account' => 'UX-12b: account rows move under the user realm at /settings/*',
            'T5 nav.yml:account-theme realm=account' => 'UX-12b: account rows move under the user realm at /settings/*',
            'T5 nav.yml:account-tokens realm=account' => 'UX-12b: account rows move under the user realm at /settings/*',
            'T5 nav.yml:dashboard realm=account' => 'UX-12b: account rows move under the user realm at /settings/*',
            'T5 nav.yml:operator-dashboard realm=operator' => 'UX-09: operator rows move to resource section / host NavSections (IA-13)',
            'T5 nav.yml:operator-platform-connection realm=operator' => 'UX-09: the client-side row moves to a host NavSection (IA-13)',
            'T5 nav.yml:operator-seat realm=account' => 'UX-12b: account rows move under the user realm at /settings/*',
            'T5 nav.yml:settings-profile realm=account' => 'UX-12b: account rows move under the user realm at /settings/*',
            'T5 nav.yml:tenant-console realm=account' => 'UX-12b: account rows move under the user realm at /settings/*',
            'T6 built public/build Beam Starter' => 'UX-03: BrandData and the starter-template residue',
            'T6 built public/build react-starter-kit' => 'UX-03: BrandData and the starter-template residue',
            'T6 source .env.example APP_NAME=Laravel' => 'UX-03: BrandData and the starter-template residue',
            'T6 source node_modules/@splicewire/beam-inertia/src/components/app-header.tsx react-starter-kit' => 'UX-03: BrandData and the starter-template residue',
            'T6 source node_modules/@splicewire/beam-inertia/src/components/app-sidebar.tsx react-starter-kit' => 'UX-03: BrandData and the starter-template residue',
            'T6 source node_modules/@splicewire/beam-inertia/src/layouts/site-layout.tsx Beam Starter' => 'UX-03: BrandData and the starter-template residue',
            'T6 source node_modules/@splicewire/beam-inertia/src/layouts/site-layout.tsx href="/operator"' => 'UX-12a: the site-header realm links become RealmSwitcher',
            'T6 source resources/js/app.tsx || \'Laravel\'' => 'UX-03: BrandData and the starter-template residue',
        ];
    }

    public function test_the_host_ia_seam_holds_up_to_its_ratchet(): void
    {
        $this->assertHostIaSeamRatchet();
    }
}
