<?php

namespace App\Http\Requests;

class UpdateCopyRequest extends StoreCopyRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('copy'));
    }
}
