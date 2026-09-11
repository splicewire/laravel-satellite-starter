<?php

declare(strict_types=1);

namespace App\Data\Pages;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Beam\Accounts\Data\Pages\PageEntryData;

#[TypeScript]
final class OperatorDashboardPageData extends Data
{
    public function __construct(
        public ?PageEntryData $entry,
        public Lazy|OperatorStaffData $staff,
        public Lazy|OperatorStatsData $stats,
    ) {}
}
