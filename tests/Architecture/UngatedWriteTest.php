<?php

namespace Tests\Architecture;

use Splicewire\Beam\Testing\AssertsUngatedWrites;
use Tests\TestCase;

/**
 * app-walkthrough APP-08 (APP-11): every hand-written write route at this host either declares a gate or is listed
 * here with its owner. The list only shrinks: a new ungated write fails, and so does an entry whose route is now
 * gated or gone. `splicewire:beam:doctor`'s `http.ungated-write` reports the same set.
 */
class UngatedWriteTest extends TestCase
{
    use AssertsUngatedWrites;

    public function test_every_ungated_write_route_is_listed_with_its_owner(): void
    {
        $this->assertUngatedWriteRatchet();
    }

    protected function ungatedWriteRatchet(): array
    {
        return [
            'POST beam/accounts/invitations' => 'APP-08b: team invitation; declare the team-manage ability',
            'POST beam/accounts/tokens' => 'APP-08b: a user\'s own API tokens; declare the self-service ability',
            'POST beam/accounts/tokens/{id}/renew' => 'APP-08b: a user\'s own API tokens; declare the self-service ability',
            'POST beam/accounts/tokens/{id}/rotate' => 'APP-08b: a user\'s own API tokens; declare the self-service ability',
        ];
    }
}
