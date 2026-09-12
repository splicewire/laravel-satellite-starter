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
        /**
         * The operator realm's own door list. Not `Lazy`: it is the page's navigation, so deferring
         * it would render a landing with no way out until a second round trip completed.
         *
         * @var list<OperatorSurfaceData>
         */
        public array $surfaces = [],
    ) {}
}
