<?php

namespace App\Traits;

trait ApiResponse
{
    protected function success($data = null, string $message = 'Sucesso', int $statusCode = 200)
    {
        return response()->json([
            'success' => true,
            'data'      => $data,
            'message'   => $message,
        ], $statusCode);
    }

    protected function error(string $message = 'Erro de validação', int $statusCode = 400, $data = null)
    {
        return response()->json([
            'success' => false,
            'data'      => $data,
            'message'   => $message,
        ], $statusCode);
    }

    protected function created(string $message = 'Cadastrado com sucesso', $data = null)
    {
        return $this->success($data, $message, 201);
    }


    protected function notFound(string $message = 'Recurso não encontrado', $data = null)
    {
        return $this->error($message, 404, $data);
    }

    protected function unauthorized(string $message = 'Não autorizado', $data = null)
    {
        return $this->error($message, 401, $data);
    }

    protected function internalServerError(string $message = 'Erro interno do servidor, favor contatar o responsável', $data = null)
    {
        return $this->error($message, 500, $data);
    }
}
