<?php

namespace Tests\Feature;

use App\Data\Examples\SitemapReadData;
use App\Frame\Examples\SitemapReadSchemaResolver;
use App\Models\SitemapRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use ReflectionClass;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Schemastud\Frame\Contracts\ResourceSchemaResolver;
use Schemastud\Frame\Registry\ResourceDefinition;
use Spatie\Permission\PermissionRegistrar;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Splicewire\Beam\Particle\Attributes\ParticleResource;
use Tests\TestCase;

class SatelliteSitemapExampleTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, User} */
    private function seats(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = app(TeamProvisioner::class)->personalTeamFor($owner);
        app(TeamProvisioner::class)->addMember($member, $team, Role::Member);
        foreach ([$owner, $member] as $user) {
            $user->forceFill(['current_team_id' => $team->getKey()])->save();
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
        app(PermissionRegistrar::class)->setPermissionsTeamId($team->getKey());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [$owner, $member];
    }

    public function test_read_schema_matches_the_existing_record_identity_and_payload(): void
    {
        [$owner] = $this->seats();
        $this->actingAs($owner);
        $input = ['label' => 'Guides', 'href' => '/docs', 'order' => 3, 'externalUrl' => null];
        $forgedId = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $created = $this->postJson('/frame/resources/sitemap', $input + ['id' => $forgedId, 'payload' => ['label' => 'Smuggled'], 'meta' => ['injected' => true], 'unexpected' => 'ignored'])->assertOk()->json('data');
        $id = $created['id'];
        $this->assertNotSame($forgedId, $id);
        $this->putJson('/frame/resources/sitemap/records/'.$id, $input + ['id' => $forgedId])->assertUnprocessable();
        $this->assertSame(1, SitemapRecord::count());
        $this->assertSame($input, SitemapRecord::findOrFail($id)->payload);
        $this->getJson("/frame/resources/sitemap/records/{$id}")->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.label', 'Guides');
        $write = $this->getJson('/frame/resources/sitemap/schema')->assertOk()->json();
        $explicit = $this->getJson('/frame/resources/sitemap/schema?purpose=write')->assertOk()->json();
        $read = $this->getJson('/frame/resources/sitemap/schema?purpose=read')->assertOk()->json();
        $this->assertSame($write, $explicit);
        $this->assertArrayNotHasKey('id', $write['properties']);
        $this->assertEqualsCanonicalizing(['id', ...array_keys($input)], array_keys($read['properties']));
        $this->getJson('/frame/resources/sitemap/schema?purpose=unknown')->assertUnprocessable();
        $this->assertSame([], (new ReflectionClass(SitemapReadData::class))->getAttributes(ParticleResource::class));
        $manifest = $this->getJson('/frame/manifest')->assertOk()->json();
        $this->assertCount(1, array_filter($manifest['resources'], fn (array $r): bool => $r['key'] === 'sitemap'));
    }

    public function test_members_read_global_records_but_cannot_write_and_guests_are_refused(): void
    {
        [$owner, $member] = $this->seats();
        $row = $this->actingAs($owner)->postJson('/frame/resources/sitemap', ['label' => 'Docs', 'href' => '/docs'])->assertOk()->json('data');
        $before = SitemapRecord::findOrFail($row['id'])->getRawOriginal();
        $this->actingAs($member)->getJson('/frame/resources/sitemap/schema?purpose=read')->assertOk();
        $this->getJson('/frame/resources/sitemap/records/'.$row['id'])->assertOk()->assertJsonPath('data.label', 'Docs');
        $this->postJson('/frame/resources/sitemap', ['label' => 'Denied', 'href' => '/denied'])->assertForbidden();
        $this->putJson('/frame/resources/sitemap/records/'.$row['id'], ['label' => 'Denied', 'href' => '/denied'])->assertForbidden();
        $this->assertSame($before, SitemapRecord::findOrFail($row['id'])->getRawOriginal());
        $this->assertSame(1, SitemapRecord::count());
        auth()->forgetGuards();
        $this->getJson('/frame/resources/sitemap/schema?purpose=read')->assertUnauthorized();
        $this->postJson('/frame/resources/sitemap', ['label' => 'Guest', 'href' => '/guest'])->assertUnauthorized();
    }

    public function test_example_routes_have_declared_props_and_original_edit_stays_writable(): void
    {
        $this->withoutVite();
        [$owner] = $this->seats();
        $row = $this->actingAs($owner)->postJson('/frame/resources/sitemap', ['label' => 'Before', 'href' => '/before'])->assertOk()->json('data');
        foreach (['' => 'list', '/new' => 'create', '/'.$row['id'] => 'read'] as $suffix => $mode) {
            $this->get('/examples/sitemap-resource'.$suffix)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('examples/sitemap-resource')->where('mode', $mode)
                ->where('listHref', '/examples/sitemap-resource')->where('schemaUrl', '/frame/resources/sitemap/schema'));
        }
        $this->putJson('/frame/resources/sitemap/records/'.$row['id'], ['label' => 'After', 'href' => '/after'])->assertOk();
        $this->getJson('/frame/resources/sitemap/records/'.$row['id'])->assertOk()->assertJsonPath('data.label', 'After');
        $this->get('/sitemap/'.$row['id'])->assertOk()->assertInertia(fn (Assert $page) => $page->component('frame/console'));
    }

    public function test_narrow_resolver_delegates_write_and_other_resources(): void
    {
        $definition = app(ResourceRegistry::class)->get('sitemap');
        $next = new class implements ResourceSchemaResolver
        {
            public function schema(ResourceDefinition $resource, Request $request): ?array
            {
                return ['delegated' => $resource->key];
            }
        };
        $resolver = new SitemapReadSchemaResolver($next);
        $this->assertSame(['delegated' => 'sitemap'], $resolver->schema($definition, Request::create('/')));
        $this->assertSame(['delegated' => 'sitemap'], $resolver->schema($definition, Request::create('/?purpose=write')));
        $other = clone $definition;
        $other->key = 'other';
        $this->assertSame(['delegated' => 'other'], $resolver->schema($other, Request::create('/?purpose=read')));
        $this->assertNull((new SitemapReadSchemaResolver)->schema($other, Request::create('/?purpose=read')));
    }
}
