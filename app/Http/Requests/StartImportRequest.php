<?php

namespace App\Http\Requests;

use App\Actions\StartGenreImport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartImportRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $earliest = StartGenreImport::EarliestYear;
        $latest = now()->year + 1;

        return [
            'from_year' => ['nullable', 'integer', "between:{$earliest},{$latest}"],
            'to_year' => ['nullable', 'integer', "between:{$earliest},{$latest}", Rule::when($this->filled('from_year'), 'gte:from_year')],
            'refresh' => ['boolean'],
        ];
    }
}
