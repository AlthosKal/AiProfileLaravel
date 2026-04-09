<?php

namespace Modules\Client\Http\LaravelMcp\Transaction\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Client\Http\LaravelMcp\Clients\HttpClient;
use Modules\Shared\Traits\JsonResponseTrait;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controlador puente para transacciones.
 *
 * Recibe peticiones de React (auth:sanctum), obtiene el email del usuario
 * autenticado y delega cada operación a HttpClient, que se encarga de
 * adjuntar el JWT interno y llamar a laravel-mcp.
 *
 * La validación de datos se delega a laravel-mcp; aquí solo se asegura
 * que el payload sea un array antes de reenviarlo.
 */
class TransactionController extends Controller
{
    use JsonResponseTrait;

    public function __construct(
        private readonly HttpClient $client,
    ) {}

    /**
     * @throws ConnectionException
     */
    public function index(Request $request): JsonResponse
    {
        $response = $this->client->getAll(
            email: $request->user()->email,
            perPage: (int) $request->query('per_page', 15),
            page: (int) $request->query('page', 1),
        );

        return response()->json($response->json(), $response->status());
    }

    /**
     * @throws ConnectionException
     */
    public function store(Request $request): JsonResponse
    {
        $response = $this->client->add(
            $request->user()->email,
            $request->all(),
        );

        return response()->json($response->json(), $response->status());
    }

    /**
     * @throws ConnectionException
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $response = $this->client->getBYId($request->user()->email, $id);

        return response()->json($response->json(), $response->status());
    }

    /**
     * @throws ConnectionException
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $response = $this->client->update(
            $request->user()->email,
            $id,
            $request->all(),
        );

        return response()->json($response->json(), $response->status());
    }

    /**
     * @throws ConnectionException
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $response = $this->client->delete($request->user()->email, $id);

        return response()->json($response->json(), $response->status());
    }

    /**
     * @throws ConnectionException
     */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $response = $this->client->export(
            $request->user()->email,
            $request->only(['format', 'date_from', 'date_to']),
        );

        if (! $response->successful()) {
            return response()->json($response->json(), $response->status());
        }

        return response()->streamDownload(
            fn () => print ($response->body()),
            $response->header('Content-Disposition')
                ? basename(str_replace('"', '', explode('filename=', $response->header('Content-Disposition'))[1] ?? 'export'))
                : 'export',
            [
                'Content-Type' => $response->header('Content-Type') ?: 'application/octet-stream',
            ],
        );
    }

    /**
     * @throws ConnectionException
     */
    public function import(Request $request): JsonResponse
    {
        $response = $this->client->import(
            $request->user()->email,
            $request->input('format', ''),
            $request->file('file'),
        );

        return response()->json($response->json(), $response->status());
    }
}
