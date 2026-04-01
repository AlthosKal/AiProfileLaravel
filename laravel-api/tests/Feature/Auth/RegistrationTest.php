<?php

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Modules\Auth\Enums\AuthErrorCode;
use Modules\Auth\Models\User;

$validPayload = fn () => [
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => 'Password1!',
    'identification_number' => 12345678,
    'identification_type' => 'CC',
];

it('registers a new user successfully', function () use ($validPayload) {
    Event::fake();

    $response = $this->postJson('/api/v1/register', $validPayload());

    $response->assertCreated();

    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'name' => 'Test User',
    ]);

    $this->assertDatabaseHas('password_histories', [
        'user_email' => 'test@example.com',
    ]);

    Event::assertDispatched(Registered::class);
});

it('does not register a user with an existing email', function () use ($validPayload) {
    User::factory()->create(['email' => 'test@example.com']);

    $response = $this->postJson('/api/v1/register', $validPayload());

    $response->assertUnprocessable()
        ->assertJsonFragment(['email' => [AuthErrorCode::UserAlreadyExists->value]]);
});

it('validates required fields', function (string $field, string $errorCode) use ($validPayload) {
    $payload = array_diff_key($validPayload(), [$field => null]);

    $this->postJson('/api/v1/register', $payload)
        ->assertUnprocessable()
        ->assertJsonPath("errors.{$field}.0", $errorCode);
})->with([
    'name required'                  => ['name', AuthErrorCode::NameRequired->value],
    'email required'                 => ['email', AuthErrorCode::EmailRequired->value],
    'password required'              => ['password', AuthErrorCode::PasswordRequired->value],
    'identification_number required' => ['identification_number', AuthErrorCode::IdentificationNumberRequired->value],
    'identification_type required'   => ['identification_type', AuthErrorCode::IdentificationTypeRequired->value],
]);

it('validates identification type must be a valid enum value', function () use ($validPayload) {
    $payload = array_merge($validPayload(), ['identification_type' => 'INVALID']);

    $this->postJson('/api/v1/register', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('errors.identification_type.0', AuthErrorCode::IdentificationTypeInvalid->value);
});
