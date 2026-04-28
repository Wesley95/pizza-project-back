<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
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
        return $this->request->get('type') == 'credit' ? [
            'encryptedCard' => 'required'
        ] : [];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        $data = [
            'encryptedCard.required' => 'O cartão criptografado é obrigatório',
            
            'required' => 'Campo requerido',
            'max' => 'o campo não pode exceder o limite máximo de :max caractéres',
            'min' => 'o campo deve conter um mínimo de :min caractéres',
        ];

        return $data;
    }
}
