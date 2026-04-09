<?php

namespace Modules\Transaction\Imports\Sheets;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithValidation;
use Modules\Transaction\Enums\TransactionType;
use Modules\Transaction\Models\Transaction;

/**
 * Sheet de importación del módulo Transaction.
 *
 * Define cómo se mapea cada fila del archivo al modelo Transaction y las
 * reglas de validación que debe cumplir cada celda antes de persistirse.
 * El email del usuario autenticado se inyecta por constructor para asociar
 * cada registro importado a su propietario, sin requerirlo en el archivo.
 */
readonly class TransactionImportSheet implements ToModel, WithValidation
{
    /**
     * @param  string  $userEmail  Email del usuario autenticado extraído del request.
     */
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
