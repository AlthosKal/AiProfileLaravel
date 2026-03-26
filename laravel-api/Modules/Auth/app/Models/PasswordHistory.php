<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PasswordHistory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PasswordHistory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PasswordHistory query()
 * @mixin \Eloquent
 */
#[Fillable(['user_id', 'password'])]
class PasswordHistory extends Model {}
