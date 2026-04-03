<?php

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;

// Par RSA dedicado exclusivamente a los tests del guard.
// Generado con openssl_pkey_new — no está relacionado con las claves de producción.
$testPrivateKey = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCiM5nwkk524odt
R0cYz38Khkh/cFg0mk58NDbSApTApMWjvUxt5rB116yy4LsgFh8wZpz3+ltjVyxn
3nMlLMBEeA9WPwUq3TTF9ynYhEJ6CK/Ei8ZhhCiXnV3pF5UQkIw0H3vicxnmbcCl
/BkWJVWxD8q2OXI+/WV6n75qjhN4OWaKRm57sPrTXlzOCJZPulKOjctUmC+k90zf
xCjQcfTVdDdC3hBF3//N6gNumwwf0Aa8os8pe/oWnqVrGZk579C7Ten7npvzOCy9
4BV4nly5ErGfzwg/DGnuiqIg1VEr3O3zUI7TMn6fxtnFhqx/jxjdgfhtD5esSa9d
6b8R55B7AgMBAAECggEAJyqQpG+ftMNUckXA3DPWeGMehG9LTUBpbUJqbmGjK7Vd
6ADgwLTwrTPsBrGPXdsZouVUR+jTQnSdS2OCqFpa/u2Cvo+vHr+Va6wYFakyKCeK
0cnymD+CUcH1GEDShNJymG91yaODgInF+A6cvCU9wOiQSVorxRwI8gg6wZ4XA3Nu
TwV8Mzk6Zz3+ez16YqvhMFSJdE4MNQhKzVPV8tbgfp7nQ76bLwP2DTkTds/i2Vha
1kD1pJeZ1Ba8XykOBpH0h1+SQw+0qW1fcuJVyfrddxR6pe3AKEyeEMKWy++sMwiQ
1kJQ92ZanVNXipp+U9gt51ov+NYrUXUrxrIVXoO2YQKBgQDWVAXejXvQCKtso/XZ
h/6+kfOGYNJQVmppSzAToW3WKEmP7jN0ea5gKhicfV4fEgvemIw4ZGw0Kunt8PYV
enBy4AS+ua72UBa7u5AQ6RGtLMn5rZEYEF+eD1kWCcCeahGdRra1Owsp55Sv3DIl
07bmg5xRARXvtF4LbkhsJM8EqwKBgQDBvQaMU+e30JCVMpleMPWbZHJhaPd6ihbt
8JUhOXxCscVGTqJwvaHbq2ckEZYQ/sRU8PTMCBACTE4yLkta/PyCghnK6ebZ6z8x
XWRn8l0mlCnCofMJoLnchyoMXWDTIb5PnAA/D4jDrqse7Z3TKqGSPUt3ZF6pk3Qr
gDzx62mDcQKBgQDHPtM6ArNwQS8DzyTVNg0HIm2OpeG+V6eS/RfTmAWgylEgoaNq
C1ilA11f1VgzcDZil9P69Lh2gtJ3pcNPUkTJNiKTH9FcIDYSDhqu7czF/dZB6y3w
fgA10zTRPP25Bwga+ssNjbciHKxoFD72VWw5vW4LDARVk4q9+6cOCeX+AwKBgQCY
4BHEtYjJUThlorHG05da8R4Yo311InYJd6gVuYjGEAT8/5vKnriT4GLY4U+rRX2j
ESf5v/rx9UhW7JTlzW9rhEHaDkvtdWY+C9Xo+CRtBskVHjnrRPqke7vAWgbHU38a
zpybJiTjVHcPRq0dLiyket2L7pWL9iDbGqv8sahm4QKBgBglc1UwCZ9H0VU4Ppxo
U5S0sdpBOftGWrMqqGgdhFG3epT8TbMyGOjye/+MAMSpUe+0hn6OBTIKZQulkXdO
IXm7nRZk3u9OM3YmwwKGJhGqHOsQ7uEbE66BZjM2McmzNip5z1SMAyM3dSMWJJfu
x9I0/TrySgYDQ6XcV5KD4lKo
-----END PRIVATE KEY-----
PEM;

$testPublicKey = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAojOZ8JJOduKHbUdHGM9/
CoZIf3BYNJpOfDQ20gKUwKTFo71MbeawddessuC7IBYfMGac9/pbY1csZ95zJSzA
RHgPVj8FKt00xfcp2IRCegivxIvGYYQol51d6ReVEJCMNB974nMZ5m3ApfwZFiVV
sQ/KtjlyPv1lep++ao4TeDlmikZue7D6015czgiWT7pSjo3LVJgvpPdM38Qo0HH0
1XQ3Qt4QRd//zeoDbpsMH9AGvKLPKXv6Fp6laxmZOe/Qu03p+56b8zgsveAVeJ5c
uRKxn88IPwxp7oqiINVRK9zt81CO0zJ+n8bZxYasf48Y3YH4bQ+XrEmvXem/EeeQ
ewIDAQAB
-----END PUBLIC KEY-----
PEM;

/**
 * Genera un JWT firmado con las claves RSA de test.
 *
 * @param  array<string, mixed>  $overrides  Permite sobrescribir claims para tests negativos
 */
function makeTestJwt(string $privateKey, array $overrides = []): string
{
    $config = Configuration::forAsymmetricSigner(
        new Sha256,
        InMemory::plainText($privateKey),
        InMemory::plainText('empty'),
    );

    $now = new DateTimeImmutable;

    return $config->builder()
        ->issuedBy($overrides['iss'] ?? 'http://laravel-api-test')
        ->issuedAt($now)
        ->expiresAt($overrides['exp'] ?? $now->modify('+5 minutes'))
        ->relatedTo($overrides['email'] ?? 'user@example.com')
        ->getToken($config->signer(), $config->signingKey())
        ->toString();
}

beforeEach(function () use ($testPublicKey) {
    // Apuntar el guard a las claves de test
    config(['passport.public_key' => $testPublicKey]);
    config(['app.internal_api_url' => 'http://laravel-api-test']);
});

it('autentica una request con JWT válido y expone el email en request->user()', function () use ($testPrivateKey) {
    $token = makeTestJwt($testPrivateKey, ['email' => 'user@example.com']);

    $response = $this->withToken($token)->getJson('/api/user');

    $response->assertSuccessful();
    expect($response->json('email'))->toBe('user@example.com');
});

it('rechaza una request sin Authorization header', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

it('rechaza un JWT con firma inválida', function () use ($testPrivateKey) {
    $token = makeTestJwt($testPrivateKey);
    $parts = explode('.', $token);
    $parts[2] = strrev($parts[2]);
    $tampered = implode('.', $parts);

    $this->withToken($tampered)->getJson('/api/user')->assertUnauthorized();
});

it('rechaza un JWT expirado', function () use ($testPrivateKey) {
    $token = makeTestJwt($testPrivateKey, ['exp' => new DateTimeImmutable('-1 second')]);

    $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
});

it('rechaza un JWT con issuer incorrecto', function () use ($testPrivateKey) {
    $token = makeTestJwt($testPrivateKey, ['iss' => 'http://sistema-desconocido.com']);

    $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
});

it('rechaza un token que no es JWT', function () {
    $this->withToken('esto-no-es-un-jwt')->getJson('/api/user')->assertUnauthorized();
});
