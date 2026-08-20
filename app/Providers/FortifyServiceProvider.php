<?php

namespace App\Providers;

/* @chisel-registration */

use App\Actions\Fortify\CreateNewUser;
/* @end-chisel-registration */
use App\Actions\Fortify\ResetUserPassword;
use App\Beam\EntryBody;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Splicewire\Beam\Accounts\Facades\BeamAccounts;
use Splicewire\Beam\Accounts\Facades\BeamDemo;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(EntryBody $entryBody): void
    {
        $this->configureActions();
        $this->configureViews($entryBody);
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        /* @chisel-registration */
        Fortify::createUsersUsing(CreateNewUser::class);
        /* @end-chisel-registration */
    }

    /**
     * Configure Fortify views.
     *
     * theme-entries-and-authoring STR-03: every view renders the SAME beam-ux entry-resolution page
     * (`auth/entry`), differentiated by `slug` — the `BeamUxEntry` `App\Beam\EntryBody::forSlug()`
     * resolves. Every prop these views computed before (`canResetPassword`, `status`, `demoAccounts`,
     * `email`, `token`, `passwordRules`) still flows exactly as before; the sealed form island reads
     * them via `usePage()` instead of receiving them as direct page-component props (it's no longer the
     * top-level Inertia page — `resources/js/editor/registry.tsx`). Routes/controllers/business guards
     * (password-reset token validity, 2FA challenge session, etc.) are untouched — view-selection only.
     */
    private function configureViews(EntryBody $entryBody): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/entry', [
            'slug' => 'login',
            'body' => $entryBody->forSlug('login'),
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
            // Controller-provided quick demo sign-in — the OOTB beam-accounts login-as affordance
            // (Splicewire\Beam\Accounts\Facades\BeamDemo + the signed `users/{id}/op/login-as` route).
            // Empty in production / when demo is off, so the login page's demo block simply doesn't render.
            'demoAccounts' => $this->demoAccounts(),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/entry', [
            'slug' => 'reset-password',
            'body' => $entryBody->forSlug('reset-password'),
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/entry', [
            'slug' => 'forgot-password',
            'body' => $entryBody->forSlug('forgot-password'),
            'status' => $request->session()->get('status'),
        ]));

        /* @chisel-email-verification */
        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/entry', [
            'slug' => 'verify-email',
            'body' => $entryBody->forSlug('verify-email'),
            'status' => $request->session()->get('status'),
        ]));
        /* @end-chisel-email-verification */

        /* @chisel-registration */
        Fortify::registerView(fn () => Inertia::render('auth/entry', [
            'slug' => 'register',
            'body' => $entryBody->forSlug('register'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));
        /* @end-chisel-registration */

        /* @chisel-2fa */
        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/entry', [
            'slug' => 'two-factor-challenge',
            'body' => $entryBody->forSlug('two-factor-challenge'),
        ]));
        /* @end-chisel-2fa */

        /* @chisel-password-confirmation */
        Fortify::confirmPasswordView(fn () => Inertia::render('auth/entry', [
            'slug' => 'confirm-password',
            'body' => $entryBody->forSlug('confirm-password'),
        ]));
        /* @end-chisel-password-confirmation */
    }

    /**
     * The demo sign-in buttons for the login page — the OOTB beam-accounts login-as affordance. Each is a
     * SIGNED link to the particle operation `users/{id}/op/login-as` (the signature is ignored in
     * local/testing but keeps the links valid in a preview deploy, where the op requires one). Empty ⇒
     * no buttons: the set is whichever `BeamDemo::keys()` have a seeded user when `BeamDemo::enabled()`
     * (non-production by default), else nothing. The subjects are provisioned by the package
     * `DemoTeamSeeder` (called from DatabaseSeeder).
     *
     * @return list<array{key: string, label: string, url: string}>
     */
    private function demoAccounts(): array
    {
        if (! BeamDemo::enabled()) {
            return [];
        }

        // The link targets the particle operation `users/{id}/op/login-as`, which resolves {id}
        // against the user model — so the demo subject KEY has to become a user id here. One query
        // for the whole roster; a key with no seeded user simply gets no button.
        $emails = array_combine(BeamDemo::keys(), array_map(BeamDemo::email(...), BeamDemo::keys()));

        $users = BeamAccounts::userModel()::query()
            ->whereIn('email', array_values($emails))
            ->get()
            ->keyBy('email');

        $urls = [];

        foreach ($emails as $key => $email) {
            if ($user = $users->get($email)) {
                $urls[$key] = URL::signedRoute('users.op.login-as', ['id' => $user->getKey()]);
            }
        }

        return array_map(fn (string $key): array => [
            'key' => $key,
            'label' => BeamDemo::name($key),
            'url' => $urls[$key],
        ], array_keys($urls));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        /* @chisel-2fa */
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
        /* @end-chisel-2fa */

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        /* @chisel-passkeys */
        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
        /* @end-chisel-passkeys */
    }
}
