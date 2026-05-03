<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IngredientRequest;
use App\Http\Services\Admin\IngredientService;
use App\Models\Ingredient;
use App\Models\Repositories\IngredientRepository;
use App\Traits\ApiResponse;
use Illuminate\Support\Str;

class IngredientController extends Controller
{
    use ApiResponse;

    private IngredientRepository $ingredient;

    public function __construct(IngredientRepository $ingredientRepository) {
        $this->ingredient = $ingredientRepository;
    }

    /**
     * Realiza o cadastro de um novo ingrediente
     * 
     * @param \App\Http\Requests\Admin\IngredientRequest $request
     * @param \App\Http\Services\Admin\IngredientService $ingredientService
     * 
     * @return mixed
     */
    public function store(IngredientRequest $request, IngredientService $ingredientService)
    {
        try{
            $ingredientService->create([
                'name' => $request->name,
                'status' => $request->status,
                'price' => $request->price,
                'slug' => Str::slug($request->name),
                'description' => $request->description
            ]);

            return $this->created();
        }catch(\Exception $e) {
            return $this->error("Erro de cadastro: " . $e->getMessage());
        }
    }

    /**
     * Realiza a edição do cadastro de um ingrediente
     * @param \App\Http\Requests\Admin\IngredientRequest $request
     * @param \App\Http\Services\Admin\IngredientService $ingredientService
     * 
     * @return mixed
     */
    public function edit(IngredientRequest $request, IngredientService $ingredientService)
    {
        try{
            $ingredientService->update($request->id, [
                'name' => $request->name,
                'status' => $request->status,
                'price' => $request->price,
                'slug' => Str::slug($request->name),
                'description' => $request->description
            ]);
            
            return $this->success("Ingrediente editado com sucesso");
        }catch(\Exception $e) {
            return $this->error("Erro de edição: " . $e->getMessage());
        }
    }

    /**
     * Realiza a captura do ingrediente pelo id
     * 
     * @param string|int $id
     * 
     * @return mixed
     */
    public function show($id) {
        try{
            return $this->success(Ingredient::findOrFail($id));
        }catch(\Exception $e) {
            return $this->error("Erro de captura do ingrediente: " . $e->getMessage());
        }
    }

    /**
     * Realiza a captura e paginação dos ingredientes do sistema
     * @param \Illuminate\Http\Request $request
     * 
     * @return mixed
     */
    public function get(Request $request) {
        $ingredients = 
            isset($request->page) ? 
            $this->ingredient->search($request->except('_token'))->paginate($request->per_page ?? 10) :
            Ingredient::get();

        return $this->success($ingredients);
    }

    /**
     * Realiza a atualização dos status baseado nos ids e status enviados
     * 
     * @param \Illuminate\Http\Request $request
     * @param \App\Http\Services\Admin\IngredientService $ingredientService
     */
    public function changeStatus(Request $request, $ingredientService) {
        try{
            $ids = $request->ids;
            $ingredientService->changeStatus($ids);

            return $this->success($ids);
        }catch(\Exception $e) {
            return $this->error("Erro de atualização: " . $e->getMessage());
        }
    }

    /**
     * Realiza a importação dos dados massivamente
     * 
     * @param \Illuminate\Http\Request $request
     * @param \App\Http\Services\Admin\IngredientService $service
     * 
     * @return mixed
     * 
     */
    public function import(Request $request, IngredientService $service)
    {
        $result = $service->import($request->file('file'));

        if (!$result['success']) {
            return $this->error("Ocorreu um erro durante o processo de importação de ingredientes", 422);
        }

        return $this->success($result);
    }
}