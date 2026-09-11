<?php

declare(strict_types=1);

namespace App\Data\Pages;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class OperatorStaffData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}
