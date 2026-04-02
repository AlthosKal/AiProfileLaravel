<?php

use Illuminate\Support\Facades\Mail;
use Modules\Auth\Mail\ResetPasswordMail;
use Modules\Auth\Models\User;

test('reset password link can be requested', function () {
    Mail::fake();

    $user = User::factory()->create();

    $this->postJson('/api/v1/forgot-password', ['email' => $user->email])
        ->assertSuccessful();

    Mail::assertSent(ResetPasswordMail::class, fn ($mail) => $mail->hasTo($user->email));
});

test('password can be reset with valid token', function () {
    Mail::fake();

    $user = User::factory()->create();

    $this->postJson('/api/v1/forgot-password', ['email' => $user->email]);

    Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) use ($user) {
        // Extraer el token de la URL de reset enviada en el mailable
        preg_match('/password-reset\/([^?]+)/', $mail->resetUrl, $matches);
        $token = $matches[1] ?? null;

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ]);

        $response->assertSuccessful();

        return true;
    });
});
