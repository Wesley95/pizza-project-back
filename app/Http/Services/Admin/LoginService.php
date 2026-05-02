<?php

namespace App\Http\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginService {

    /**
     * @param ?string $email;
     * @param ?string $password
     * 
     * @return mixed     
     */
    public function login(?string $email, ?string $password): string
    {
        $user = User::where([
            'email' => $email,
            'status' => true
        ])->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw new \Exception('Credenciais inválidas');
        }

        return $user->createToken('api-token')->plainTextToken;
    }
}