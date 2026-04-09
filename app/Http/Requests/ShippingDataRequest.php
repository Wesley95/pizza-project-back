<?php

namespace App\Http\Requests;

use App\Rules\CPF;
use App\Rules\FullName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

class ShippingDataRequest extends FormRequest
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
            'document' => ['required',new CPF()],
            'name' => ['required','max:255','min:2', new FullName()],
            'phone' => ['required','min:10','max:11'],
            'email' => ['required','regex:/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix','max:150'],
        ];

        if($this->request->get('shipping') == 'delivery') {
            $data = array_merge($data, [
                'street' => ['required','max:255','min:2'],
                'number' => ['required','max:50'],
                'complement' => ['max:255','min:2','nullable'],
                'neighborhood' => ['required','max:255','min:2'],
                'city' => ['required','max:255','min:2'],
                'uf' => ['required','size:2'],
                'cep' => ['required','digits:8'],
            ]);
        }

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
            'cep.required' => 'Digite o CEP',
            'cep.digits' => 'Quantidade de números inválida',
            'uf.size' => 'A sigla deve contér 2 caractéres',
            'email.required' => 'O e-mail pessoal é obrigatório',
            'email.regex' => 'E-mail inválido',
            
            'required' => 'Campo requerido',
            'max' => 'o campo não pode exceder o limite máximo de :max caractéres',
            'min' => 'o campo deve conter um mínimo de :min caractéres',
        ];

        return $data;
    }

    public function prepareForValidation()
    {
        $this->merge([
            'name' => trim($this->request->get('name')),
            'email' => strtolower(trim($this->request->get('email'))),
            'phone' => preg_replace('/\D/', '', $this->request->get('phone')),
            'cep' => preg_replace('/\D/', '', $this->request->get('cep')),
        ]);
    }
}
