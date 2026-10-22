# Create and inspect a Satellite sitemap link

This example runs in the actual Laravel Satellite starter, using its existing `sitemap` resource,
UUID `schema_records` store and site-wide policy. It consumes two supplied local Frame candidates.
It does not pair with a remote platform, run an engine or adopt newer Beam authoring packages.

The normal `/sitemap` console remains writable for an authorized owner/admin. The example at
`/examples/sitemap-resource` creates links, lists them and opens an explicitly read-only detail.
Read-only is the presentation of that detail, not an immutable backing or a new permission policy.

## Prepare a fresh local host

Use Herd PHP8.4, Composer, Node22.13+ and pnpm9.15.3. Supply the directory containing the three
named local checkouts below, including this clean Satellite candidate, and an owned scratch parent.
These are supplied development checkpoints, not a registry release or remote-main installation.
The Satellite candidate's exact HEAD is captured before cloning; retain it with your results.

```bash
export CANDIDATE_ROOT=/absolute/path/to/supplied/checkouts
export DEMO_PARENT=/absolute/path/to/owned/scratch
export EXAMPLE_PORT=46910
export PHP_FRAME_REF=b9adc1ac13562f3e32d0c84926a1bd97c64e7f85
export JS_FRAME_REF=a773feb9604fe1f32433211f86334a43640770ee
export SATELLITE_SOURCE="$CANDIDATE_ROOT/wt-beam-dx-satellite-read-schema-20261022"
export SATELLITE_REF="$(git -C "$SATELLITE_SOURCE" rev-parse HEAD)"
export EXAMPLE_ROOT="$(mktemp -d "$DEMO_PARENT/satellite-resource.XXXXXX")"
git clone --local --no-hardlinks "$SATELLITE_SOURCE" "$EXAMPLE_ROOT/host"
git -C "$EXAMPLE_ROOT/host" checkout --detach "$SATELLITE_REF"
git clone --local --no-hardlinks "$CANDIDATE_ROOT/wt-beam-dx-frame-read-schema-20261021" "$EXAMPLE_ROOT/phpframe"
git -C "$EXAMPLE_ROOT/phpframe" checkout --detach "$PHP_FRAME_REF"
git clone --local --no-hardlinks "$CANDIDATE_ROOT/wt-beam-dx-schemastud-read-schema-20261021" "$EXAMPLE_ROOT/frame"
git -C "$EXAMPLE_ROOT/frame" checkout --detach "$JS_FRAME_REF"
cd "$EXAMPLE_ROOT/host"
XDEBUG_MODE=off herd composer install --no-scripts --no-interaction --prefer-dist
```

The committed PHP lock installs235 packages including development tools. Only PHP Frame changes
in the alternate local overlay below; the shipping manifest/lock remain untouched. All other
Satellite/connector/core/accounts/UX/workflow packages retain their committed references.
The alternate manifest disables the owner's broad local merge include and points only to the fresh
Frame clone, so no owner package checkout is linked into this host.

