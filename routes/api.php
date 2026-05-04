<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\IngredientController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CategoryController as PublicCategoryController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

    
Route::group(['as' => 'admin', 'prefix' => 'admin'], function(){
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function(){
        Route::group(['as' => 'users', 'prefix' => 'users'], function() {
            Route::get('/', [UserController::class, "get"])->name('get');
            Route::post('/add', [UserController::class, "store"])->name('add');
            Route::post('/edit', [UserController::class, "edit"])->name('edit');
            Route::get('/{id}', [UserController::class, "show"])->whereNumber('id')->name('show');
            Route::post('/delete', [UserController::class, "destroy"])->name('destroy');
            Route::post('/change-status', [UserController::class, "changeStatus"])->name('change-status');
        });

        Route::group(['as' => 'category','prefix' => 'category'], function() {
            Route::get('/', [CategoryController::class, 'get'])->name('get');
            Route::get('/{id}', [CategoryController::class, 'show'])->whereNumber('id')->name('show');
            Route::post('/add', [CategoryController::class, 'store'])->name('add');
            Route::post('/edit', [CategoryController::class, "edit"])->name('edit');
            Route::post('/delete', [CategoryController::class, "destroy"])->name('destroy');
            Route::post('/change-status', [CategoryController::class, "changeStatus"])->name('change-status');
        });

        Route::group(['as' => 'ingredient','prefix' => 'ingredient'], function() {
            Route::get('/', [IngredientController::class, 'get'])->name('get');
            Route::get('/{id}', [IngredientController::class, 'show'])->whereNumber('id')->name('show');
            Route::post('/add', [IngredientController::class, 'store'])->name('add');
            Route::post('/edit', [IngredientController::class, "edit"])->name('edit');
            Route::post('/change-status', [IngredientController::class, "changeStatus"])->name('change-status');
            Route::post('/import', [IngredientController::class, "import"])->name('import');
        });

        Route::group(['as' => 'product','prefix' => 'product'], function() {
            Route::get('/', [ProductController::class, 'get'])->name('get');
            Route::get('/{id}', [ProductController::class, 'show'])->whereNumber('id')->name('show');
            Route::post('/add', [ProductController::class, 'store'])->name('add');
            Route::post('/edit', [ProductController::class, "edit"])->name('edit');
            Route::post('/change-status', [ProductController::class, "changeStatus"])->name('change-status');
            Route::post('/import', [ProductController::class, "import"])->name('import');
        });

        Route::group(['as' => 'order','prefix' => 'order'], function() {
            Route::get('/', [AdminOrderController::class, 'get'])->name('get');
            Route::get('/{id}', [AdminOrderController::class, 'show'])->whereNumber('id')->name('show');
            
            Route::post('/change-status', [AdminOrderController::class, "changeStatus"])->name('change-status');
        });

        Route::get('/check-token', function(Request $request) {
            return $request->user();
        })->name('check-token');
    });
});

Route::prefix('')->group(function() {
    Route::group(['as' => 'menu','prefix' => 'menu'], function() {
        Route::get('/', [MenuController::class, 'menu'])->name('menu');
        Route::get('/{id}', [MenuController::class, 'show'])->whereNumber('id')->name('show');
        Route::get('/check-availability', [MenuController::class, 'checkAvailability'])->name('checkAvailability');
        Route::get('/update-cart', [MenuController::class, 'updateCartValues'])->name('updateCartValues');
    });
    
    Route::group(['as' => 'order','prefix' => 'order'], function() {
        Route::get('/public-key', [OrderController::class, 'getPublicKey'])->name('public-key');
        Route::get('/check-fees', [OrderController::class, 'toCheckFees'])->name('check-fees');
    
        Route::post('/', [OrderController::class, 'create'])->name('create');
        Route::get('/{id}', [OrderController::class, 'show'])->whereNumber('id')->name('show');
        Route::post('/{id}/shipping-data', [OrderController::class, 'setShippingData'])->whereNumber('id')->name('shipping-data');
        Route::post('/{id}/payment', [OrderController::class, 'setPayment'])->whereNumber('id')->name('payment');
        Route::post('/{id}/recreate-order', [OrderController::class, 'recreateOrder'])->whereNumber('id')->name('recreate-pix');
    });

    Route::group(['as' => 'category', 'prefix' => 'category'], function() {
        Route::get('/', [PublicCategoryController::class, 'get'])->name('get');
    });
});
