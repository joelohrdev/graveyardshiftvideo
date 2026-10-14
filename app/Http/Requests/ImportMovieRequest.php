<?php

namespace App\Http\Requests;

use App\Enums\MediaFormat;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportMovieRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tmdb_id' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
            'format' => ['nullable', Rule::enum(MediaFormat::class)],
            'barcode' => ['nullable', 'regex:/^\d{8,14}$/'],
        ];
    }
}
