<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductIngredientTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_ingredient', function (Blueprint $table) {
            $table->id();
            $table->double('price', 11,9);
            $table->boolean('included')->default(false);
            $table->unsignedBigInteger('ingredient_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_ingredient');
    }
}
