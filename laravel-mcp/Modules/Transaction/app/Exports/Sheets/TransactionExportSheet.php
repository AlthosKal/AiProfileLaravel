<?php

namespace Modules\Transaction\Exports\Sheets;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Modules\Transaction\Models\Transaction;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet de exportación del módulo Transaction.
 *
 * Define qué datos se exportan (query con filtros de fecha) y cómo se
 * representan en el archivo (columnas, encabezados, estilos y formatos).
 * Es el único archivo de exportación que conoce el dominio Transaction.
 *
 * @implements WithMapping<Transaction>
 */
readonly class TransactionExportSheet implements FromQuery, WithColumnFormatting, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  string  $dateFrom  Fecha de inicio del rango (Y-m-d).
     * @param  string  $dateTo  Fecha de fin del rango (Y-m-d).
     */
    public function __construct(
        private string $dateFrom,
        private string $dateTo,
    ) {}

    /** @return Builder<Transaction> */
    public function query(): Builder
    {
        return Transaction::query()
            ->whereDate('created_at', '>=', $this->dateFrom)
            ->whereDate('created_at', '<=', $this->dateTo)
            ->orderByDesc('id');
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            'Correo Usuario',
            'Nombre Transacción',
            'Monto',
            'Descripción',
            'Tipo Transacción',
            'Fecha de Creación',
        ];
    }

    /**
     * @param  Transaction  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row->user_email,
            $row->name,
            $row->amount / 100, // Si el monto está en centavos
            $row->description,
            $row->type,
            $row->created_at->format('d/m/Y'),
        ];
    }

    /** @return array<int, mixed> */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
        ];
    }
}
