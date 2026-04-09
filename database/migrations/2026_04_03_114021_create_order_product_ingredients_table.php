<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderProductIngredientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_product_ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->decimal('price', 10,2)->default(0);
            $table->boolean('is_extra')->default(false);
            $table->unsignedBigInteger('ingredient_id')->nullable();
            $table->unsignedBigInteger('order_product_id')->nullable();
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->onDelete('set null');
            $table->foreign('order_product_id')->references('id')->on('order_products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_product_ingredients');
    }
}
