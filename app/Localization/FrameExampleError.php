<?php

declare(strict_types=1);

namespace App\Localization;

use Splicewire\Beam\Localization\Attributes\Message;
use Splicewire\Beam\Localization\CodedError;
use Splicewire\Beam\Localization\Concerns\IsCodedError;

/**
 * The satellite starter's own client-receivable refusals (localization 27-W2; SIGNATURES-27 8d8ec3c6 §2). A case's value is its
 * stable wire code; its key is derived (the host owns `App\`, so the key carries no namespace) and its English is the `#[Message]`.
 */
enum FrameExampleError: string implements CodedError
{
    use IsCodedError;

    #[Message('Unknown schema purpose [:purpose]; expected one of: write, read.')]
    case UnknownSchemaPurpose = 'unknown_schema_purpose';
}
