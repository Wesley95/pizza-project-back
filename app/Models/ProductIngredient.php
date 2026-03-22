<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductIngredient extends Model
{
    use HasFactory;

    public $table = "product_ingredient";

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',        
        'ingredient_id',
        'price',
        'included'
    ];

    public $timestamps = false;
}
