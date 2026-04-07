<?php

namespace Modules\Transaction\Actions;

use Illuminate\Http\UploadedFile;
use Modules\Shared\Enums\ExportFormat;
use Modules\Shared\Security\GatewayUser;
use Modules\Transaction\Imports\Sheets\TransactionImportSheet;
use Modules\Transaction\Imports\TransactionImporter;

readonly class ImportTransactionAction
{
    public function import(ExportFormat $format, UploadedFile $file): void
    {
        /** @var GatewayUser $user */
        $user = request()->user();
        $userEmail = $user->email;

        $sheet = new TransactionImportSheet($userEmail);
        $strategy = $format->resolveImportStrategy($sheet);
        $importer = new TransactionImporter($strategy);

        $importer->import($file);
    }
}
