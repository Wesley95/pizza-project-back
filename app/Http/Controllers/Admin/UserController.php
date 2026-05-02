<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Http\Services\Admin\UserService;
use App\Models\Repositories\UserRepository;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    use ApiResponse;

    private UserRepository $user;

    public function __construct(UserRepository $userRepo) {
        $this->user = $userRepo;
    }

    /**
     * Realiza o cadastro de um novo usuário administrador
     * 
     * @param \App\Http\Requests\Admin\UserRequest $request
     * @param \App\Http\Services\Admin\UserService $userService
     * 
     * @return mixed
     */
    public function store(UserRequest $request, UserService $userService)
    {
        try{
            $user = $userService->create([
                'name' => $request->nome,
                'email' => $request->email,
                'password' => Hash::make($request->senha)
            ]);

            return $this->created($user);
        }catch(\Exception $e) {
            return $this->error("Erro de cadastro: " . $e->getMessage());
        }
    }

    /**
     * Realiza a edição do cadastro de um usuário
     * 
     * @param \App\Http\Requests\Admin\UserRequest $request
     * @param \App\Http\Services\Admin\UserService $userService 
     * 
     * @return mixed
     */
    public function edit(UserRequest $request, UserService $userService)
    {
        try{
            $userService->update($request->id ?? 0, [
                'name' => $request->nome,
                'email' => $request->email,
                ...($request->senha ? [
                    'password' => Hash::make($request->senha)
                ] : [])
            ]);

            return $this->success("Usuário editado com sucesso");
        }catch(\Exception $e) {
            return $this->error("Erro de edição: " . $e->getMessage());
        }
    }

    /**
     * Realiza a captura do usuário pelo id
     * 
     * @param string|int $id
     * 
     * @return mixed
     */
    public function show($id) {
        try{
            return $this->success(User::findOrFail($id));
        }catch(\Exception $e) {
            return $this->error("Erro de captura de cliente: " . $e->getMessage());
        }
    }

    /**
     * Realiza o softdelete do usuário
     * 
     * @param \Illuminate\Http\Request $request
     * 
     * @return mixed
     */
    public function destroy(Request $request) {
        try{

            $ids = $request->ids;

            User::whereIn('id', $ids)
                ->delete();

            return $this->success();
        } catch(\Exception $e) {
            return $this->error("Erro de exclusão: " . $e->getMessage());
        }
    }

    /**
     * Realiza a captura e paginação dos usuários do sistema
     * 
     * @param \Illuminate\Http\Request $request
     * 
     * @return mixed
     */
    public function paginate(Request $request) {
        $users = $this->user->search($request->except('_token'))->paginate($request->per_page ?? 10);
        return $this->success($users);
    }

    /**
     * Realiza a atualização dos status baseado nos ids e status enviados
     * 
     * @param \Illuminate\Http\Request $request
     * @param \App\Http\Services\Admin\UserService $userService 
     * 
     */
    public function changeStatus(Request $request, UserService $userService) {
        try{
            $ids = $request->ids;
            $userService->changeStatus($ids);
            
            return $this->success($ids);
        }catch(\Exception $e) {
            return $this->error("Erro de atualização: " . $e->getMessage());
        }
    }
}