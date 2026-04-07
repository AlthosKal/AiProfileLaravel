<?php

namespace Modules\Transaction\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Shared\Enums\ExportFormat;

class ImportTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'format' => ['required', 'string', Rule::enum(ExportFormat::class)],
            'file' => ['required', 'file', 'mimes:xlsx,csv', 'max:10240'],
        ];
    }
}
