<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    public $table = "products";

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'sku',
        'price',
        'discount',
        'description',
        'status',
        'visibility',
        'customized',
        'highlight',
        'image',
        'category_id'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'customized' => 'boolean',
        'highlight' => 'boolean',
        'actived' => 'boolean'
    ];

    public $timestamps = true;

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'product_ingredient')
            ->withPivot(['price', 'included']);
    }

    public function category() {
        return $this->belongsTo(Category::class);
    }
}
