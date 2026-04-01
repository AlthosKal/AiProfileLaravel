<?php

namespace Modules\Auth\Enums;

/**
 * Tipo de número de identificación del usuario
 *
 * Representa el tipo de identificación del usuario presente en el sistema
 */
enum IdentificationTypeEnum: string
{
    case CC = 'CC';
    case NIT = 'NIT';
    case PASSPORT = 'PASSPORT';
    case TI = 'TI';

}
