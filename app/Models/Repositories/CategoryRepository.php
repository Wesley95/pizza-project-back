<?php

namespace App\Models\Repositories;

use App\Models\Category;
use App\Models\Repositories\AbstractRepository;

class CategoryRepository extends AbstractRepository {

    public function __construct(Category $model) {
        $this->model = $model;
    }

    /**
     * Função responsável por aplicar um filtro na busca pelos usuários cadastros no sistema
     * 
     * @param array $filter
     */
    public function search($filter){
        
        $this->model = $this->model;

        $search = $filter['search'] ?? null;
        $status = $filter['status'] ?? null;

        if($search) {
            $this->model = $this->model->where('name', 'like', "%$search%");
        }

        if($status) {
            $this->model = $this->model->where('status', $status == 'ativo');
        }

        return $this->model;
    }
}
