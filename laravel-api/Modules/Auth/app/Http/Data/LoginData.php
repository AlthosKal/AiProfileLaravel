<?php

namespace Modules\Auth\Http\Data;

use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

class LoginData extends Data
{
    public function __construct(
        #[Rule('required|string|email')]
        public string $email,
        #[Rule('required|string')]
        public string $password,
        #[Rule('nullable|boolean')]
        public ?bool $remember,
        #[Rule('nullable|string|')]
        public ?string $recaptcha_token
    ) {}
}
