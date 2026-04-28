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
        'total',
        'expiration_date'
    ];

    public $timestamps = true;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expiration_date' => 'datetime'
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->expiration_date = now()->addMinutes(20);
        });
    }

    public function orderProducts() {
        return $this->hasMany(OrderProduct::class, 'order_id','id');
    }

    public function shippingData() {
        return $this->hasOne(OrderShippingData::class, 'order_id','id');
    }
}
