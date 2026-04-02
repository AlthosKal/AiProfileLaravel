<?php

namespace Modules\Auth\Http\Data;

use Laravel\Socialite\Contracts\User;
use Spatie\LaravelData\Data;

class GoogleAuthData extends Data
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
    ) {}

    public static function fromSocialiteUser(User $user): self
    {
        return new self(
            id: $user->getId(),
            email: $user->getEmail(),
            name: $user->getName(),
        );
    }
}
