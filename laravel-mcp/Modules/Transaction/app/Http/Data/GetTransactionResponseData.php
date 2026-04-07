<?php

namespace Modules\Transaction\Http\Data;

use Spatie\LaravelData\Data;

class GetTransactionResponseData extends Data
{
    public function __construct(
        public string $id,
        public string $user_email,
        public string $name,
        public float $amount,
        public string $description,
        public string $type,
        public string $created_at,
        public string $updated_at,
    ) {}
}
