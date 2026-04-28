<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderShippingData extends Model
{
    use HasFactory;

    public $table = "order_shipping_data";

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'document',
        'email',
        'phone',
        'cep',
        'street',
        'neighborhood',
        'complement',
        'number',
        'uf',
        'city',
        'reference',
        'is_delivery',
        'order_id',
    ];

    public $timestamps = false;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_delivery' => 'boolean'
    ];

    public function order() {
        return $this->hasOne(Order::class, 'order_id','id');
    }
}
