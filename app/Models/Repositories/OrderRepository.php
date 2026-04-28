<?php

namespace App\Models\Repositories;

use App\Models\Order;
use App\Models\Repositories\AbstractRepository;
use Illuminate\Support\Facades\DB;

class OrderRepository extends AbstractRepository {

    public function __construct(Order $model) {
        $this->model = $model;
    }

    /**
     * Função responsável por aplicar um filtro na busca para pedidos
     * 
     * @param array $filter
     */
    public function search($filter){
        
        $this->model = $this->model;

        $status = $filter['status'] ?? null;
        $payment_status = $filter['payment_status'] ?? null;

        if($status) {
            $this->model = $this->model->where('status', $status);
        }

        if($payment_status) {
            $this->model = $this->model->where('payment_status', $payment_status);
        }

        return $this->model;
    }

    public function ordersJoin() {
        return $this->model
        ->select('id', 'created_at', 'status', 'payment_status', 'total', 'token')
        ->with([
            'shippingData',
            'orderProducts',
            'orderProducts.product:id,image',
            'orderProducts.orderProductIngredients'
        ])
        ->get();
    }
}
