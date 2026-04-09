<?php

namespace App\Http\Requests\Admin;

use App\Rules\FullName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

class ProductRequest extends FormRequest
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
            'name' => ['required','max:255','min:2','unique:products,name,'.$this->id],
            'sku' => ['required','unique:products,sku,'.$this->id],
            'price' => ['numeric', 'min:1', 'required'],
            'discount' => ['numeric','max:100'],
            'description' => ['max:255'],
            'visibility' => ['required', 'in:private,public'],
            'category_id' => ['required']
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
            'name.unique' => 'O nome digitado já consta cadastrado no sistema',
            'sku.required' => 'O SKU é obrigatório',
            'sku.unique' => 'O SKU digitado já consta cadastrado no sistema',
            'price.required' => 'O preço do produto é obrigatório',
            'price.min' => 'O preço deve ser maior ou igual a :min',
            'max' => 'O campo deve conter no máximo :max caracteres',
            'min' => 'O campo deve conter no mínimo :min caracteres',
            'visibility.required' => 'Defina o tipo de exibição do produto',
            'category_id.required' => 'Selecione a categoria do produto',
            'discount.max' => 'O desconto máximo é de :max porcento'
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
