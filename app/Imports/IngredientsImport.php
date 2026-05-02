<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use App\Models\Ingredient;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;

class IngredientsImport implements ToCollection, WithHeadingRow, WithValidation
{
    public function collection(Collection $rows)
    {
        $data = [];
        $validated = [];

        foreach ($rows as $row) {
            $slug = Str::slug(trim($row['nome']));

            if(!isset($validated[$slug])) {
                $to_insert = [
                    'slug' => $slug,
                    'name' => $row['nome'],
                    'price' => $row['preco'],
                    'description' => $row['descricao'],
                    'status' => strtolower(trim($row['status'])) == 'ativo'
                ];

                $data[] = $to_insert;
            }

            $validated[$slug] = true;
        }

        if(count($data) > 0)
            Ingredient::upsert($data, ['slug'], [
                'name','price','description','status','slug'
            ]);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'preco' => ['required', 'numeric', 'min:0'],
            'status' => ['required','in:ativo,inativo'],
            'descricao' => ['max:70']
        ];
    }

    public function customValidationMessages()
    {
        return [
            'nome.required' => 'O nome é obrigatório.',
            'preco.numeric' => 'O preço deve ser numérico.',
            'status' => 'O status é obrigatório',
            'status.in' => 'O status deve ser ativo ou inativo',
            'descricao.max' => 'A descrição deve conter no máximo :max caracteres'
        ];
    }
}