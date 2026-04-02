<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthenticatedSessionController;
use Modules\Auth\Http\Controllers\EmailVerificationNotificationController;
use Modules\Auth\Http\Controllers\NewPasswordController;
use Modules\Auth\Http\Controllers\OAuthController;
use Modules\Auth\Http\Controllers\PasswordResetLinkController;
use Modules\Auth\Http\Controllers\RegisteredUserController;
use Modules\Auth\Http\Controllers\VerifyEmailController;
use Modules\Auth\Http\Middleware\EnsureEmailIsNotVerified;
use Modules\Shared\Enums\MiddlewaresFramework;
use Modules\Shared\Security\RateLimiterForApp;

Route::prefix('v1')->group(function () {
    Route::prefix('auth/{provider}')->group(function () {
        Route::get('/redirect', [OAuthController::class, 'redirect'])->name('oauth.redirect');
        Route::get('/callback', [OAuthController::class, 'callback'])->name('oauth.callback');
    });

    Route::middleware(MiddlewaresFramework::GUEST->value)->group(function () {
        Route::post('/register', [RegisteredUserController::class, 'store'])
            ->middleware(RateLimiterForApp::middleware(name: 'register_user', byEmail: true))
            ->name('register');

        Route::post('/login', [AuthenticatedSessionController::class, 'store'])
            ->middleware(RateLimiterForApp::middleware(name: 'login', byEmail: true))
            ->name('login');

        Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
            ->middleware(RateLimiterForApp::middleware(name: 'forgot_password', byEmail: true))
            ->name('password.email');

        Route::post('/reset-password', [NewPasswordController::class, 'store'])
            ->middleware(RateLimiterForApp::middleware(name: 'reset_password', byEmail: true))
            ->name('password.store');
    });

    Route::middleware(MiddlewaresFramework::with(MiddlewaresFramework::AUTH, 'sanctum'))->group(function () {
        Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
            ->middleware([
                MiddlewaresFramework::SIGNED->value,
                EnsureEmailIsNotVerified::class,
                RateLimiterForApp::middleware(name: 'verify_email'),
            ])
            ->name('verification.verify');

        Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware([EnsureEmailIsNotVerified::class, RateLimiterForApp::middleware(name: 'resend_verification')])
            ->name('verification.send');

        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
            ->name('logout');
    });
});
