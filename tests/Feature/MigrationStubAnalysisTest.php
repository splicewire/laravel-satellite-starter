<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

uses(Tests\TestCase::class);

it('analyses migration stubs before they are published as PHP files', function () {
    $directory = sys_get_temp_dir().'/starter-stub-analysis-'.bin2hex(random_bytes(8));
    File::makeDirectory($directory);
    $stub = $directory.'/migration.php.stub';
    $source = <<<'PHP'
<?php

class MigrationStubProbe
{
    public function owner(object $row): mixed
    {
        return $row->user_id;
    }
}
PHP;

    $analyse = function (?string $path = null): Process {
        $process = new Process([
            PHP_BINARY, '-d', 'memory_limit=1G', base_path('vendor/bin/phpstan'),
            'analyse', '--configuration='.base_path('phpstan.neon'),
            '--error-format=json', '--no-progress',
            ...($path === null ? ['--debug'] : [$path]),
        ], base_path(), ['XDEBUG_MODE' => 'off', 'PAO_DISABLE' => '1']);
        $process->setTimeout(60);
        $process->run();

        return $process;
    };

    try {
        File::put($stub, $source);
        $broken = $analyse($directory);
        expect($broken->getExitCode())->toBe(1)
            ->and($broken->getOutput())->toContain('property.notFound', 'migration.php.stub');

        File::put($stub, str_replace('$row->user_id;', '$row->user_id ?? null;', $source));
        $fixed = $analyse($directory);
        expect($fixed->getExitCode())->toBe(0, $fixed->getOutput().$fixed->getErrorOutput());
        $report = json_decode($fixed->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        expect($report['totals']['file_errors'])->toBe(0);

        // No CLI path override: removing package sources from the normal gate must fail too.
        $configured = $analyse();
        expect($configured->getExitCode())->toBe(0, $configured->getOutput().$configured->getErrorOutput())
            ->and($configured->getOutput())->toContain(
                'laravel-beam-accounts/database/migrations/shared/add_slug_to_teams_table.php.stub',
                'laravel-beam-accounts/database/migrations/shared/create_permission_tables.php.stub',
            );
    } finally {
        File::deleteDirectory($directory);
    }
});
