<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest para la verificación de email mediante enlace firmado.
 *
 * Encapsula la autorización del par id+hash de la URL para que el controlador
 * solo reciba requests donde los parámetros corresponden al usuario autenticado.
 *
 * No se usa EmailVerificationRequest del framework porque ese FormRequest
 * resuelve el usuario con el guard 'auth' (session-based), incompatible con
 * auth:sanctum (token-based). Aquí se valida contra $this->user(), que ya fue
 * resuelto por el middleware auth:sanctum antes de llegar al FormRequest.
 *
 * El middleware 'signed' en la ruta garantiza que la URL no fue manipulada.
 * Esta autorización previene que un usuario verifique el email de otro aunque
 * tenga una URL firmada válida.
 */
class VerifyEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return hash_equals((string) $user->getKey(), (string) $this->route('id'))
            && hash_equals(sha1($user->getEmailForVerification()), (string) $this->route('hash'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
