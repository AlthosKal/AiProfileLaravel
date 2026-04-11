<?php

namespace Modules\Transaction\Http\Data;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class GenerateDocumentProcessData extends Data
{
    public function __construct(
        #[Required]
        public string $correlation_id,
        #[Required]
        public string $code,
        #[Required]
        public string $output_file_name,
    ) {}
}
