<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Product;
use App\Models\Repositories\ProductRepository;
use App\Traits\ApiResponse;
use Dotenv\Util\Str as UtilStr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class MenuController extends Controller
{
    use ApiResponse;

    private $product;
    private string $image_path = 'uploads' . DIRECTORY_SEPARATOR . 'products';

    public function __construct(ProductRepository $productRepository) {
        $this->product = $productRepository;
    }

    /**
     * Realiza o retorno dos itens disponíveis no menu
     * @param \Illuminate\Http\Request $request
     * 
     * @return mixed
     */
    public function menu(Request $request) {
        $request->merge([
            'status' => 'ativo',
            'visibility' => 'public',
        ]);

        $products = $this->product->search($request->except('_token'))->get();

        foreach($products as $value)
            $value->image = isset($value->image) ? asset('storage/' . $this->image_path . DIRECTORY_SEPARATOR . $value->image) : null;

        return response()->json($products);
    }

    /**
     * Disponibiliza o produto com os ingredientes
     * 
     * @param string $id
     * 
     * @return mixed
     */
    public function show($id) {
        $product = self::getActivedProducts([$id]);
        $product = $product[0] ?? null;

        if(!$product)
            return $this->notFound("Produto não encontrado");

        $product->image = isset($product->image) ? asset('storage/' . $this->image_path . DIRECTORY_SEPARATOR . $product->image) : null;

        return response()->json($product);
    }

    /**
     * Realiza a checagem de disponibilidade dos produtos
     * 
     * @param \Illuminate\Http\Request $request
     * 
     * @return mixed
     */
    public function checkAvailability(Request $request) {
        try {
            $product = self::getActivedProducts([$request->productId ?? 0])[0] ?? null;
            
            if (!$product) {
                return $this->notFound("Produto não disponível ou não encontrado");
            }

            $product->image = isset($product->image) ? asset('storage/' . $this->image_path . DIRECTORY_SEPARATOR . $product->image) : null;

            $ingredientsReduce = [];

            foreach ($request->ingredients ?? [] as $cur) {
                if (empty($cur['id'])) continue;

                $ingredientsReduce[$cur['id']] = [
                    'checked' => filter_var($cur['checked'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            }

            $product = $product->toArray();

            foreach ($product['ingredients'] as $key => $value) {
                $selected = $ingredientsReduce[$value['id']] ?? null;

                $product['ingredients'][$key]['checked'] = $selected['checked'] ?? false;
            }

            return response()->json($product);

        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Realiza o retorno de um produto baseado no id enviado
     * 
     * @param string|int $id
     */
    private function getActivedProducts($id = [0]) {
        return Product::where([
            'status' => true,
            'visibility' => 'public',
        ])
        ->whereIn('id', $id)
        ->with([
            'ingredients' => function ($query) {
                $query->where('status', true);
            },
            'category'
        ])
        ->get();
    }
}