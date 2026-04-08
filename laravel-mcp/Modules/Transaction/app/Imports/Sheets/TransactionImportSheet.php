<?php

namespace Modules\Transaction\Imports\Sheets;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithValidation;
use Modules\Transaction\Enums\TransactionType;
use Modules\Transaction\Models\Transaction;

readonly class TransactionImportSheet implements ToModel, WithValidation
{
    public function __construct(private string $userEmail) {}

    /** @param array<int, mixed> $row */
    public function model(array $row): Transaction
    {
        return new Transaction([
            'user_email' => $this->userEmail,
            'name' => $row[0],
            'amount' => $row[1],
            'description' => $row[2],
            'type' => $row[3],
        ]);
    }

    /** @return array<array-key, mixed> */
    public function rules(): array
    {
        $validTypes = array_column(TransactionType::cases(), 'value');

        return [
            '0' => ['required', 'string'],
            '1' => ['required', 'integer'],
            '2' => ['required', 'string'],
            '3' => ['required', 'string', 'in:'.implode(',', $validTypes)],
        ];
    }
}
