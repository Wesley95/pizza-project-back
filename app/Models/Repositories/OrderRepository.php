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

    /**
     * Recupera os pedidos e filtra, baseado no request enviado
     * 
     * @param mixed $request
     */
    public function ordersJoin($request) {
        $this->model = $this->model
            ->select('id', 'created_at', 'status', 'payment_status', 'total', 'token')
            ->with([
                'shippingData',
                'orderProducts',
                'orderProducts.product:id,image',
                'orderProducts.orderProductIngredients'
            ]);

        $status = $request->status ?? "";

        if($status && $status !== 'all') {
            switch($status) {
                case "actived":
                    $this->model = $this->model->whereIn('status', [
                        'pending','confirmed','preparing','ready','out_for_delivery'
                    ]);
                    break;
                default:
                    $this->model = $this->model->where('status', $status);
                break;
            }
        }
            
        return $this->model->get();
    }

    public function getCountByStatus() {
        return $this->model
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get();
    }
}
