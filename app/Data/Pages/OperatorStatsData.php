<?php

declare(strict_types=1);

namespace App\Data\Pages;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class OperatorStatsData extends Data
{
    public function __construct(
        public int $users,
        public int $sitemaps,
        public int $entries,
    ) {}
}
