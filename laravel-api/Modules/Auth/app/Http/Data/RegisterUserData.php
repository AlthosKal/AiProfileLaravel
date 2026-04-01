<?php

namespace Modules\Auth\Http\Data;

use Modules\Auth\Enums\AuthErrorCode;
use Modules\Auth\Rules\IdentificationTypeRule;
use Modules\Auth\Rules\RecaptchaV3Rule;
use Modules\Auth\Rules\UserAlreadyExistsRule;
use Spatie\LaravelData\Attributes\Validation\Password;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * DTO para la solicitud de registro de un nuevo usuario.
 *
 * Las reglas de validación de cada campo están divididas entre atributos PHP
 * en el constructor (validaciones simples) y el método `rules()` (validaciones
 * que requieren lógica adicional, como verificar unicidad del email en la DB).
 */
class RegisterUserData extends Data
{
    public function __construct(
        #[Rule('required|string|max:254')]
        public string $name,
        // La validación del email se centraliza en rules() para incluir
        // UserAlreadyExistsRule junto con las reglas base en una sola entrada.
        public string $email,
        #[Password(default: true)]
        #[Rule('required|string|min:8')]
        public string $password,
        #[Rule('required|integer|digits_between:1,10')]
        public int $identification_number,
        // IdentificationTypeRule valida que el valor sea un case válido del enum IdentificationTypeEnum.
        #[Rule(['required', 'string', 'max:8', new IdentificationTypeRule])]
        public string $identification_type,
        #[Rule(['nullable', 'string', new RecaptchaV3Rule('register')])]
        public ?string $recaptcha_token
    ) {}

    /**
     * Reglas de validación adicionales que requieren acceso al request o lógica compleja.
     *
     * Se centraliza aquí la validación del email para combinar en una sola entrada
     * las reglas base con UserAlreadyExistsRule, que consulta la base de datos.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        $email = request()->input('email', '');

        return [
            'email' => ['required', 'email', 'max:254', new UserAlreadyExistsRule($email)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            // Nombre
            'name.required' => AuthErrorCode::NameRequired->value,
            'name.string' => AuthErrorCode::NameString->value,
            'name.max' => AuthErrorCode::NameTooLong->value,

            // Email
            'email.required' => AuthErrorCode::EmailRequired->value,
            'email.email' => AuthErrorCode::EmailInvalid->value,
            'email.max' => AuthErrorCode::EmailTooLong->value,

            // Contraseña
            'password.required' => AuthErrorCode::PasswordRequired->value,
            'password.string' => AuthErrorCode::PasswordString->value,
            'password.min' => AuthErrorCode::PasswordTooShort->value,

            // Número de identificación
            'identification_number.required' => AuthErrorCode::IdentificationNumberRequired->value,
            'identification_number.integer' => AuthErrorCode::IdentificationNumberInteger->value,
            'identification_number.digits_between' => AuthErrorCode::IdentificationNumberInvalidLength->value,

            // Tipo de identificación
            'identification_type.required' => AuthErrorCode::IdentificationTypeRequired->value,
            'identification_type.string' => AuthErrorCode::IdentificationTypeString->value,
            'identification_type.max' => AuthErrorCode::IdentificationTypeTooLong->value,

            // Recaptcha Token
            'recaptcha_token.string' => AuthErrorCode::RecaptchaInvalidFormat->value,
        ];
    }
}
