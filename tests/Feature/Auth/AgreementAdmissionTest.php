<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Rushing\Agreements\Ledger;
use Rushing\Agreements\Models\LedgerEntry;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Invitation;
use Splicewire\Beam\Accounts\Models\Membership;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Splicewire\Beam\Agreements\Data\AgreementMemberData;
use Splicewire\Beam\Agreements\Registry\AgreementDefinition;
use Splicewire\Beam\Agreements\Registry\AgreementRegistry;
use Splicewire\Beam\Agreements\Registry\RecordSource;
use Splicewire\Beam\Agreements\Requirements\AttachedRequirement;
use Splicewire\Beam\Agreements\Requirements\RequirementRegistry;
use Tests\TestCase;

/** The host's HTTP registration and invitation paths against the installed Agreements provider and ledger. */
final class AgreementAdmissionTest extends TestCase
{
    use RefreshDatabase;

    protected ?string $accountRegistration = 'open';

    private AgreementMemberData $member;

    protected function setUp(): void
    {
        parent::setUp();

        $definition = new AgreementDefinition(
            key: 'terms',
            kind: 'contract',
            title: 'Terms of Service',
            source: new RecordSource(static fn (): string => 'Frozen terms for the registration flow.'),
        );
        app(AgreementRegistry::class)->register($definition);
        $version = app(Ledger::class)->freeze($definition, 'v1', now()->subDay(), true);
        $this->member = new AgreementMemberData('terms', 1, $version->digest);
    }

    public function test_registration_without_the_required_member_is_refused_before_user_or_ledger_write(): void
    {
        $this->requireFor('auth.register');

        $this->postJson(route('register.store'), $this->registration('missing-member@example.test'))
            ->assertStatus(428)
            ->assertJsonPath('requirements.0.key', 'terms');

        $this->assertDatabaseMissing('users', ['email' => 'missing-member@example.test']);
        $this->assertDatabaseCount('agreement_entries', 0);
    }

    public function test_registration_records_one_party_assent_visible_in_the_account_agreements_resource(): void
    {
        $this->requireFor('auth.register');
        $email = 'registered-with-terms@example.test';

        $this->post(route('register.store'), $this->registration($email, [$this->memberInput()]))
            ->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', $email)->sole();
        $entry = LedgerEntry::query()->sole();
        $this->assertSame((string) $user->getKey(), $entry->party_id);
        $this->assertNull($entry->subject_id, 'party-scoped assent has no ledger subject');
        $this->assertSame('auth.register', $entry->behavior);
        $this->assertSame('form', $entry->method);

        $this->actingAs($user)->get('/account/agreements')->assertOk()->assertInertia(
            fn ($page) => $page->component('account/agreements')->where('resource', 'agreement-entries'),
        );
        $this->actingAs($user)->getJson('/frame/resources/agreement-entries')
            ->assertOk()
            ->assertJsonFragment(['id' => (string) $entry->getKey()]);
    }

    public function test_a_new_invitee_cannot_redeem_without_terms_and_then_records_the_party_entry(): void
    {
        $this->requireFor('invitations.redeem');
        [$owner, $team] = $this->team();
        $email = 'new-invitee@example.test';
        $invitation = $this->invitation($owner, $team, $email);

        $this->post(route('register.store'), $this->registration($email))
            ->assertRedirect(route('dashboard', absolute: false));
        $invitee = User::query()->where('email', $email)->sole();

        $this->actingAs($invitee)->postJson(route('invitations.redeem', ['id' => $invitation->token]))
            ->assertStatus(428)
            ->assertJsonPath('requirements.0.key', 'terms');

        $this->assertFalse(Membership::query()->where('team_id', $team->getKey())->where('user_id', $invitee->getKey())->exists());
        $this->assertNull($invitation->fresh()->accepted_at);
        $this->assertDatabaseCount('agreement_entries', 0);

        $this->actingAs($invitee)->postJson(route('invitations.redeem', ['id' => $invitation->token]), [
            'agreementMembers' => [$this->memberInput()],
        ])->assertOk();

        $entry = LedgerEntry::query()->sole();
        $this->assertSame((string) $invitee->getKey(), $entry->party_id);
        $this->assertNull($entry->subject_id, 'party-scoped assent has no ledger subject');
        $this->assertSame('invitations.redeem', $entry->behavior);
        $this->assertSame('form', $entry->method);
        $this->assertTrue(Membership::query()->where('team_id', $team->getKey())->where('user_id', $invitee->getKey())->exists());
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_an_existing_invitee_can_redeem_with_one_real_ledger_entry(): void
    {
        $this->requireFor('invitations.redeem');
        [$owner, $team] = $this->team();
        $invitee = User::factory()->create(['email' => 'existing-invitee@example.test']);
        $invitation = $this->invitation($owner, $team, $invitee->email);

        $this->actingAs($invitee)->postJson(route('invitations.redeem', ['id' => $invitation->token]), [
            'agreementMembers' => [$this->memberInput()],
        ])->assertOk();

        $entry = LedgerEntry::query()->sole();
        $this->assertSame((string) $invitee->getKey(), $entry->party_id);
        $this->assertSame('invitations.redeem', $entry->behavior);
        $this->assertSame('form', $entry->method);
        $this->assertTrue(Membership::query()->where('team_id', $team->getKey())->where('user_id', $invitee->getKey())->exists());
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    private function requireFor(string $behavior): void
    {
        app(RequirementRegistry::class)->attach($behavior, 'terms', AttachedRequirement::PARTY);
    }

    private function registration(string $email, array $members = []): array
    {
        return array_filter([
            'name' => 'Terms User',
            'email' => $email,
            'password' => 'password',
            'passwordConfirmation' => 'password',
            'agreementMembers' => $members === [] ? null : $members,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function memberInput(): array
    {
        return ['key' => $this->member->key, 'version' => $this->member->version, 'digest' => $this->member->digest];
    }

    /** @return array{User, Team} */
    private function team(): array
    {
        $owner = User::factory()->create();

        return [$owner, app(TeamProvisioner::class)->createTeamFor($owner, 'Agreement Test Team')];
    }

    private function invitation(User $owner, Team $team, string $email): Invitation
    {
        return Invitation::create([
            'team_id' => (string) $team->getKey(),
            'email' => $email,
            'role' => Role::Member->value,
            'token' => bin2hex(random_bytes(24)),
            'invited_by' => $owner->getKey(),
        ]);
    }
}
