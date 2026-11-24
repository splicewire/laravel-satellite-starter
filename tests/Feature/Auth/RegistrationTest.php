<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Mockery;
use Splicewire\Beam\Accounts\Fortify\CreateNewUser;
use Splicewire\Beam\Agreements\AssentRecorder;
use Splicewire\Beam\Agreements\Data\OutstandingRequirementData;
use Splicewire\Beam\Agreements\RequirementAdmission;
use Splicewire\Beam\Agreements\RequirementDeclarations;
use Splicewire\Beam\Agreements\RequirementResolver;
use Splicewire\Beam\Agreements\Requires;
use Tests\TestCase;

/**
 * purchase-walkthrough BUY-05 (M10): with ACCOUNT_REGISTRATION=open, this starter's registration door is open, and a
 * registrant is created through beam-accounts' door-checked action, not a host copy.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected ?string $accountRegistration = 'open';

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'passwordConfirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_a_registrant_is_created_through_the_door_checked_action()
    {
        $this->assertInstanceOf(CreateNewUser::class, app(CreatesNewUsers::class));
        $this->assertFileDoesNotExist(app_path('Actions/Fortify/CreateNewUser.php'));
    }

    public function test_null_agreement_member_refuses_registration_before_creating_the_user(): void
    {
        $declarations = Mockery::mock(RequirementDeclarations::class);
        $declarations->shouldReceive('requirementsFor')
            ->once()
            ->with('auth.register')
            ->andReturn([new Requires('terms')]);
        $declarations->shouldReceive('declares')->andReturnFalse();
        app()->instance(RequirementDeclarations::class, $declarations);

        $resolver = Mockery::mock(RequirementResolver::class);
        $resolver->shouldReceive('outstanding')
            ->once()
            ->with(null, 'auth.register', null, [])
            ->andReturn([new OutstandingRequirementData(
                key: 'terms',
                version: 1,
                digest: 'frozen-digest',
                kind: 'contract',
                title: 'Terms',
                scope: 'party',
                subject: null,
                readUrl: '/agreements/terms',
                acceptOperation: null,
                member: 'agreementMember',
            )]);
        app()->instance(RequirementAdmission::class, new RequirementAdmission($resolver));

        $recorder = Mockery::mock(AssentRecorder::class);
        $recorder->shouldNotReceive('record');
        app()->instance(AssentRecorder::class, $recorder);

        $this->postJson(route('register.store'), [
            'name' => 'Terms Refusal',
            'email' => 'terms-refusal@example.test',
            'password' => 'password',
            'passwordConfirmation' => 'password',
            'agreementMember' => null,
        ])->assertStatus(428)
            ->assertJsonPath('requirements.0.key', 'terms');

        $this->assertDatabaseMissing('users', ['email' => 'terms-refusal@example.test']);
    }
}
