<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderProductIngredient extends Model
{
    use HasFactory;

    public $table = "order_product_ingredients";

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'price',
        'is_extra',
        'order_product_id'
    ];

    public $timestamps = false;

    public function orderProduct() {
        return $this->belongsTo(OrderProduct::class,'order_product_id', 'id');
    }
}
