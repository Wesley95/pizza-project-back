<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Services\Admin\LoginService;
use App\Traits\ApiResponse;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Função responsável por validar os dados de login que foram enviados
     * 
     * @param \Illuminate\Http\Request $request
     * @param \App\Http\Services\Admin\LoginService $loginService
     * 
     * @return mixed
     */
    public function login(Request $request, LoginService $loginService)
    {
        try {
            $token = $loginService->login($request->email, $request->password);
    
            return $this->success([
                'token' => $token
            ]);
        }catch(\Exception $e) {
            return response()->json([
                'message' => 'Não foi possível realizar a autenticação'
            ], 401);
        }
    }
}