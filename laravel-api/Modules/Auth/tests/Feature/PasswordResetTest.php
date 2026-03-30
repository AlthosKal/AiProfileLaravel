<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Actions\Password\ResetPasswordAction;
use Modules\Auth\Actions\Password\SendPasswordResetLinkAction;
use Modules\Auth\Enums\AuthErrorCode;
use Modules\Auth\Http\Data\PasswordResetLinkData;
use Modules\Auth\Http\Data\ResetPasswordData;
use Modules\Auth\Mail\ResetPasswordMail;
use Modules\Auth\Models\User;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────
// SendPasswordResetLinkAction
// ─────────────────────────────────────────────────────────────

describe('SendPasswordResetLinkAction', function () {
    beforeEach(function () {
        Mail::fake();
    });

    it('retorna la clave semántica de éxito cuando el email existe', function () {
        User::factory()->create(['email' => 'user@example.com']);

        $action = app(SendPasswordResetLinkAction::class);
        $data = new PasswordResetLinkData(email: 'user@example.com', recaptcha_token: null);

        $status = $action->send($data);

        expect($status)->toBe(AuthErrorCode::PasswordResetLinkSent->value);
    });

    it('retorna la misma clave semántica cuando el email NO existe para evitar enumeración de usuarios', function () {
        $action = app(SendPasswordResetLinkAction::class);
        $data = new PasswordResetLinkData(email: 'noexiste@example.com', recaptcha_token: null);

        $status = $action->send($data);

        // Ambos casos retornan la misma clave para no revelar si el email está registrado
        expect($status)->toBe(AuthErrorCode::PasswordResetLinkFailed->value);
    });

    it('envía el mailable de reset al usuario registrado', function () {
        $user = User::factory()->create(['email' => 'user@example.com']);

        $action = app(SendPasswordResetLinkAction::class);
        $data = new PasswordResetLinkData(email: 'user@example.com', recaptcha_token: null);

        $action->send($data);

        Mail::assertSent(ResetPasswordMail::class, fn ($mail) => $mail->hasTo($user->email));
    });
});

// ─────────────────────────────────────────────────────────────
// ResetPasswordAction
// ─────────────────────────────────────────────────────────────

describe('ResetPasswordAction', function () {
    it('resetea la contraseña y retorna clave semántica de éxito', function () {
        Event::fake([PasswordReset::class]);

        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = Password::createToken($user);

        $action = app(ResetPasswordAction::class);
        $data = new ResetPasswordData(
            token: $token,
            email: 'user@example.com',
            password: 'NuevaPassword1!',
            password_confirmation: 'NuevaPassword1!',
        );

        $status = $action->update($data);

        expect($status)->toBe(AuthErrorCode::PasswordResetSuccess->value);
        Event::assertDispatched(PasswordReset::class);
    });

    it('actualiza la contraseña en la base de datos', function () {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = Password::createToken($user);

        $action = app(ResetPasswordAction::class);
        $data = new ResetPasswordData(
            token: $token,
            email: 'user@example.com',
            password: 'NuevaPassword1!',
            password_confirmation: 'NuevaPassword1!',
        );

        $action->update($data);

        expect(Hash::check('NuevaPassword1!', $user->fresh()->password))->toBeTrue();
    });

    it('registra password_changed_at al resetear', function () {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = Password::createToken($user);

        $action = app(ResetPasswordAction::class);
        $data = new ResetPasswordData(
            token: $token,
            email: 'user@example.com',
            password: 'NuevaPassword1!',
            password_confirmation: 'NuevaPassword1!',
        );

        $action->update($data);

        expect($user->fresh()->password_changed_at)->not->toBeNull();
    });

    it('lanza ValidationException con clave semántica cuando el token es inválido', function () {
        User::factory()->create(['email' => 'user@example.com']);

        $action = app(ResetPasswordAction::class);
        $data = new ResetPasswordData(
            token: 'token-invalido',
            email: 'user@example.com',
            password: 'NuevaPassword1!',
            password_confirmation: 'NuevaPassword1!',
        );

        try {
            $action->update($data);
            $this->fail('Se esperaba una ValidationException');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('email')
                ->and($e->errors()['email'][0])->toBe(AuthErrorCode::PasswordResetFailed->value);
        }
    });

    it('guarda la contraseña anterior en el historial', function () {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $oldHash = $user->password;
        $token = Password::createToken($user);

        $action = app(ResetPasswordAction::class);
        $data = new ResetPasswordData(
            token: $token,
            email: 'user@example.com',
            password: 'NuevaPassword1!',
            password_confirmation: 'NuevaPassword1!',
        );

        $action->update($data);

        expect($user->passwordHistories()->where('password', $oldHash)->exists())->toBeTrue();
    });
});
