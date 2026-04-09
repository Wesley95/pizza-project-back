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
        $products = $this->product->getActivedProducts([$id]);
        $product = self::adjustProducts($products)[0] ?? null;

        if(!$product)
            return $this->notFound("Produto não encontrado");

        $product->image = isset($product->image) ? asset('storage/' . $this->image_path . DIRECTORY_SEPARATOR . $product->image) : null;

        return response()->json($product);
    }

    /**
     * Realiza a checagem de disponibilidade do produto
     * 
     * @param \Illuminate\Http\Request $request
     * 
     * @return mixed
     */
    public function checkAvailability(Request $request) {
        try {
            $products = $this->product->getActivedProducts([$request->productId ?? 0]) ?? null;
            $product = $products[0] ?? null;
            
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
     * Realiza a checagem de disponibilidade dos produtos
     * 
     * @param \Illuminate\Http\Request $request
     * 
     * @return mixed
     */
    public function updateCartValues(Request $request) {
        try {
            $data = $request->all();
            $ids = array_map(fn($e) => $e['productId'] , $data);
            $products = $this->product->getActivedProducts($ids ?? []) ?? null;
            $products = $products->keyBy(fn($item) => "id-" . $item->id);

            $prods = [];

            foreach($data as $d) {
                if(!$d) continue;
                $id = $d['productId'];

                $product = $products['id-'.$id] ?? null;

                if(!$product) continue;

                $discountValue = $product->price * ($product->discount / 100);

                $obj = [
                    'count' => isset($d['count']) ? intval($d['count']) : 1,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'originalPrice' => $product->price,
                    'price' => $product->price - ($discountValue),
                    'description' => $product->description,
                    'discount' => $product->discount,
                    'discountValue' => $discountValue,
                    'image' => isset($product->image) ? asset('storage/' . $this->image_path . "/" . $product->image) : null,
                    'productId' => $product->id,
                    'customized' => $product->customized,
                ];

                $product->setRelation('ingredients', ($product->ingredients ?? [])->keyBy(function($item, $key) {
                    return 'id-' . $item['id'];
                }));

                $ingredients = [];

                foreach (($d['ingredients'] ?? []) as $cur) {
                    if (empty($cur['id']) || !$product->ingredients->has('id-' . $cur['id'])) continue;
                    
                    $ing = $product->ingredients['id-' . $cur['id']];

                    if(!$ing->status) continue;

                    $ingredients[] = [
                        'checked' => $ing->pivot->included ? false : filter_var($cur['checked'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'description' => $ing->description,
                        'id' => $ing->id,
                        'included' => $ing->pivot->included,
                        'name' => $ing->name,
                        'price' => $ing->pivot->price,
                        'slug' => $ing->slug,
                    ];
                }
                $obj['ingredients'] = $ingredients ?? [];

                $prods[] = $obj;
            }

            return $this->success([
                'products' => $prods
            ]);

        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Realiza a limpeza dos produtos, retirando os ingredientes caso o produto não seja customizável
     * 
     * @param Illuminate\Database\Eloquent\Collection<Product> $products
     * 
     * @return Illuminate\Database\Eloquent\Collection<Product>
     */
    private function adjustProducts($products) {
        return $products->each(function ($product) {
            if (!$product->customized) {
                $filtered = $product->ingredients
                    ->filter(function ($ingredient) {
                        return $ingredient->pivot->included == 1;
                    })
                    ->values();

                $product->setRelation('ingredients', $filtered);
            }
        });
    }
}