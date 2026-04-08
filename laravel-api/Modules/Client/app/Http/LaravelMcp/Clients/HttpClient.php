<?php

namespace Modules\Client\Http\Clients;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Modules\Shared\Security\InternalJwtSecurity;

/**
 * Cliente HTTP interno para comunicarse con laravel-mcp.
 *
 * Obtiene (o reutiliza desde caché) el JWT RS256 del usuario autenticado
 * mediante InternalJwtSecurity y lo adjunta como Bearer token en cada
 * petición. La URL base se configura en services.laravel-mcp.url.
 */
readonly class HttpClient
{
    public function __construct(
        private InternalJwtSecurity $jwtSecurity,
    ) {}

    /**
     * @throws ConnectionException
     */
    public function getAll(string $email, int $perPage = 15, int $page = 1): Response
    {
        return $this->request($email)->get($this->url('transactions'), [
            'per_page' => $perPage,
            'page' => $page,
        ]);
    }

    /**
     * @throws ConnectionException
     */
    public function getBYId(string $email, int $id): Response
    {
        return $this->request($email)->get($this->url("transactions/$id"));
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ConnectionException
     */
    public function add(string $email, array $data): Response
    {
        return $this->request($email)->post($this->url('transactions'), $data);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ConnectionException
     */
    public function update(string $email, int $id, array $data): Response
    {
        return $this->request($email)->put($this->url("transactions/$id"), $data);
    }

    /**
     * @throws ConnectionException
     */
    public function delete(string $email, int $id): Response
    {
        return $this->request($email)->delete($this->url("transactions/$id"));
    }

    /**
     * @param  array<string, string>  $params  e.g. ['format' => 'xlsx', 'date_from' => '...', 'date_to' => '...']
     *
     * @throws ConnectionException
     */
    public function export(string $email, array $params): Response
    {
        return $this->request($email)->get($this->url('transactions/export'), $params);
    }

    /**
     * @throws ConnectionException
     */
    public function import(string $email, string $format, UploadedFile $file): Response
    {
        return $this->request($email)
            ->attach('file', $file->getContent(), $file->getClientOriginalName())
            ->post($this->url('transactions/import'), ['format' => $format]);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function request(string $email): PendingRequest
    {
        $jwt = $this->jwtSecurity->forEmail($email);

        return Http::withToken($jwt)
            ->timeout(15)
            ->connectTimeout(5)
            ->acceptJson();
    }

    private function url(string $path): string
    {
        return rtrim(config('services.laravel-mcp.url'), '/').'/v1/'.ltrim($path, '/');
    }
}
