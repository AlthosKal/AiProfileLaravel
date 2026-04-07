<?php

namespace Modules\Transaction\Enums;

enum TransactionSuccessCode: string
{
    case TransactionsImportedSuccessfully = 'transactions_imported_successfully';
    case TransactionListedSuccessfully = 'transaction_listed_successfully';
    case TransactionCreatedSuccessfully = 'transaction_created_successfully';
    case TransactionUpdatedSuccessfully = 'transaction_updated_successfully';
    case TransactionDeletedSuccessfully = 'transaction_deleted_successfully';
}
