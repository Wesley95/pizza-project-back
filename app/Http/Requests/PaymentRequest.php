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
            'name' => 'required|string|min:2|max:100',
            'card' => [
                'required',
                'digits_between:13,19'
            ],
            'expiration' => [
                'required',
                'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'
            ],
            'cvv' => 'required|digits_between:3,4',
        ] : [];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $expiration = $this->expiration;

            if (!$expiration) return;

            [$month, $year] = explode('/', $expiration);

            $month = (int) $month;
            $year = (int) ('20' . $year);

            $currentYear = (int) date('Y');
            $currentMonth = (int) date('m');

            if ($year < $currentYear || ($year == $currentYear && $month < $currentMonth)) {
                $validator->errors()->add('expiration', 'Cartão expirado.');
            }
        });
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
            'card.required' => 'O número do cartão é obrigatório',
            'card.digits_between' => 'Cartão inválido',
            'expiration.regex' => 'Data inválida',
            'cvv.digits_between' => 'CVV inválido',

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
            'card' => preg_replace('/\D/', '', $this->request->get('card')),
            'cep' => preg_replace('/\D/', '', $this->request->get('cep')),
        ]);
    }
}
