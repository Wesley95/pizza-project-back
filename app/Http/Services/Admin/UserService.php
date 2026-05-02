<?php

namespace App\Http\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserService {

    /**
     * Realiza o cadastro de um novo usuário
     * 
     * @param array $data
     * 
     * @return User
     */
    public function create(array $data): User
    {
        return User::create($data);
    }

    /**
     * Realiza a atualização de um usuário
     * 
     * @param int $id
     * @param array $data
     * 
     * @return void
     */
    public function update(int $id, array $data): void
    {
        $user = User::findOrFail($id);
        $user->update($data);
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
        user::whereIn('id', $ids)
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
        User::whereIn('id', $ids)
            ->update([
                'status' => DB::raw('CASE WHEN status = 1 THEN 0 ELSE 1 END')
            ]);
    }
}