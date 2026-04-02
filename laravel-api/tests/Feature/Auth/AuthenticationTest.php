<?php

use Modules\Auth\Models\User;

test('users can authenticate using the login screen', function () {
    $password = 'Password1!';
    $user = User::factory()->create(['password' => bcrypt($password)]);

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => $password,
        'device_name' => 'Test Device',
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('data.token', fn ($token) => ! empty($token));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create(['password' => bcrypt('Password1!')]);

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'Wrong-Password1!',
        'device_name' => 'Test Device',
    ]);

    $response->assertUnprocessable();
});

test('users can logout', function () {
    $password = 'Password1!';
    $user = User::factory()->create(['password' => bcrypt($password)]);

    $loginResponse = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => $password,
        'device_name' => 'Test Device',
    ]);

    $token = $loginResponse->json('data.token');

    $response = $this->withToken($token)->postJson('/api/v1/logout');

    $response->assertNoContent();
});
