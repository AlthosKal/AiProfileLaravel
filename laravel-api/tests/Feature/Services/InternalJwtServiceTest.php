<?php

use App\Services\InternalJwtService;
use Illuminate\Support\Facades\Cache;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;

beforeEach(function () {
    Cache::store('redis')->flush();
});

it('genera un JWT firmado con RS256 que contiene el email en el claim sub', function () {
    $service = app(InternalJwtService::class);

    $token = $service->forEmail('user@example.com');

    $config = Configuration::forAsymmetricSigner(
        new Sha256,
        InMemory::plainText('empty'),
        InMemory::plainText(config('passport.public_key')),
    );

    $parsed = $config->parser()->parse($token);

    expect($parsed->claims()->get('sub'))->toBe('user@example.com')
        ->and($parsed->claims()->get('iss'))->toBe(config('app.url'))
        ->and($parsed->claims()->get('exp'))->toBeInstanceOf(DateTimeImmutable::class);
});

it('el JWT expira en 5 minutos', function () {
    $service = app(InternalJwtService::class);

    $before = new DateTimeImmutable;
    $token = $service->forEmail('expiry@example.com');
    $after = new DateTimeImmutable;

    $config = Configuration::forAsymmetricSigner(
        new Sha256,
        InMemory::plainText('empty'),
        InMemory::plainText(config('passport.public_key')),
    );

    $parsed = $config->parser()->parse($token);
    $exp = $parsed->claims()->get('exp')->getTimestamp();

    expect($exp)
        ->toBeGreaterThanOrEqual($before->modify('+5 minutes')->getTimestamp())
        ->toBeLessThanOrEqual($after->modify('+5 minutes')->getTimestamp());
});

it('retorna el mismo JWT desde caché para el mismo email', function () {
    $service = app(InternalJwtService::class);

    $first = $service->forEmail('cached@example.com');
    $second = $service->forEmail('cached@example.com');

    expect($first)->toBe($second);
});

it('genera JWTs distintos para emails distintos', function () {
    $service = app(InternalJwtService::class);

    $tokenA = $service->forEmail('alice@example.com');
    $tokenB = $service->forEmail('bob@example.com');

    expect($tokenA)->not->toBe($tokenB);
});

it('genera un JWT nuevo después de que el caché expira', function () {
    $service = app(InternalJwtService::class);
    $email = 'refresh@example.com';
    $cacheKey = 'internal_jwt:'.sha1($email);

    // Guardar el primer JWT manualmente en caché
    $first = $service->forEmail($email);

    // Simular expiración eliminando la clave del caché
    Cache::store('redis')->forget($cacheKey);

    // Avanzar el reloj para garantizar iat distinto en el nuevo token
    $this->travel(2)->seconds();

    $second = $service->forEmail($email);

    expect($first)->not->toBe($second);
});
