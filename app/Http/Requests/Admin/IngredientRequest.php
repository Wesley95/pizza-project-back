<?php

namespace App\Http\Requests\Admin;

use App\Rules\FullName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

class IngredientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $data = [
            'name' => ['required','max:255','min:2','unique:ingredients,name,'.$this->id],
            'price' => ['numeric', 'min:1', 'required']
        ];

        return $data;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        $data = [
            'name.required' => 'O Nome é obrigatório',
            'unique' => 'O nome digitado já consta cadastrado no sistema',
            'price.required' => 'O preço do ingrediente é obrigatório',
            'price.min' => 'O preço deve ser maior ou igual a :min'
        ];

        return $data;
    }

    public function prepareForValidation()
    {
        $this->merge([
            'name' => trim($this->request->get('name'))
        ]);
    }
}
