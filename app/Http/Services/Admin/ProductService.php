<?php

namespace App\Http\Services\Admin;

use App\Http\Requests\Admin\ProductRequest;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductService {

    /**
     * @param \App\Http\Requests\Admin\ProductRequest $request
     * @param string $path
     * 
     */
    public function store(ProductRequest $request, string $path) : Product
    {
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
            $file_name = self::toSaveImage($img, $path);

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

        return $product;
    }

    /**
     * Realiza a atualização dos dados do produto
     * 
     * @param \App\Http\Requests\Admin\ProductRequest $request
     * @param string $path
     */
    public function update(ProductRequest $request, string $path) : void
    {
        $product = Product::findOrFail($request->id ?? 0);

        if ($request->hasFile('file')) {
            if($product->image)
                Storage::disk('public')->delete($path. DIRECTORY_SEPARATOR ."{$product->image}");

            $img = $request->file('file');
            $file_name = self::toSaveImage($img, $path);

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
    }

    /**
     * Disponibiliza o produto
     * 
     * @param mixed $id
     * @param string $path
     * 
     * @return \App\Models\Product
     * 
     */
    public function show(mixed $id, string $path) : Product
    {
        $product = Product::with('ingredients')->findOrFail($id);

        $product->image = isset($product->image) ? asset('storage/' . $path . DIRECTORY_SEPARATOR . $product->image) : null;

        $product->setRelation('ingredients', $product->ingredients->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'slug' => $item->slug,
                'included' => $item->pivot->included == 1,
                'price' => $item->pivot->price ?? 0
            ];
        }));

        return $product;
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
        Product::whereIn('id', $ids)
            ->update([
                'status' => DB::raw('CASE WHEN status = 1 THEN 0 ELSE 1 END')
            ]);
    }

        /**
     * Realiza o armazenamento da imagem do produto, e verificação de existência
     * 
     * @param mixed $img
     * @param string $path
     */
    private function toSaveImage(mixed $img, string $path) : string
    {
        $file_name = '';

        do {
            $file_name = uniqid() . '.' . $img->getClientOriginalExtension();

            $exist = Product::where('image', $file_name)->exists();
        } while ($exist);

        $img->storeAs($path, $file_name, 'public');

        return $file_name;
    }
}