<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IngredientRequest;
use App\Imports\IngredientsImport;
use App\Models\Ingredient;
use App\Models\Repositories\IngredientRepository;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class IngredientController extends Controller
{
    use ApiResponse;

    private $ingredient;

    public function __construct(IngredientRepository $ingredientRepository) {
        $this->ingredient = $ingredientRepository;
    }

    /**
     * Realiza o cadastro de um novo ingrediente
     * @param \App\Http\Requests\Admin\IngredientRequest $request
     * 
     * @return mixed
     */
    public function store(IngredientRequest $request)
    {
        try{
            Ingredient::create([
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
     * 
     * @return mixed
     */
    public function edit(IngredientRequest $request)
    {
        try{
            $ingredient = $request->id ? Ingredient::find($request->id) : null;

            if(!$ingredient) {
                return $this->notFound('Ingrediente não encontrado');
            }

            $data = [
                'name' => $request->name,
                'status' => $request->status,
                'price' => $request->price,
                'slug' => Str::slug($request->name),
                'description' => $request->description
            ];

            $ingredient->update($data);

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
            return Ingredient::find($id);
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
    public function paginate(Request $request) {
        $ingredients = $this->ingredient->search($request->except('_token'))->paginate($request->per_page ?? 10);
        return response()->json($ingredients);
    }

    /**
     * Realiza a atualização dos status baseado nos ids e status enviados
     * 
     * @param \Illuminate\Http\Request $request
     */
    public function changeStatus(Request $request) {
        try{
            $ids = $request->ids;

            Ingredient::whereIn('id', $ids)
                ->update([
                    'status' => DB::raw('CASE WHEN status = 1 THEN 0 ELSE 1 END')
                ]);
            
            return $this->success($ids);
        }catch(\Exception $e) {
            return $this->error("Erro de atualização: " . $e->getMessage());
        }
    }

    public function import(Request $request) {
        try {
            Excel::import(new IngredientsImport, $request->file('file'));

            return response()->json([
                'success' => true,
                'message' => 'Importação realizada com sucesso!'
            ]);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $errors = [];

            foreach ($e->failures() as $failure) {
                $errors[] = [
                    'linha' => $failure->row(),
                    'coluna' => $failure->attribute(),
                    'erros' => $failure->errors(),
                ];
            }

            return response()->json([
                'success' => false,
                'errors' => $errors
            ], 422);
        }
    }
}