<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Services\Admin\CategoryService;
use App\Models\Category;
use App\Models\Repositories\CategoryRepository;
use App\Traits\ApiResponse;

class CategoryController extends Controller
{
    use ApiResponse;

    private CategoryRepository $category;

    public function __construct(CategoryRepository $categoryRepo) {
        $this->category = $categoryRepo;
    }

    /**
     * Realiza o cadastro de um novo usuário administrador
     * @param \App\Http\Requests\Admin\CategoryRequest $request
     * @param \App\Http\Services\Admin\CategoryService $categoryService
     * 
     * @return mixed
     */
    public function store(CategoryRequest $request, CategoryService $categoryService)
    {
        try{
            $categoryService->create([
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
     * @param \App\Http\Services\Admin\CategoryService $categoryService
     * 
     * @return mixed
     */
    public function edit(CategoryRequest $request, CategoryService $categoryService)
    {
        $categoryService->update($request->id, $request->only(['name', 'color']));

        return $this->success("Categoria editada com sucesso");
    }

    /**
     * Realiza a captura da categoria pelo id
     * 
     * @param string|int $id
     * 
     * @return mixed
     */
    public function show(string|int $id)
    {
        try{
            return $this->success(Category::findOrFail($id));
        }catch(\Exception $e) {
            return $this->error("Erro de captura da categoria: " . $e->getMessage());
        }
    }

    /**
     * Realiza o softdelete da categoria
     * 
     * @param \Illuminate\Http\Request $request
     * @param \App\Http\Services\Admin\CategoryService $categoryService
     * 
     * @return mixed
     */
    public function destroy(Request $request, CategoryService $categoryService) {
        try{
            $categoryService->delete($request->ids ?? []);
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
        $categories = 
            isset($request->page) ? 
            $this->category->search($request->except('_token'))->paginate($request->per_page ?? 10) : 
            Category::get();

        return $this->success($categories);
    }

    /**
     * Realiza a atualização dos status baseado nos ids e status enviados
     * 
     * @param \Illuminate\Http\Request $request
     * @param \App\Http\Services\Admin\CategoryService $categoryService
     */
    public function changeStatus(Request $request, CategoryService $categoryService) {
        try{
            $ids = $request->ids ?? [];
            $categoryService->changeStatus($ids ?? []);

            return $this->success($ids);
        }catch(\Exception $e) {
            return $this->error("Erro de atualização: " . $e->getMessage());
        }
    }
}