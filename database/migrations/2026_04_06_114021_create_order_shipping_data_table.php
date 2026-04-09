<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderShippingDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_shipping_data', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string("document")->nullable();
            $table->string("email")->nullable();
            $table->string("phone")->nullable();
            $table->string("cep")->nullable();
            $table->string("street")->nullable();
            $table->string("neighborhood")->nullable();
            $table->string("complement")->nullable();
            $table->string("number")->nullable();
            $table->string("uf")->nullable();
            $table->string("city")->nullable();
            $table->string("reference")->nullable();
            $table->boolean('is_delivery')->default(true);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_shipping_data');
    }
}
