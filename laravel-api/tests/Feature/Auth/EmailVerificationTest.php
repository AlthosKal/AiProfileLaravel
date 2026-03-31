<?php

use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Enums\AuthSuccessCode;
use Modules\Auth\Models\User;

test('email can be verified', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    Sanctum::actingAs($user);

    $this->getJson($verificationUrl)
        ->assertSuccessful()
        ->assertJsonFragment(['status' => AuthSuccessCode::EmailVerified->value]);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    Sanctum::actingAs($user);

    $this->getJson($verificationUrl)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});
