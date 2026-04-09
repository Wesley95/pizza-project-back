<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class CPF implements Rule{
    public function __construct(){
        //
    }

    private static function cpf($value)
	{
        if (preg_match('/^(.)\1*$/u', $value)) return false;
		$value = str_replace(['.','-'],'',$value);
        
        if(strlen($value) != 11) return false;
		
		$cut_aux = substr($value, 0, 9);
		$digit = substr($value,9, 11);		
		
		if(self::incrementCpfNumber($cut_aux) == (int)$digit[0])
		{
			$cut_aux = substr($value, 0, 10);
			
			return self::incrementCpfNumber($cut_aux) == (int)$digit[1];
        }		

		return false;
	}
	
	private static function incrementCpfNumber($value)
	{
		$total_sum = 0;
		$value_aux = 0;		
		
		for($l = strlen($value)+1;$l > 1; $l--)
		{
			$total_sum += ($value[$value_aux] * $l);
			$value_aux++;			
		}
		if(($total_sum % 11) < 2) $total_sum = 0;
		else $total_sum = 11 - ($total_sum % 11);
		
		return $total_sum;
	}

    public function passes($attribute, $value){
        return $this->cpf($value);
    }

    public function message(){
        return 'CPF Inválido.';
    }
}