<?php

namespace App\Http\Services\Admin;

use App\Models\Category;
use App\Models\Order;
use App\Models\Repositories\OrderRepository;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OrderService {

    /**
     * Realiza o retorno da listagem formatada para o painel do admin
     * 
     * @param array $filter
     * @param string $image_path
     * @param \App\Models\Repositories\OrderRepository $orderRepository
     * 
     * @return array
     */
    public function get(array $filter, string $image_path, OrderRepository $orderRepository) : array
    {
        $grouped = $orderRepository->getCountByStatus($filter);
        $orders = $orderRepository->ordersJoin($filter)->toArray();
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
                        $cur_p['image'] = isset($cur_image) ? asset('storage/' . $image_path . DIRECTORY_SEPARATOR . $cur_image) : null;
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

        return [
            $formattedOrders,
            $grouped
        ];
    }

    /**
     * Realiza a alteração do status da ordem e retorna os dados para atualização
     * 
     * @param array $filter
     * @param \App\Models\Repositories\OrderRepository $orderRepository
     * 
     * @return array
     */
    public function changeStatus(array $filter, OrderRepository $orderRepository) : array
    {
        $order = Order::findOrFail($filter['id'] ?? 0);

        $order->update([
            'status' => $filter['newStatus']
        ]);

        unset($filter['id']);
        unset($filter['newStatus']);

        $orders = $orderRepository->ordersJoin($filter, false, ['id','status'])->toArray();
        $grouped = $orderRepository->getCountByStatus($filter);

        return [
            $orders,
            $grouped
        ];
    }
}