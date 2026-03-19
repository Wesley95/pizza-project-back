<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\IngredientController;
use App\Http\Controllers\Admin\UserController;
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

Route::group(['as' => 'admin','prefix' => 'admin'], function() {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function(){
    Route::group(['as' => 'admin', 'prefix' => 'admin'], function(){
        Route::group(['as' => 'users', 'prefix' => 'users'], function() {
            Route::get('/', [UserController::class, "paginate"]);
            Route::post('/add', [UserController::class, "store"]);
            Route::post('/edit', [UserController::class, "edit"]);
            Route::get('/{id}', [UserController::class, "show"])->whereNumber('id');
            Route::post('/delete', [UserController::class, "destroy"]);
            Route::post('/change-status', [UserController::class, "changeStatus"]);
        });

        Route::group(['as' => 'category','prefix' => 'category'], function() {
            Route::get('/', [CategoryController::class, 'paginate']);
            Route::get('/{id}', [CategoryController::class, 'show'])->whereNumber('id');
            Route::post('/add', [CategoryController::class, 'store']);
            Route::post('/edit', [CategoryController::class, "edit"]);
            Route::post('/delete', [CategoryController::class, "destroy"]);
            Route::post('/change-status', [CategoryController::class, "changeStatus"]);
        });

        Route::group(['as' => 'ingredient','prefix' => 'ingredient'], function() {
            Route::get('/', [IngredientController::class, 'paginate']);
            Route::get('/{id}', [IngredientController::class, 'show'])->whereNumber('id');
            Route::post('/add', [IngredientController::class, 'store']);
            Route::post('/edit', [IngredientController::class, "edit"]);
            Route::post('/change-status', [IngredientController::class, "changeStatus"]);
            Route::post('/import', [IngredientController::class, "import"]);
        });

        Route::get('/check-token', function(Request $request) {
            return $request->user();
        });
    });
});