```bash
node --input-type=module <<'JS'
import {readFileSync,writeFileSync,copyFileSync,mkdirSync,realpathSync} from 'node:fs';
import {resolve} from 'node:path';
const root=process.env.EXAMPLE_ROOT, host=resolve(root,'host');
if(!/^[1-9][0-9]{3,4}$/.test(process.env.EXAMPLE_PORT)||Number(process.env.EXAMPLE_PORT)>65535)throw Error('Choose a valid free localhost port.');
const composer=JSON.parse(readFileSync('composer.json','utf8'));
composer.repositories.unshift({type:'path',url:resolve(root,'phpframe'),options:{symlink:true,versions:{'schemastud/laravel-frame':'dev-main'}}});
composer.extra['merge-plugin'].include=[];
writeFileSync('composer.example.json',JSON.stringify(composer,null,2)+'\n');
copyFileSync('composer.lock','composer.example.lock');
const database=resolve(host,'database/satellite-example.sqlite');
writeFileSync(database,'',{flag:'wx'});
let env=readFileSync('.env.example','utf8');
const values={APP_ENV:'local',APP_DEBUG:'false',APP_URL:`http://127.0.0.1:${process.env.EXAMPLE_PORT}`,DB_CONNECTION:'sqlite',DB_DATABASE:database,CACHE_STORE:'array',SESSION_DRIVER:'file',QUEUE_CONNECTION:'sync',MAIL_MAILER:'array',ACCOUNT_SEED_DEMO_USERS:'false',SCHEMA_BASE_URI:`http://127.0.0.1:${process.env.EXAMPLE_PORT}/schemas`,BEAM_SCRATCH_STORAGE_ROOT:resolve(host,'storage/example-files')};
for(const [key,value] of Object.entries(values)){const line=new RegExp(`^#?\\s*${key}=.*$`,'m');env=line.test(env)?env.replace(line,`${key}=${value}`):`${env}\n${key}=${value}\n`;}
writeFileSync('.env',env,{mode:0o600});
mkdirSync('storage/app/private',{recursive:true});
writeFileSync(resolve(root,'.satellite-example-owned'),JSON.stringify({host:realpathSync(host),database:realpathSync(database)}),{flag:'wx',mode:0o600});
JS
COMPOSER=composer.example.json XDEBUG_MODE=off herd composer update schemastud/laravel-frame --no-scripts --no-interaction --prefer-dist
XDEBUG_MODE=off herd php artisan key:generate
XDEBUG_MODE=off herd php artisan package:discover
XDEBUG_MODE=off herd php artisan popcorn:registries:cache
```

Prepare the JS toolchain **before** the compiler-dependent installer. Start from the committed
consumer lock. The small preparation helper builds only the supplied Frame source, using its exact
source-locked build tools and this consumer's installed runtime peers; it records inputs/output
hashes in `frame-tools/receipt.json`. It refuses an already-installed source checkout or tools dir.
It does not substitute newer Seam/UI/Beam peers. A missing peer is a concrete preparation failure.

```bash
pnpm install --frozen-lockfile
node scripts/prepare-sitemap-frame.mjs "$EXAMPLE_ROOT/frame" "$EXAMPLE_ROOT/frame-tools" "$JS_FRAME_REF"
node --input-type=module <<'JS'
import {readFileSync,writeFileSync} from 'node:fs';
import {resolve} from 'node:path';
const release=JSON.parse(readFileSync('package.json','utf8')).dependencies['@schemastud/frame'];
writeFileSync('pnpm.local.json',JSON.stringify({packages:{'@schemastud/frame':{release,path:resolve(process.env.EXAMPLE_ROOT,'frame/frame')}}},null,2)+'\n');
JS
scripts/pnpm-overlay on
XDEBUG_MODE=off herd php artisan help splicewire:satellite:install
XDEBUG_MODE=off herd php artisan splicewire:satellite:install --no-interaction
XDEBUG_MODE=off herd composer types:generate
XDEBUG_MODE=off pnpm run build
XDEBUG_MODE=off herd php scripts/sitemap-example-fixture.php storage/app/private/sitemap-actors.json
```

Every command must finish successfully. The Satellite installer chains its normal Beam installer,
then Satellite config/migrations; its locked help does not have the newer `--schema-authority` or
Satellite `--no-seed` flags. Schema authority is the explicit local env value above. Do not use
`--force` to overwrite committed host config. The existing nonproduction scratch storage knob keeps
compiled/mirrored bodies in this fresh host's output rather than changing authored source.
The normal package seed disables demo users here; the guarded helper creates four fresh private
actors and two teams only after installation. It refuses existing actors/output, a mismatched
ownership marker, nonlocal host/DB, and nonprivate output. Passwords are not printed.

`scripts/pnpm-overlay` maps the exact committed full-SHA Frame spec, including matching transitive
occurrences, to the supplied local build. The manifest stays byte-identical and no Git hidden flags
are used. The local lock change stays visible and is **not a shipping lock**. Keep the alternate
Composer manifest/lock and this JS overlay only in your disposable host. Do not commit them.

Start the supported server:

```bash
XDEBUG_MODE=off herd php artisan serve --host=127.0.0.1 --port="$EXAMPLE_PORT" --tries=1 --no-reload
```

## Follow the resource journey

Read the private `storage/app/private/sitemap-actors.json` locally. Use the normal login form with
its `owner` email/password; there is no login bypass. Open
`http://127.0.0.1:YOUR_PORT/examples/sitemap-resource`.

1. Choose **New link**. Enter Label, Path (`href`), Order and optional External URL. The create
   form uses the original four-field write schema; it never asks for an ID.
2. Save. The declared `SitemapData` input validates and packs only the four authored fields; the UUID is
   server-owned. Successful create returns to the list. Open the row.
