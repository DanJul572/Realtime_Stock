<?php

namespace App\Http\Requests;

use App\Rules\Base64Image;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('products', 'code')->ignore($this->route('product')),
            ],
            'image' => ['nullable', new Base64Image],
            'name' => 'required',
            'size' => 'required',
            'stock' => 'required|numeric',
            'surface' => 'required',
            'type' => 'required',
            'price_1' => 'required',
            'price_2' => 'required',
            'category_id' => 'required',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'error' => $validator->errors()->first(),
            'statusCode' => 400,
        ], 400));
    }
}
