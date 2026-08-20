<?php

namespace App\Account;

use Illuminate\Contracts\Auth\Authenticatable;
use Splicewire\Beam\Accounts\Contracts\AccountShellProvider;
use Splicewire\Beam\Accounts\Data\AccountData;
use Splicewire\Beam\Accounts\Data\AccountShellData;
use Splicewire\Beam\Accounts\Data\MetricData;
use Splicewire\Beam\Accounts\Data\PlanData;
use Splicewire\Beam\Accounts\Data\ProfileData;

/**
 * The starter's account-shell provider — fills the generic beam-accounts SHAPE
 * ({@see AccountShellData}) with neutral demo values so the packaged `<AccountShell>` renders a plan
 * chip + public profile OOTB. A real host swaps these for its own product data; the SHAPE and the
 * front-end render do not change.
 *
 * beam-accounts binds {@see \Splicewire\Beam\Accounts\Support\NullAccountShellProvider} by default
 * (returns null → the shell degrades gracefully). Binding this one is the host's "wrote only config"
 * step to prove the account realm renders.
 */
class StarterAccountShell implements AccountShellProvider
{
    public function shellFor(?Authenticatable $user): ?AccountShellData
    {
        if ($user === null) {
            return null;
        }

        $email = (string) ($user->getAttribute('email') ?? '');
        $name = (string) ($user->getAttribute('name') ?? $email);
        $handle = '@'.strtok($email !== '' ? $email : 'you', '@');

        return new AccountShellData(
            plan: new PlanData(
                tier: 'free',
                label: 'Free',
                credits: null,
                max: null,
            ),
            profile: new ProfileData(
                handle: $handle,
                avatar: $this->initials($name),
                metrics: [
                    new MetricData(label: 'PROJECTS', value: '0'),
                    new MetricData(label: 'MEMBERS', value: '1'),
                ],
            ),
            account: new AccountData(
                email: $email,
                paymentMethodLabel: null,
            ),
            upsells: [],
        );
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = array_map(fn (string $p): string => mb_strtoupper(mb_substr($p, 0, 1)), array_filter($parts));

        return implode('', array_slice($letters, 0, 2)) ?: '·';
    }
}
