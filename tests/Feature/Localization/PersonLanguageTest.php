<?php

namespace Tests\Feature\Localization;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Splicewire\Beam\Accounts\Models\Invitation;
use Splicewire\Beam\Accounts\Notifications\TeamInvitationNotification;
use Splicewire\Beam\Localization\LocaleScope;
use Splicewire\Beam\Localization\PseudoLocale;
use Tests\TestCase;

/**
 * localization 36, the ticket's done-when on a FRESH install of this starter (RefreshDatabase migrates this host's own
 * committed schema, add_preferred_locale_to_users_table included):
 * - a member who chooses the pseudo-locale sees marked text on the next page, and after signing in again;
 * - a notification another member's action sends them is marked;
 * - clearing returns them to automatic.
 *
 * Range review B12-5/6:
 * - The proof reads a REAL localized page (settings/profile) and asserts its 200 before inspecting copy. A route added
 *   after boot sits behind this host's deliberately-last beamUxSite catch-all.
 * - The notification is one whose copy is localized: the team invitation, whose subject is a beam-accounts key.
 *   ResetPassword's framework subject is not run through beam's translator, so it is not the proof.
 */
class PersonLanguageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        // The root view's Vite tags need a built manifest; a test renders the page without one.
        $this->withoutVite();
        // The proof host opts into the pseudo-locale (ticket 20's explicit opt-in); nothing else about the starter's
        // language configuration changes. en-XA joins the supported list only because of this flag.
        config(['beam.localization.pseudo' => true]);
    }

    private function choose(User $member, ?string $locale): void
    {
        $this->actingAs($member)
            ->patch(route('profile.update'), ['name' => $member->name, 'email' => $member->email, 'preferredLocale' => $locale])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }

    /** Every string in the page's Inertia props: the root view's `<script data-page>` JSON (Inertia v3), or its attribute. */
    private function pageStrings(TestResponse $response): array
    {
        $this->assertSame(200, $response->status(), 'the localized page must render before its copy is inspected');
        $html = (string) $response->getContent();
        $json = preg_match('#<script[^>]*\bdata-page\b[^>]*>(.*?)</script>#s', $html, $match) === 1
            ? $match[1]
            : (preg_match('/data-page="([^"]+)"/', $html, $match) === 1 ? html_entity_decode($match[1], ENT_QUOTES) : null);
        $this->assertNotNull($json, 'the page carries its Inertia data');
        $strings = [];
        $props = json_decode((string) $json, true)['props'] ?? [];
        $this->assertNotEmpty($props, 'the Inertia page data decodes to props');
        array_walk_recursive($props, function ($value) use (&$strings) {
            if (is_string($value)) {
                $strings[] = $value;
            }
        });

        return $strings;
    }

    /** Whether the page rendered in en-XA: the document language, and at least one marked product line. */
    private function assertMarkedPage(TestResponse $response, string $why): void
    {
        $marked = array_values(array_filter($this->pageStrings($response), fn (string $s): bool => PseudoLocale::isMarked($s)));
        $response->assertSee('lang="en-XA"', false);
        $this->assertNotEmpty($marked, $why);
    }

    public function test_a_member_who_chooses_the_pseudo_locale_reads_marked_text_next_page_and_after_signing_in_again(): void
    {
        $member = User::factory()->create();

        $this->choose($member, 'en-XA');
        $this->assertSame('en-XA', $member->fresh()->preferred_locale);

        $this->assertMarkedPage($this->actingAs($member)->get(route('profile.edit')), 'the next page carries marked product text');

        // Sign out, then in again: the choice is the person's, stored centrally, not the session's.
        $this->post(route('logout'));
        $this->assertGuest();
        $this->post(route('login.store'), ['email' => $member->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($member);
        $this->assertMarkedPage($this->get(route('profile.edit')), 'still marked after signing in again');
    }

    public function test_a_notification_another_members_action_sends_them_is_marked(): void
    {
        Notification::fake();
        $member = User::factory()->create();
        $this->choose($member, 'en-XA');
        $other = User::factory()->create(['name' => 'Olive']);
        // POST is not shadowed by the GET-only site catch-all; the review recorded this route being reached.
        Route::middleware('web')->post('/__invite-member/{user}', function (User $user) {
            // Another member's action invites this one. The copy is beam-accounts' invitation mail.
            $user->notify(new TeamInvitationNotification(
                invitation: new Invitation(['email' => $user->email, 'role' => 'member', 'team_id' => 'acme']),
                acceptUrl: 'https://starter.test/invitations/tok',
                teamName: 'Acme',
                inviterName: request()->user()?->name,
                expiresOn: 'Sat, Nov 14, 2026',
            ));

            return 'sent';
        });

        $this->actingAs($other)->post('/__invite-member/'.$member->getKey())->assertOk();

        Notification::assertSentTo($member, TeamInvitationNotification::class, function (TeamInvitationNotification $mail, array $channels, object $notifiable, ?string $locale) {
            $subject = LocaleScope::run((string) $locale, fn () => $mail->toMail($notifiable)->subject);

            return $locale === 'en-XA' && PseudoLocale::isMarked((string) $subject);
        });
    }

    public function test_clearing_the_choice_returns_the_member_to_automatic(): void
    {
        $member = User::factory()->create();
        $this->choose($member, 'en-XA');
        $this->choose($member, null);

        $this->assertNull($member->fresh()->preferred_locale);
        $response = $this->actingAs($member)->get(route('profile.edit'));
        $this->assertSame(200, $response->status());
        $response->assertDontSee('lang="en-XA"', false);
    }
}
