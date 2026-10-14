<?php

namespace App\Http\Requests;

use App\Enums\ListingCondition;
use App\Enums\MediaFormat;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWatchRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'format' => ['nullable', Rule::enum(MediaFormat::class)->except([MediaFormat::Digital])],
            'condition' => ['required', Rule::enum(ListingCondition::class)],
            'target_price' => ['nullable', 'numeric', 'min:0', 'max:99999'],
        ];
    }
}