3. Detail shows disabled ID plus the four original fields, no Save/Delete, and Back to list.
   Reload this URL. The UUID and fields must describe the same persisted row.
4. In the original `/sitemap` console, open that row, update Label/Path and Save as owner/admin.
   Return to the example and reload detail: it reflects the same backing record. This example
   has not disabled editing of the underlying resource.
5. Log in separately as `admin` and create a second link. Log in separately as `member`: read
   the global links, with no New control; direct writes remain forbidden. `other-owner` belongs
   to another team but reads site-wide configuration under the existing global policy. This is
   not the service-desk example's per-team row isolation, and no foreign-team404 is promised.

The supported routes are list, `/examples/sitemap-resource/new` and
`/examples/sitemap-resource/UUID`; all admit authenticated verified users and support direct
reload. A guest is redirected to ordinary login; JSON Frame requests are unauthorized. UI
capabilities come from the actor's real manifest. They are affordances; server policy is the lock.
The mismatched route-ID422 control and member/guest refusals must not create or mutate records. A forged ID or nested
payload must not replace the server UUID or the original payload packing rules.

## What the host adds

The model, table, policy and writable console retain their existing behavior. The shared
Sitemap producer repair lands first in the Beam starter, then is propagated here as a precise
source/test change: `input: SitemapData::class`, `MapsToModelAttributes`, and an explicit four-field
payload map. Validated DTO and direct array prepare calls share that map. Raw `id`, `payload`,
`meta` and unknown fields cannot become model attributes. The existing
generic Frame handler adds `id` to its payload projection. `App\Data\Examples\SitemapReadData`
inherits the four original fields and declares that computed ID, without a second ParticleResource.
`SitemapReadSchemaResolver` selects this declared schema only for `sitemap` + `purpose=read`,
using the configured generator's `forResponse()`. Default/explicit write and other resources
retain the previous resolver or fallback; ID never becomes a write input.

The host page composes public FrameProvider/ListShell/EditShell/createResourceTransport and
existing UI/shadcn slots inside the starter's AppLayout/QueryClient/theme. A small presentation
slot supplies human field titles without changing field names or validation. It restricts its own
surface to create/inspect while leaving the normal resource authoring route intact. Its transport
uses real raw Frame envelopes, same-origin sessions/CSRF, actual manifest capabilities and a stable
per-actor identity. No private Beam provider, fake can-all, global cache rewrite or newer authoring
stack is involved. The older packaged console adapter is intentionally unchanged.

Create requests `/frame/resources/sitemap/schema`. Explicit detail requests the same endpoint
with `?purpose=read`. FormMode and disabled interaction are separate from this projection axis.
The read schema has ID + four fields and matches the actual record envelope. A read-schema failure
shows an error and Retry; it never silently mounts a write form. A retry must recover from the
real endpoint and preserve the row. Invalid purpose is422 after normal reach checks. The owning
sources in the supplied bundle are `phpframe/docs/resource-schemas.md` and
`frame/frame/docs/schema-purpose.md`; these are local source paths, not additional website URLs.

The generated `App/Data/Examples` and `App/Data/Pages` modules describe the read/page contracts.
They are generated by the host's existing transformer, not hand-authored ambient declarations.
Type annotations do not imply runtime response validation or generic typed mutation bodies.
Malformed required or array-valued fields return422 before any write on create and update.
This is the declared input validation boundary; route-ID mismatch422, policy403 and guest401
are separate controls. The original unchanged producer returned500 for malformed input and
accepted nested payload replacement; the owning Sitemap regression covers both repairs.

The Satellite package/provider/installer is real; this local resource path makes no remote platform,
engine, billing, provisioning, publication or whole-host compatibility claim.

## Check and stop

```bash
XDEBUG_MODE=off herd php artisan test --filter='SatelliteSitemapExampleTest|SitemapInputContractTest|SitemapWriteAuthorizationTest|SitemapRecordFrameTest'
XDEBUG_MODE=off pnpm run build
```

Stop the server with Ctrl-C. After retaining the local overlay/build receipts you need, use the
supported `scripts/pnpm-overlay off` to restore the exact committed JS lock and remove its own hook;
it reruns the canonical frozen install. This does not turn the unpublished Frame feature into a
registry release. Keep or dispose of your owned local host deliberately; no owner site was used.
