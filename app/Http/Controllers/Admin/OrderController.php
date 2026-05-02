<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Repositories\OrderRepository;
use App\Traits\ApiResponse;

class OrderController extends Controller
{
    use ApiResponse;

    private OrderRepository $order;
    private string $image_path = 'uploads' . DIRECTORY_SEPARATOR . 'products';

    public function __construct(OrderRepository $orderRepository) {
        $this->order = $orderRepository;
    }

    /**
     * Realiza a captura e paginação dos produtos do sistema
     * @param \Illuminate\Http\Request $request
     * 
     * @return mixed
     */
    public function paginate(Request $request) {
        $countByStatus = $this->order->getCountByStatus();
        $orders = $this->order->ordersJoin($request)->toArray();
        $formattedOrders = [];

        foreach ($orders as $value) {
            $addressFormatted = "";
            $isDelivery = false;
            $hasShippingData = !empty($value['shipping_data']);
            $data = collect($value)->only(["id", "created_at", "status", "payment_status", "total", "token"])->toArray();
            
            /*
            |--------------------------------------------------------------------------
            | Endereço
            |--------------------------------------------------------------------------
            */

            $shippingData = $value['shipping_data'] ?? [];
            if($hasShippingData) {
                $isDelivery = $shippingData['is_delivery'];
                if($isDelivery) {
                    $cep = $shippingData['cep'] ?? "";
                    $street = $shippingData['street'] ?? "";
                    $neighborhood = $shippingData['neighborhood'] ?? "";
                    $number = $shippingData['number'] ?? "";
                    $uf = $shippingData['uf'] ?? "";
                    $city = $shippingData['city'] ?? "";
                    $reference = $shippingData['reference'] ?? "";
                    $complement = $shippingData['complement'] ?? "";
        
                    $streetNumber = collect([$street, $number])
                        ->filter(fn($e) => !empty($e))
                        ->implode(', ');
        
                    $addressFormatted = collect([
                        $streetNumber,
                        $complement,
                        $neighborhood,
                        $city && $uf ? "{$city} - {$uf}" : $city,
                        $cep,
                    ])->filter(fn($e) => !empty($e))
                    ->implode(', ');
        
                    if (!empty($reference)) {
                        $addressFormatted .= " ({$reference})";
                    }
                }
            }

            $products = [];
            if(!empty($value['order_products'])) {
                foreach($value['order_products'] as $p) {
                    $cur_image = $p['product']['image'] ?? '';
                    $cur_p = collect($p)->only(["name", "original_price", "applied_discount_amount", "price", "quantity", "product_id", "order_id"])->toArray();

                    $cur_p['image'] = "";
                    $cur_p['ingredients'] = array_map(function($i) {
                        return [
                            'name' => $i['name'] ?? null,
                            'price' => $i['price'] ?? null,
                            'is_extra' => $i['is_extra'] ?? null
                        ];
                    }, ($p['order_product_ingredients'] ?? []));

                    if(!empty($cur_image)) {
                        $cur_p['image'] = isset($cur_image) ? asset('storage/' . $this->image_path . DIRECTORY_SEPARATOR . $cur_image) : null;
                    }

                    $products[] = $cur_p;
                }
            }

            $data = array_merge($data, [
                'name' => $hasShippingData ? ($shippingData['name'] ?? "") : "Pedido incompleto",
                'phone' => $hasShippingData ? ($shippingData['phone'] ?? "") : "Pedido incompleto",
                'is_delivery' => $isDelivery,
                'address' => $hasShippingData ? ($isDelivery ? $addressFormatted : "") : "Pedido incompleto",
                'products' => $products,
                'incomplete' => !$hasShippingData
            ]);

            $formattedOrders[] = $data;
        }

        return $this->success([
            'orders' => $formattedOrders,
            'grouped' => $countByStatus
        ]);
    }

    /**
     * Realiza a atualização do status de uma específica ordem
     * 
     * @param \Illuminate\Http\Request $request
     */
    public function changeStatus(Request $request) {
        try{
            $order = Order::findOrFail($request->id ?? 0);

            $order->update([
                'status' => $request->status
            ]);
            
            return $this->success([
                'status' => $request->status
            ]);
        }catch(\Exception $e) {
            return $this->error("Erro de atualização: " . $e->getMessage());
        }
    }
}