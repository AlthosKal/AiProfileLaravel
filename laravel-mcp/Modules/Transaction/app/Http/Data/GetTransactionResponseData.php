<?php

namespace Modules\Transaction\Http\Data;

use Modules\Transaction\Enums\TransactionType;
use Spatie\LaravelData\Data;

class GetTransactionResponseData extends Data
{
    public function __construct(
        public string $user_email,
        public string $name,
        public int $amount,
        public string $description,
        public TransactionType $type,
    ) {}
}
