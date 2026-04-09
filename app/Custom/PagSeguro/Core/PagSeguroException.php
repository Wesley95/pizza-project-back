<?php

namespace App\Custom\PagSeguro\Core;

use Exception;

class PagSeguroException extends Exception
{
    public function render($request){
        return response()->json(["error" => true, "message" => $this->getMessage()]);
    }
}