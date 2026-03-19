<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Category;
use App\Models\Repositories\CategoryRepository;
use App\Models\Repositories\UserRepository;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    use ApiResponse;

    private $category;

    public function __construct(CategoryRepository $categoryRepo) {
        $this->category = $categoryRepo;
    }

    /**
     * Realiza o cadastro de um novo usuário administrador
     * @param \App\Http\Requests\Admin\CategoryRequest $request
     * 
     * @return mixed
     */
    public function store(CategoryRequest $request)
    {
        try{
            Category::create([
                'name' => $request->name,
                'color' => $request->color                
            ]);

            return $this->created();
        }catch(\Exception $e) {
            return $this->error("Erro de cadastro: " . $e->getMessage());
        }
    }

    /**
     * Realiza a edição do cadastro de um usuário
     * @param \App\Http\Requests\Admin\CategoryRequest $request
     * 
     * @return mixed
     */
    public function edit(CategoryRequest $request)
    {
        try{
            $category = $request->id ? Category::find($request->id) : null;

            if(!$category) {
                return $this->notFound('Categoria não encontrada');
            }

            $data = [
                'name' => $request->name,
                'color' => $request->color
            ];

            $category->update($data);

            return $this->success("Categoria editada com sucesso");
        }catch(\Exception $e) {
            return $this->error("Erro de edição: " . $e->getMessage());
        }
    }

    /**
     * Realiza a captura da categoria pelo id
     * 
     * @param string|int $id
     * 
     * @return mixed
     */
    public function show($id) {
        try{
            return Category::find($id);
        }catch(\Exception $e) {
            return $this->error("Erro de captura da categoria: " . $e->getMessage());
        }
    }

    /**
     * Realiza o softdelete da categoria
     * 
     * @param \Illuminate\Http\Request $request
     * 
     * @return mixed
     */
    public function destroy(Request $request) {
        try{

            $ids = $request->ids;

            Category::whereIn('id', $ids)
                ->delete();

                return $this->success();
        } catch(\Exception $e) {
            return $this->error("Erro de exclusão: " . $e->getMessage());
        }
    }

    /**
     * Realiza a captura e paginação das categorias do sistema
     * @param \Illuminate\Http\Request $request
     * 
     * @return mixed
     */
    public function paginate(Request $request) {
        $categories = $this->category->search($request->except('_token'))->paginate($request->per_page ?? 10);
        return response()->json($categories);
    }

    /**
     * Realiza a atualização dos status baseado nos ids e status enviados
     * 
     * @param \Illuminate\Http\Request $request
     */
    public function changeStatus(Request $request) {
        try{
            $ids = $request->ids;

            Category::whereIn('id', $ids)
                ->update([
                    'status' => DB::raw('CASE WHEN status = 1 THEN 0 ELSE 1 END')
                ]);
            
            return $this->success($ids);
        }catch(\Exception $e) {
            return $this->error("Erro de atualização: " . $e->getMessage());
        }
    }
}