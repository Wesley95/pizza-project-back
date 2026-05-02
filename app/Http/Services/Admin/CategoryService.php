<?php

namespace App\Http\Services\Admin;

use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CategoryService {

    /**
     * Realiza o cadastro de uma nova categoria
     * 
     * @param array $data
     * 
     * @return Category
     */
    public function create(array $data): Category
    {
        return Category::create($data);
    }

    /**
     * Realiza a atualização de uma categoria
     * 
     * @param int $id
     * @param array $data
     * 
     * @return void
     */
    public function update(int $id, array $data): void
    {
        $category = Category::findOrFail($id);
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
        Category::whereIn('id', $ids)
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
        Category::whereIn('id', $ids)
            ->update([
                'status' => DB::raw('CASE WHEN status = 1 THEN 0 ELSE 1 END')
            ]);
    }
}