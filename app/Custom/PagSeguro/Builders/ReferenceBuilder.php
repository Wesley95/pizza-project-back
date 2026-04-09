<?php

namespace App\Custom\PagSeguro\Builders;

use App\Custom\PagSeguro\Core\PagSeguroException;

class ReferenceBuilder {
    
    /**
     * Define o valor de reference
     * 
     * @var string
     */
    private $reference;

    public function __construct() {
        $this->reference = "";
    }

    /**
     * Função responsável por setar a referência, seguindo as regras da API Charge V2 PagSeguro
     * 
     * @param string $reference
     * 
     */
    public function setReference(string $reference = ""){
        if(strlen($reference) == 0 || strlen($reference) > 64)
            throw new PagSeguroException("A referência deve conter entre 0 e 64 caractéres.");

        $this->reference = $reference;

        return $this;
    }

    /**
     * Função responsável por setar os dados do cliente
     * 
     * @param string $reference
     * 
     */
    public function build(string $reference){
        $this->setReference($reference);

        return $this->reference;
    }
}