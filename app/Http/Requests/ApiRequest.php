<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class ApiRequest extends FormRequest
{
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            apiResponse(
                message: 'The given data was invalid.',
                success: false,
                status: 422,
                errors: $validator->errors()->toArray(),
            )
        );
    }
}
