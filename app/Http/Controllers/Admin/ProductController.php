<?php

namespace App\Http\Controllers\Admin;

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

class ProductController extends Controller
{
    use ApiResponse;

    private $product;
    private string $image_path = 'uploads' . DIRECTORY_SEPARATOR . 'products';


    public function __construct(ProductRepository $productRepository) {
        $this->product = $productRepository;
    }

    /**
     * Realiza o cadastro de um novo produto
     * @param \App\Http\Requests\Admin\ProductRequest $request
     * 
     * @return mixed
     */
    public function store(ProductRequest $request)
    {
        try{
            $data = [
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'sku' => $request->sku,
                'price' => $request->price,
                'status' => $request->status === 'true',
                'description' => $request->description,
                'discount' => $request->discount,
                'category_id' => $request->category_id,
                'customized' => $request->customized === 'true',
                'highlight' => $request->highlight === 'true',
                'visibility' => $request->visibility,
            ];

            if ($request->hasFile('file')) {
                $img = $request->file('file');
                $file_name = self::toSaveImage($img);

                $data['image'] = $file_name;
            }

            $product = Product::create($data);

            $ingredients = [];

            foreach ($request->ingredients as $item) {
                $ingredients[$item['id']] = [
                    'price' => $item['price'],
                    'included' => $item['included'] === 'true'
                ];
            }

            if($product) {
                $product->ingredients()->sync($ingredients);
            }

            return response()->json($product);

            return $this->created();
        }catch(\Exception $e) {
            return $this->error("Erro de cadastro: " . $e->getMessage());
        }
    }

    /**
     * Realiza a edição do cadastro de um produto
     * @param \App\Http\Requests\Admin\ProductRequest $request
     * 
     * @return mixed
     */
    public function edit(ProductRequest $request)
    {
        try{
            $product = $request->id ? Product::find($request->id) : null;

            if(!$product) {
                return $this->notFound('Produto não encontrado');
            }

            if ($request->hasFile('file')) {
                if($product->image)
                    Storage::disk('public')->delete($this->image_path. DIRECTORY_SEPARATOR ."{$product->image}");

                $img = $request->file('file');
                $file_name = self::toSaveImage($img);

                $product->image = $file_name;
            }

            $data = [
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'sku' => $request->sku,
                'price' => $request->price,
                'status' => $request->status === 'true',
                'description' => $request->description,
                'discount' => $request->discount,
                'category_id' => $request->category_id,
                'customized' => $request->customized === 'true',
                'highlight' => $request->highlight === 'true',
                'visibility' => $request->visibility,
            ];

            $ingredients = [];

            foreach ($request->ingredients as $item) {
                $ingredients[$item['id']] = [
                    'price' => $item['price'],
                    'included' => $item['included'] === 'true'
                ];
            }

            if($product->update($data)) {
                $product->ingredients()->sync($ingredients);
            }

            return $this->success("Produto editado com sucesso");
        }catch(\Exception $e) {
            return $this->error("Erro de edição: " . $e->getMessage());
        }
    }

    /**
     * Realiza a captura do produto pelo id
     * 
     * @param string|int $id
     * 
     * @return mixed
     */
    public function show($id) {
        try {
            $product = Product::with('ingredients')->find($id);

            if (!$product) {
                return $this->notFound("Produto não encontrado");
            }

            $product->image = isset($product->image) ? asset('storage/' . $this->image_path . DIRECTORY_SEPARATOR . $product->image) : null;

            $product->setRelation('ingredients', $product->ingredients->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'slug' => $item->slug,
                    'included' => $item->pivot->included == 1,
                    'price' => $item->pivot->price ?? 0
                ];
            }));

            return response()->json($product);

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
    public function paginate(Request $request) {
        $products = $this->product->search($request->except('_token'))->paginate($request->per_page ?? 10);

        foreach($products as $value)
            $value->image = isset($value->image) ? asset('storage/' . $this->image_path . DIRECTORY_SEPARATOR . $value->image) : null;

        return response()->json($products);
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

        return response()->json($products);
    }

    /**
     * Realiza a atualização dos status baseado nos ids e status enviados
     * 
     * @param \Illuminate\Http\Request $request
     */
    public function changeStatus(Request $request) {
        try{
            $ids = $request->ids;

            Product::whereIn('id', $ids)
                ->update([
                    'status' => DB::raw('CASE WHEN status = 1 THEN 0 ELSE 1 END')
                ]);
            
            return $this->success($ids);
        }catch(\Exception $e) {
            return $this->error("Erro de atualização: " . $e->getMessage());
        }
    }

    /**
     * Realiza o armazenamento da imagem do produto, e verificação de existência
     * 
     * @param mixed img
     */
    private function toSaveImage($img)
    {
        $file_name = '';

        do {
            $file_name = uniqid() . '.' . $img->getClientOriginalExtension();

            $exist = Product::where('image', $file_name)->exists();
        } while ($exist);

        $img->storeAs($this->image_path, $file_name, 'public');

        return $file_name;
    }
}