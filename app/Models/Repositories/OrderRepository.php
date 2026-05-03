<?php

namespace App\Models\Repositories;

use App\Models\Order;
use App\Models\Repositories\AbstractRepository;
use Carbon\Carbon;
use DateTime;
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
        $query = $this->model;

        $status = $filter['status'] ?? null;
        $payment_status = $filter['payment_status'] ?? null;

        if($status) {
            $query = $query->where('status', $status);
        }

        if($payment_status) {
            $query = $query->where('payment_status', $payment_status);
        }

        return $query;
    }

    /**
     * Recupera os pedidos e filtra, baseado no filtro enviado
     * 
     * @param array $filter
     */
    public function ordersJoin(array $filter, bool $with_eager_relation = true, array $columns = []) {
        $columns = !empty($columns) ? $columns : [
            'id',
            'created_at',
            'status',
            'payment_status',
            'total',
            'token'
        ];

        $query = $this->model
            ->select($columns);

        if($with_eager_relation) {
            $query = $query->with([
                'shippingData',
                'orderProducts',
                'orderProducts.product:id,image',
                'orderProducts.orderProductIngredients'
            ]);
        }

        $query = $this->filter($filter, $query);       
            
        return $query->get();
    }

    public function getCountByStatus(array $filter) {
        $query = $this->model->select('status', DB::raw('COUNT(*) as total'));

        unset($filter['status']);
        
        $query = $this->filter($filter, $query);
        return $query->groupBy('status')->get();
    }

    private function filter(array $data, \Illuminate\Database\Eloquent\Builder $query) {
        $status = $data['status'] ?? "";
        $ids = !empty($data['ids']) && is_array($data['ids']) ? $data['ids'] : [];
        
        //DATE
        $type = $data['type'] ?? "";
        $period_value = $data['period_value'] ?? "";
        $start = $data['start'] ?? "";
        $end = $data['end'] ?? "";

        if($status && $status !== 'all') {
            switch($status) {
                case "actived":
                    $query = $query->whereIn('status', [
                        'pending','confirmed','preparing','ready','out_for_delivery'
                    ]);
                    break;
                default:
                    $query = $query->where('status', $status);
                break;
            }
        }

        if(count($ids) > 0) {
            $query = $query->whereIn('id', $ids);
        }

        if($type) {
            switch($type) {
                case "period":
                        if($period_value && is_numeric($period_value)) {
                            $query = $query->where('created_at', '>=', Carbon::now()->subDays($period_value));
                        }
                    break;
                case "custom":
                        if($start && count(explode('-', $start)) == 3) {
                            $query = $query->where('created_at', '>=', Carbon::createFromFormat('Y-m-d', $start)->startOfDay());
                        }

                        if($end && count(explode('-', $end)) == 3) {
                            $query = $query->where('created_at', '<=', Carbon::createFromFormat('Y-m-d', $end)->endOfDay());
                        }
                    break;
            }
        }

        return $query;
    }
}
