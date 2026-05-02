<?php

namespace App\Http\Services\Admin;

use App\Imports\IngredientsImport;
use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;

class IngredientService {

    /**
     * Realiza o cadastro de um novo ingrediente
     * 
     * @param array $data
     * 
     * @return Ingredient
     */
    public function create(array $data): Ingredient
    {
        return Ingredient::create($data);
    }

    /**
     * Realiza a atualização de um ingrediente
     * 
     * @param int $id
     * @param array $data
     * 
     * @return void
     */
    public function update(int $id, array $data): void
    {
        $category = Ingredient::findOrFail($id);
        $category->update($data);
    }

    /**
     * Realiza a deleção dos itens de forma massiva
     * 
     * @param array $ids
     * 
     * @return void
     */
    public function delete(array $ids = []) : void 
    {
        Ingredient::whereIn('id', $ids)
            ->delete();
    }

    /**
     * Realiza a atualização dos status massivamente
     * 
     * @param array $ids
     * 
     * @return void
     */
    public function changeStatus(array $ids = []) : void
    {
        Ingredient::whereIn('id', $ids)
            ->update([
                'status' => DB::raw('CASE WHEN status = 1 THEN 0 ELSE 1 END')
            ]);
    }

    /**
     * Realiza a importação dos dados dos ingredientes
     * 
     * @param mixed $file
     */
    public function import($file): array
    {
        try {
            Excel::import(new IngredientsImport, $file);

            return [
                'success' => true,
                'message' => 'Importação realizada com sucesso!'
            ];

        } catch (ValidationException $e) {
            $errors = [];

            foreach ($e->failures() as $failure) {
                $errors[] = [
                    'linha' => $failure->row(),
                    'coluna' => $failure->attribute(),
                    'erros' => $failure->errors(),
                ];
            }

            return [
                'success' => false,
                'errors' => $errors
            ];
        }
    }
}