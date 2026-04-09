<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    public $table = "orders";

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'status',
        'payment_status',
        'transaction_id',
        'payment_data',
        'token',
        'total'
    ];

    public $timestamps = true;

    public function orderProducts() {
        return $this->hasMany(OrderProduct::class, 'order_id','id');
    }

    public function shippingData() {
        return $this->hasOne(OrderShippingData::class, 'order_id','id');
    }
}
