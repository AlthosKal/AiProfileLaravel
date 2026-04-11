<?php

namespace Modules\Transaction\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Transaction\Database\Factories\FileFactory;
use Modules\Transaction\Enums\FileType;

/**
 * @method static FileFactory factory($count = null, $state = [])
 * @method static Builder<static>|File newModelQuery()
 * @method static Builder<static>|File newQuery()
 * @method static Builder<static>|File query()
 *
 * @property FileType $type
 *
 * @mixin Eloquent
 */
#[Fillable([
    'user_email',
    'name',
    'path',
    'type',
])]
class File extends Model
{
    /** @use HasFactory<FileFactory> */
    use HasFactory;

    protected static function newFactory(): FileFactory
    {
        return FileFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FileType::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
