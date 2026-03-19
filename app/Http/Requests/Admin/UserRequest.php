<?php

namespace App\Http\Requests\Admin;

use App\Rules\FullName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

class UserRequest extends FormRequest
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
            'nome' => ['required','max:255','min:2', new FullName()],
            'email' => ['required','regex:/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix','unique:users,email,'.$this->id,'max:150'],
            'senha' => ['min:6','max:25','confirmed'],
        ];

        $data['senha'][] = !$this->id ? 'required' : 'nullable';

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
            'nome.required' => 'O Nome é obrigatório',
            'email.required' => 'O E-mail é obrigatório',
            'regex' => 'O E-mail deve ser válido',            
            'unique' => 'O E-mail digitado já consta cadastrado no sistema',
            'min' => 'O campo deve conter no mínimo :min caractéres',
            'max' => 'O campo deve conter no máximo :max caractéres'
        ];

        if(!$this->id) {
            $data = array_merge($data, [
                'senha.required' => 'Digite a senha',
                'senha.confirmed' => 'Confirme corretamente a senha'
            ]);
        }

        return $data;
    }

    public function prepareForValidation()
    {
        $this->merge([
            'nome' => trim($this->request->get('nome')),
            'email' => strtolower(trim($this->request->get('email')))
        ]);
    }
}
