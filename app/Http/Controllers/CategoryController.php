<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Traits\ApiResponse;

class CategoryController extends Controller
{
    use ApiResponse;

    public function __construct() { }

    /**
     * Realiza a captura e listagem das categorias do sistema
     * 
     * @return mixed
     */
    public function get() {
        return $this->success(Category::get());
    }
}