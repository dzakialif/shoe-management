<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdateShoeRequest extends ApiRequest
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
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'ulid',
                'exists:categories.id',
            ],
            'brand_id' => [
                'required',
                'ulid',
                'exists:brands.id',
            ],
            'name' => [
                'required',
                'max:200',
            ],
            'size' => [
                'required',
                'string',
                'max:50',
            ],
            'price' => [
                'required',
                'integer',
                'min:0',
            ],
            'stock' => [
                'required',
                'integer',
                'min:0',
            ],
            'description' => [
                'nullable'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'The category field is required.',
            'category_id.exists' => 'The selected category is invalid.',
            'brand_id.required' => 'The brand field is required.',
            'brand_id.exists' => 'The selected brand is invalid.',
            'name.required' => 'The name field is required.',
            'name.max' => 'The name may not be greater than 200 characters.',
            'size.required' => 'The size field is required.',
            'size.string' => 'The size must be a string.',
            'size.max' => 'The size may not be greater than 50 characters.',
            'price.required' => 'The price field is required.',
            'price.integer' => 'The price must be an integer.',
            'price.min' => 'The price must be at least 0.',
            'stock.required' => 'The stock field is required.',
            'stock.integer' => 'The stock must be an integer.',
            'stock.min' => 'The stock must be at least 0.',
        ];
    }
}
