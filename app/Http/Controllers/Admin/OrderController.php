<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Services\Admin\OrderService;
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
     * 
     * @param \Illuminate\Http\Request $request
     * @param \App\Http\Services\Admin\OrderService $orderService
     * 
     * @return mixed
     */
    public function get(Request $request, OrderService $orderService) {
        try{
            $filter = $request->except('_token');
    
            [$formattedOrders, $grouped] = $orderService->get($filter, $this->image_path, $this->order);
    
            return $this->success([
                'orders' => $formattedOrders,
                'grouped' => $grouped
            ]);
        }catch(\Exception $e) {
            return $this->error("Ocorreu um erro durante a busca dos pedidos");
        }
    }

    /**
     * Realiza a atualização do status de uma específica ordem
     * 
     * @param \Illuminate\Http\Request $request
     * @param \App\Http\Services\Admin\OrderService $orderService
     * 
     * @return mixed
     */
    public function changeStatus(Request $request, OrderService $orderService) {
        try{
            [$orders, $grouped] = $orderService->changeStatus($request->except('_token'), $this->order);

            return $this->success([
                'orders' => collect($orders)->keyBy(fn($e) => 'id-'.$e['id']),
                'grouped' => $grouped
            ]);
        }catch(\Exception $e) {
            return $this->error("Erro de atualização: " . $e->getMessage());
        }
    }
}