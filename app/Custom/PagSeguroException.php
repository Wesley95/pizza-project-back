<?php

namespace App\Pagamento;

class ExceptionsPagSeguroCreditCard{
    /**
     * Método responsável por verificar as exceções do PagSeguro
     * 
     * @param Illuminate\Http\Request $request
     */
    public static function checkException($request, $field_name = "cardNumber")
    {
        if (!isset($request) || $request == "") {
            $error = \Illuminate\Validation\ValidationException::withMessages([
                $field_name => ['Houve uma falha na comunicação e o cartão não pode ser validado.'],
                ]);
            throw $error;
        } else {
            if (isset($request->status)) {
                if ($request->status == 'DECLINED') {
                    $error = \Illuminate\Validation\ValidationException::withMessages([
                            $field_name => ['Não é possível prosseguir. O cartão não foi autorizado.'],
                            ]);
                    throw $error;
                }
            }

            if (isset($request->message)) {
                if ($request->message == "Unauthorized") {
                    $error = \Illuminate\Validation\ValidationException::withMessages([
                        $field_name => ['Transação não autorizada. Erro do sistema.'],
                        ]);
                    throw $error;
                }
            }
        }
    }    
}