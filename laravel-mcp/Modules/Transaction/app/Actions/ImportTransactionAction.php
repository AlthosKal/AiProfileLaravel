<?php

namespace Modules\Transaction\Actions;

use Illuminate\Http\UploadedFile;
use Modules\Shared\Enums\ExportFormat;
use Modules\Shared\Security\GatewayUser;
use Modules\Transaction\Imports\Sheets\TransactionImportSheet;
use Modules\Transaction\Imports\TransactionImporter;

/**
 * Importa transacciones desde un archivo subido por el usuario.
 *
 * El email del usuario autenticado se inyecta en cada fila importada
 * para asociar los registros al propietario correcto.
 */
readonly class ImportTransactionAction
{
    public function import(ExportFormat $format, UploadedFile $file, GatewayUser $user): void
    {
        $sheet = new TransactionImportSheet($user->email);
        $strategy = $format->resolveImportStrategy($sheet);
        $importer = new TransactionImporter($strategy);

        $importer->import($file);
    }
}
