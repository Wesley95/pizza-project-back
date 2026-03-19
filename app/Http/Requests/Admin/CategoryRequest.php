<?php

namespace App\Http\Requests\Admin;

use App\Rules\FullName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

class CategoryRequest extends FormRequest
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
            'name' => ['required','max:255','min:2','unique:categories,name,'.$this->id],
            'color' => ['min:6','max:25'],
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
            'email.required' => 'O E-mail é obrigatório',
            'unique' => 'O nome digitado já consta cadastrado no sistema',
            'min' => 'O campo deve conter no mínimo :min caractéres',
            'max' => 'O campo deve conter no máximo :max caractéres'
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
