<?php

// Disposable local actors only. Passwords go to a new private file, never stdout.
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$output = $argv[1] ?? null;
$database = config('database.connections.sqlite.database');
$url = parse_url((string) config('app.url'));
$marker = base_path('../.satellite-example-owned');
$markerBytes = is_file($marker) && ! is_link($marker) ? file_get_contents($marker) : false;
$ownership = is_string($markerBytes) ? json_decode($markerBytes, true) : null;
if (count($argv) !== 2 || ! is_string($output) || file_exists($output) || is_link($output)
    || ! app()->environment('local') || ($url['scheme'] ?? '') !== 'http' || ($url['host'] ?? '') !== '127.0.0.1' || ! isset($url['port'])
    || config('database.default') !== 'sqlite' || ! is_string($database) || ! is_file($database)
    || basename($database) !== 'satellite-example.sqlite' || realpath(dirname($database)) !== realpath(database_path())
    || realpath(dirname($output)) !== realpath(storage_path('app/private'))
    || ! is_array($ownership) || ($ownership['host'] ?? null) !== realpath(base_path()) || ($ownership['database'] ?? null) !== realpath($database)
    || config('session.driver') !== 'file' || config('queue.default') !== 'sync' || config('mail.default') !== 'array'
    || User::query()->exists() || Team::query()->exists()) {
    fwrite(STDERR, "Use a new owned local Satellite SQLite fixture and a new private output file.\n");
    exit(1);
}

$file = fopen($output, 'x');
if ($file === false) {
    exit(1);
}
chmod($output, 0600);
try {
    $actors = DB::transaction(function (): array {
        $actors = [];
        $users = [];
        foreach (['owner', 'admin', 'member', 'other-owner'] as $name) {
            $password = bin2hex(random_bytes(20));
            $user = User::factory()->create(['name' => 'Satellite '.$name, 'email' => $name.'@satellite-example.invalid', 'password' => $password, 'email_verified_at' => now()]);
            $users[$name] = $user;
            $actors[$name] = ['id' => (string) $user->getKey(), 'email' => $user->email, 'password' => $password];
        }
        $provisioner = app(TeamProvisioner::class);
        $team = $provisioner->createTeamFor($users['owner'], 'Satellite example');
        foreach (['admin' => Role::Admin, 'member' => Role::Member] as $name => $role) {
            $user = $users[$name];
            $provisioner->addMember($user, $team, $role);
            $user->forceFill(['current_team_id' => $team->getKey()])->save();
        }
        $other = $provisioner->createTeamFor($users['other-owner'], 'Another local team');

        return ['actors' => $actors, 'teams' => ['shared' => $team->getKey(), 'other' => $other->getKey()]];
    });
    fwrite($file, json_encode($actors, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
    fclose($file);
    echo "Local actors written privately.\n";
} catch (Throwable $error) {
    fclose($file);
    unlink($output);
    throw $error;
}
