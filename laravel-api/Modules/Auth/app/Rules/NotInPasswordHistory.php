<?php

namespace Modules\Auth\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Modules\Auth\Enums\AuthErrorCode;
use Modules\Auth\Models\User;

readonly class NotInPasswordHistory implements ValidationRule
{
    public function __construct(
        private string $email,
    ) {}

    /**
     * @param  Closure(string, string|null=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = User::where('email', $this->email)->first();

        if ($user->verifyPasswordHistories($value)) {
            $fail(AuthErrorCode::PasswordInHistory->value);
        }
    }
}
