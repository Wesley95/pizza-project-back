<?php

namespace App\Custom\PagSeguro\Builders;

use App\Custom\PagSeguro\Core\PagSeguroException;
use App\Custom\PagSeguro\Utils\PagSeguroHelper;

class QRCodesBuilder {

    /**
     * Define o valor do objeto qrcodes
     * @var array $qrcode
     */
    private array $qrcode;

    /**
     * Inicializador da classe responsável por montar o objeto address
     * @param boolean $sandbox
     */
    public function __construct()
    {
        $this->qrcode = [];
    }

    /**
     * Função responsável por setar o valor da data de expiração do pix, seguindo as regras da API Order PIX
     * Formato: “yyyy-MM-dd”
     * 
     * @param string $date
     */
    public function setPixDueDate(string $date){
        if(isset($date)){
            $dateTime = new \DateTime($date);
                
            if (!$dateTime || $dateTime <= now())
                throw new PagSeguroException("A data de expiração estar no formato yyyy-MM-dd e deve ser válida");

            $this->qrcode['expiration_date'] = $date;
        }

        return $this;
    }

    /**
     * Função responsável por setar o valor de amount, seguindo as regras da API Order PIX
     * Valor a ser cobrado em centavos.
     * Apenas números inteiros positivos. Exemplo:
     * - R$ 1.500,99 = 150099
     * 
     * @param string $amount
     * 
     */
    public function setAmount(string $amount = ""){
        if(!is_numeric($amount) || $amount == '0' || strlen($amount) > 9)
            throw new PagSeguroException('O valor do produto deve ser numérico, maior do que zero e não deve conter mais do que 9 caracéres.');

        $this->qrcode['amount'] = [
            'value' => PagSeguroHelper::formatAmountValue($amount)
        ];

        return $this;
    }

    /**
     * Função responsável por setar os dados do endereço do comprador
     * 
     */
    public function build(){
        return $this->qrcode;
    }
}