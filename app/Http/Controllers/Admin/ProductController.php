<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Services\Admin\ProductService;
use App\Models\Repositories\ProductRepository;
use App\Traits\ApiResponse;

class ProductController extends Controller
{
    use ApiResponse;

    private ProductRepository $product;
    private string $image_path = 'uploads' . DIRECTORY_SEPARATOR . 'products';


    public function __construct(ProductRepository $productRepository) {
        $this->product = $productRepository;
    }

    /**
     * Realiza o cadastro de um novo produto
     * 
     * @param \App\Http\Requests\Admin\ProductRequest $request
     * @param \App\Http\Services\Admin\ProductService $productService
     * 
     * @return mixed
     */
    public function store(ProductRequest $request, ProductService $productService)
    {
        try{
            $product = $productService->store($request, $this->image_path);
            return $this->created($product);
        }catch(\Exception $e) {
            return $this->error("Erro de cadastro: " . $e->getMessage());
        }
    }

    /**
     * Realiza a edição do produto
     * 
     * @param \App\Http\Requests\Admin\ProductRequest $request
     * @param \App\Http\Services\Admin\ProductService $productService
     * 
     * @return mixed
     */
    public function edit(ProductRequest $request, ProductService $productService)
    {
        try{
            $productService->update($request, $this->image_path);
            return $this->success("Produto editado com sucesso");
        }catch(\Exception $e) {
            return $this->error("Erro de edição: " . $e->getMessage());
        }
    }

    /**
     * Realiza a captura do produto pelo id
     * 
     * @param string|int $id
     * @param \App\Http\Services\Admin\ProductService $productService
     * 
     * @return mixed
     */
    public function show($id, ProductService $productService) {
        try {
            $product = $productService->show($id, $this->image_path);
            return $this->success($product);

        } catch (\Exception $e) {
            return $this->error("Erro de captura do produto: " . $e->getMessage());
        }
    }

    /**
     * Realiza a captura e paginação dos produtos do sistema
     * @param \Illuminate\Http\Request $request
     * 
     * @return mixed
     */
    public function get(Request $request) {
        $products = $this->product->search($request->except('_token'))->paginate($request->per_page ?? 10);

        foreach($products as $value)
            $value->image = isset($value->image) ? asset('storage/' . $this->image_path . DIRECTORY_SEPARATOR . $value->image) : null;

        return $this->success($products);
    }

    /**
     * Realiza o retorno dos itens disponíveis no menu
     * @param \Illuminate\Http\Request $request
     * 
     * @return mixed
     */
    public function menu(Request $request) {
        $products = $this->product->search($request->except('_token'))->get();

        foreach($products as $value)
            $value->image = isset($value->image) ? asset('storage/' . $this->image_path . DIRECTORY_SEPARATOR . $value->image) : null;

        return $this->success($products);
    }

    /**
     * Realiza a atualização dos status baseado nos ids e status enviados
     * 
     * @param \Illuminate\Http\Request $request
     * @param \App\Http\Services\Admin\ProductService $productService
     */
    public function changeStatus(Request $request, ProductService $productService) {
        try{
            $ids = $request->ids;
            
            $productService->changeStatus($ids);

            return $this->success($ids);
        }catch(\Exception $e) {
            return $this->error("Erro de atualização: " . $e->getMessage());
        }
    }
}