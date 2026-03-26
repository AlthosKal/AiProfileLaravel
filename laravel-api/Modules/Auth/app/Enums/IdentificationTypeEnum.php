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

    public function label(): string
    {
        return match ($this) {
            self::CC => 'Cedula de Ciudadanía',
            self::NIT => 'Número de Identificación',
            self::PASSPORT => 'Número de Pasaporte',
            self::TI => 'Tarjeta de Identidad',
        };
    }

    /**
     * Obtener todos los tipos como array para selects
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function toSelectArray(): array
    {
        return array_map(
            fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            self::cases(),
        );
    }
}
