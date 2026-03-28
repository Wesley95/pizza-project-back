<?php

namespace App\Models\Repositories;

use App\Models\Product;
use App\Models\Repositories\AbstractRepository;

class ProductRepository extends AbstractRepository {

    public function __construct(Product $model) {
        $this->model = $model;
    }

    /**
     * Função responsável por aplicar um filtro na busca pelos usuários cadastros no sistema
     * 
     * @param array $filter
     */
    public function search($filter){
        
        $this->model = $this->model->with('category');

        $search = $filter['search'] ?? null;
        $status = $filter['status'] ?? null;
        $category_id = $filter['category_id'] ?? null;

        if($search) {
            $this->model = $this->model->where('name', 'like', "%$search%")->orWhere('slug', 'like', "%$search%");
        }

        if($status) {
            $this->model = $this->model->where('status', $status == 'ativo');
        }

        if($category_id) {
            $this->model = $this->model->where('category_id', $category_id);
        }

        return $this->model;
    }
}
