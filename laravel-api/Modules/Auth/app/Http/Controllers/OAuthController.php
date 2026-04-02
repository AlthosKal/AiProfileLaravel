<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Auth\Interfaces\OAuth\CallbackStrategyInterface;
use Modules\Auth\Interfaces\OAuth\RedirectStrategyInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class OAuthController extends Controller
{
    public function __construct(
        private readonly RedirectStrategyInterface $redirectStrategy,
        private readonly CallbackStrategyInterface $callbackStrategy,
    ) {}

    /**
     * Redirigir al proveedor OAuth correspondiente.
     *
     * La estrategia concreta (Google, Outlook, etc.) se resuelve desde el
     * contenedor según el parámetro {provider} de la ruta.
     */
    public function redirect(): RedirectResponse
    {
        return $this->redirectStrategy->redirect();
    }

    /**
     * Procesar el callback del proveedor OAuth correspondiente.
     */
    public function callback(): RedirectResponse
    {
        return $this->callbackStrategy->callback();
    }
}
