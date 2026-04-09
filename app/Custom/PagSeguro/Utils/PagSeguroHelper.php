<?php

namespace App\Custom\PagSeguro\Utils;

class PagSeguroHelper {

    /**
     * Realiza a formatação do valor no formato requisitado pelo PagSeguro
     * 
     * @param string $amount
     * 
     * @return string
    */
    public static function formatAmountValue(string $amount) {
        return str_replace([',','.'],'',number_format(is_numeric($amount) ? $amount : 0, 2, '.', ''));
    }
}