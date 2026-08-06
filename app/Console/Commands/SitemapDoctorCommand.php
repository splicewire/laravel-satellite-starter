<?php

namespace App\Console\Commands;

use App\Doctor\OrphanedNavItemAudit;
use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Rushing\Doctor\DoctorStatus;
use Rushing\Doctor\Finding;

/**
 * `php artisan sitemap:doctor` — a starter-LOCAL doctor for the editable-sitemap example.
 *
 * The base `splicewire:beam:doctor` hard-codes its advisory audit list and offers no host
 * extension seam, so the starter surfaces its own #[AdminResource]-specific check here. It
 * follows the base doctor's contract exactly: renders each {@see Finding} as `<check>: <detail>`
 * and is ADVISORY — a Warn (an orphaned record nav link) renders yellow but never turns the run
 * red. Only a genuine failure would, and this audit emits none by design.
 */
class SitemapDoctorCommand extends Command
{
    protected $signature = 'sitemap:doctor';

    protected $description = 'Advisory: validate editable-sitemap (kind A) record nav links against real routes.';

    public function handle(Router $router): int
    {
        $finding = (new OrphanedNavItemAudit($router))->run();

        $this->render($finding);

        // Advisory: an orphaned-nav Warn is never a hard fail (mirrors the base beam doctor).
        return self::SUCCESS;
    }

    private function render(Finding $finding): void
    {
        match ($finding->status) {
            DoctorStatus::Pass => $this->components->info($finding->check.': '.$finding->detail),
            DoctorStatus::Warn => $this->components->warn($finding->check.': '.$finding->detail),
            DoctorStatus::Fail => $this->components->error($finding->check.': '.$finding->detail),
        };
    }
}
