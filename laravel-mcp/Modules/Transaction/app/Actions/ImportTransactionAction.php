<?php

namespace Modules\Transaction\Actions;

use Illuminate\Http\UploadedFile;
use Modules\Shared\Enums\ExportFormat;
use Modules\Shared\Security\GatewayUser;
use Modules\Shared\Stores\CloudObjectStorage;
use Modules\Transaction\Builders\TransactionPathBuilder;
use Modules\Transaction\Enums\FileType;
use Modules\Transaction\Imports\Sheets\TransactionImportSheet;
use Modules\Transaction\Imports\TransactionImporter;
use Modules\Transaction\Models\File;

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
        // Importar el archivo al sistema
        $sheet = new TransactionImportSheet($user->email);
        $strategy = $format->resolveImportStrategy($sheet);
        $importer = new TransactionImporter($strategy);

        // El path se construye desde el contenido del archivo (hash SHA-256),
        // nunca desde el nombre provisto por el cliente.
        $path = TransactionPathBuilder::buildFromFile($file);

        $importer->import($file);
        // Almacenar el path en la base de datos para poder realizar su descarga
        File::create([
            'user_email' => $user->email,
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'type' => FileType::IMPORT,
        ]);
        CloudObjectStorage::store($path, $file);
    }
}
