<?php

namespace Modules\Auth\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static Builder<static>|PasswordHistory newModelQuery()
 * @method static Builder<static>|PasswordHistory newQuery()
 * @method static Builder<static>|PasswordHistory query()
 *
 * @mixin Eloquent
 */
#[Fillable(['user_id', 'password'])]
class PasswordHistory extends Model {}
