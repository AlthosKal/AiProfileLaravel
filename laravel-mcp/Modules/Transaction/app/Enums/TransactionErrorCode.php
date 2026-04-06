<?php

namespace Modules\Transaction\Enums;

enum TransactionErrorCode : string
{
    // ID
    case IdRequired = 'id_required';
    // Name
    case NameRequired = 'name_required';

    // Amount
    case AmountRequired = 'amount_required';

    case DescriptionRequired = 'description_required';

    case TransactionTypeRequired = 'transaction_type_required';

    case TransactionTypeInvalid = 'transaction_type_invalid';

    case TransactionNotFound = 'transaction_not_found';
}
