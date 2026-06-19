<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'provider_id' => 'required|integer|exists:providers,id',
            'storage_id' => 'required|integer|exists:storages,id',
            'purchase_date' => 'nullable|date',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|integer|exists:products,id',
            'products.*.qty' => 'required|integer|min:1',
            'products.*.purchase_price' => 'required|numeric|min:0',
        ];
    }
}
