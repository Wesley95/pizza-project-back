<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderProduct extends Model
{
    use HasFactory;

    public $table = "order_products";

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'original_price',
        'applied_discount_amount',
        'price',
        'quantity',
        'product_id',
        'order_id'
    ];

    public $timestamps = false;

    public function orderProductIngredients() {
        return $this->hasMany(OrderProductIngredient::class, 'order_product_id', 'id');
    }

    public function order() {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
}
