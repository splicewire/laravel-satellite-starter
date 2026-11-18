<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Splicewire\Beam\Install\MigrationFiles;

uses(Tests\TestCase::class);

it('resolves every published host migration onto its existing copy', function () {
    // MigrationFiles::pathsFor() describes the central migrator and intentionally excludes the
    // tenant directory. Publication duplication is about every migration the host tracks, so add
    // shared/ and tenant/ explicitly rather than letting a central-only inventory hide a duplicate.
    $paths = array_values(array_unique(array_merge(
        MigrationFiles::pathsFor(app()),
        array_filter([
            database_path('migrations/shared'),
            database_path('migrations/tenant'),
        ], 'is_dir'),
    )));

    $files = MigrationFiles::in($paths);
    $offered = [];
    $resolved = [];
    $hostCopies = [];

    // Check every registered publisher: a fresh destination is a defect regardless of which
    // family provider (or third-party provider) constructs it.
    foreach (ServiceProvider::pathsToPublish() as $source => $destination) {
        if (! str_ends_with($source, '.php.stub')) {
            continue;
        }

        $stem = basename($source, '.php.stub');
        $existing = array_values(array_filter($files, fn (array $file): bool => $file[1] === $stem));

        if ($existing === []) {
            continue; // A newly introduced package migration has no host copy yet.
        }

        $offered[$stem] = true;
        $hostCopies[$stem] = $existing;

        foreach ($existing as $file) {
            if (realpath($destination) === realpath($file[2])) {
                $resolved[$stem] = true;
            }
        }
    }

    // Spatie publishes this third-party migration to central migrations/, while the host keeps
    // its copy in shared/ so the tenant pass can see it. No family provider re-publishes it.
    $documentedExceptions = ['create_permission_tables' => 1];

    foreach ($offered as $stem => $_) {
        $expectedCopies = $documentedExceptions[$stem] ?? 1;
        expect($hostCopies[$stem])->toHaveCount(
            $expectedCopies,
            $stem.' has an unexpected number of runnable host copies',
        );
    }

    foreach ($documentedExceptions as $exception => $_) {
        expect($offered)->toHaveKey($exception);
        expect($hostCopies[$exception])->toHaveCount(1);
    }

    $wouldDuplicate = array_values(array_diff(
        array_keys(array_diff_key($offered, $resolved)),
        array_keys($documentedExceptions),
    ));

    expect($wouldDuplicate)->toBe([]);
    expect($offered)->not->toBeEmpty();

    // Account migrations are installed in this host and exercise both the provider scan and the
    // additional shared/ inventory. These controls fail if either part of the probe is omitted.
    expect($resolved)->toHaveKey('create_teams_table');
    expect($resolved)->toHaveKey('add_slug_to_teams_table');
});
