<?php

use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Enums\AuthSuccessCode;
use Modules\Auth\Models\User;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────
// VerifyEmailController
// ─────────────────────────────────────────────────────────────

describe('VerifyEmailController', function () {
    it('verifica el email y retorna clave semántica de éxito', function () {
        Event::fake([Verified::class]);

        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->getKey(), 'hash' => sha1($user->email)],
        );

        Sanctum::actingAs($user);

        getJson($url)
            ->assertSuccessful()
            ->assertJsonFragment(['status' => AuthSuccessCode::EmailVerified->value]);

        Event::assertDispatched(Verified::class);
        expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    });

    it('retorna clave semántica cuando el email ya está verificado', function () {
        Event::fake([Verified::class]);

        $user = User::factory()->create(['email_verified_at' => now()]);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->getKey(), 'hash' => sha1($user->email)],
        );

        Sanctum::actingAs($user);

        getJson($url)
            ->assertSuccessful()
            ->assertJsonFragment(['status' => AuthSuccessCode::EmailAlreadyVerified->value]);

        // No debe volver a despachar el evento si ya estaba verificado
        Event::assertNotDispatched(Verified::class);
    });

    it('rechaza la petición si la firma del enlace es inválida', function () {
        Sanctum::actingAs(User::factory()->unverified()->create());

        // URL sin firmar — el middleware 'signed' retorna 403
        getJson(route('verification.verify', [
            'id' => '1',
            'hash' => 'hash-invalido',
        ]))->assertForbidden();
    });

    it('rechaza la petición si el hash no corresponde al email del usuario', function () {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->getKey(), 'hash' => sha1('otro@email.com')],
        );

        Sanctum::actingAs($user);

        getJson($url)->assertForbidden();
    });

    it('rechaza la petición si el usuario no está autenticado', function () {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->getKey(), 'hash' => sha1($user->email)],
        );

        getJson($url)->assertUnauthorized();
    });
});

// ─────────────────────────────────────────────────────────────
// EmailVerificationNotificationController
// ─────────────────────────────────────────────────────────────

describe('EmailVerificationNotificationController', function () {
    it('reenvía la notificación y retorna clave semántica de éxito', function () {
        Sanctum::actingAs(User::factory()->unverified()->create());

        postJson(route('verification.send'))
            ->assertSuccessful()
            ->assertJsonFragment(['status' => AuthSuccessCode::VerificationLinkSent->value]);
    });

    it('retorna clave semántica cuando el email ya está verificado sin reenviar', function () {
        Sanctum::actingAs(User::factory()->create(['email_verified_at' => now()]));

        postJson(route('verification.send'))
            ->assertSuccessful()
            ->assertJsonFragment(['status' => AuthSuccessCode::EmailAlreadyVerified->value]);
    });

    it('rechaza la petición si el usuario no está autenticado', function () {
        postJson(route('verification.send'))->assertUnauthorized();
    });
});
