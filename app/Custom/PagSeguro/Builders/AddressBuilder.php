<?php

namespace App\Custom\PagSeguro\Builders;

use App\Custom\PagSeguro\Core\PagSeguroException;

class AddressBuilder {

    /**
     * Define o valor do objeto address
     * @var array
     */
    private array $address;

    /**
     * Inicializador da classe responsável por montar o objeto address
     * @param boolean $sandbox
     */
    public function __construct()
    {
        $this->address = [];
    }

    /**
     * Função responsável por setar o nome da rua da compra
     * 
     * @param array $address
     * 
     */
    private function setAddressStreet(array $address){
        if(isset($address['street'])){
            if(strlen($address['street']) < 1 || strlen($address['street']) > 160)
                throw new PagSeguroException("O nome da rua conter entre 1 e 160 caractéres");

            $this->address['street']  = $address['street'];
        }

        return $this;
    }

    /**
     * Função responsável por setar o número da residência
     * 
     * @param array $address
     * 
     */
    private function setAddressNumber(array $address){
        if(isset($address['number'])){
            if(strlen($address['number']) < 1 || strlen($address['number']) > 20)
                throw new PagSeguroException("O número da residência deve conter entre 1 e 20 caractéres");

            $this->address['number'] = $address['number'];
        }

        return $this;
    }

    /**
     * Função responsável por setar o complemento da residência
     * 
     * @param array $address
     * 
     */
    private function setAddressComplement(array $address){
        if(isset($address['complement']) && strlen($address['complement'])){
            if(strlen($address['complement']) < 1 || strlen($address['complement']) > 40)
                throw new PagSeguroException("O complemento deve conter entre 1 e 40 caractéres");

            $this->address['complement'] = $address['complement'];
        }

        return $this;
    }

    /**
     * Função responsável por setar o bairro da residência
     * 
     * @param array $address
     * 
     */
    private function setAddressLocality(array $address){
        if(isset($address['locality'])){
            if(strlen($address['locality']) < 1 || strlen($address['locality']) > 60)
                throw new PagSeguroException("O nome do bairro deve conter entre 1 e 60 caractéres");

            $this->address['locality'] = $address['locality'];
        }

        return $this;
    }

    /**
     * Função responsável por setar a cidade da residência
     * 
     * @param array $address
     * 
     */
    private function setAddressCity(array $address){
        if(isset($address['city'])){
            if(strlen($address['city']) < 1 || strlen($address['city']) > 90)
                throw new PagSeguroException("O nome da cidade deve conter entre 1 e 90 caractéres");

            $this->address['city'] = $address['city'];
        }

        return $this;
    }

    /**
     * Função responsável por setar o estado da residência
     * 
     * @param array $address
     * 
     */
    private function setAddressRegion(array $address){
        if(isset($address['region'])){
            if(strlen($address['region']) < 1 || strlen($address['region']) > 50)
                throw new PagSeguroException("O nome do estado deve conter entre 1 e 50 caractéres");

            $this->address['region'] = $address['region'];
        }

        return $this;
    }

    /**
     * Função responsável por setar o código do estado da residência
     * 
     * @param array $address
     * 
     */
    private function setAddressRegionCode(array $address){
        $codes = ['RO','AC','AM','RR','PA','AP','TO','MA','PI','CE','RN','PB','PE','AL','SE','BA','MG','ES','RJ','SP','PR','SC','RS','MS','MT','GO','DF'];
        if(isset($address['region_code'])){
            $uf = strtoupper($address['region_code']);
            if(strlen($uf) != 2)
                throw new PagSeguroException("O código do estado deve conter 2 caractéres");

            if(!in_array($uf, $codes))
                throw new PagSeguroException("Código de Estado inválido");

            $this->address['region_code'] = $uf;
        }

        return $this;
    }

    /**
     * Função responsável por setar o país da residência
     * 
     * @param array $address
     * 
     */
    private function setAddressCountry(array $address){
        if(isset($address['country'])){
            if(strlen($address['country']) < 1 || strlen($address['country']) > 50)
                throw new PagSeguroException("O nome do país deve conter entre 1 e 50 caractéres");

            $this->address['country'] = $address['country'];
        }

        return $this;
    }

    /**
     * Função responsável por setar o código postal da residência
     * 
     * @param array $address
     * 
     */
    private function setAddressPostalCode(array $address){
        if(isset($address['postal_code'])){
            $postal_code = preg_replace('/\D/', '', trim($address['postal_code']));
            if(strlen($postal_code) != 8 || !is_numeric($postal_code))
                throw new PagSeguroException("O código postal deve conter 8 caractéres e ser numérico");

            $this->address['postal_code'] = $postal_code;
        }

        return $this;
    }

    /**
     * Função responsável por setar os dados do endereço do comprador
     * 
     * @param array $address
     * 
     */
    public function build(array $address){
        $this->setAddressStreet($address);
        $this->setAddressNumber($address);
        $this->setAddressComplement($address);
        $this->setAddressLocality($address);
        $this->setAddressCity($address);
        $this->setAddressRegion($address);
        $this->setAddressRegionCode($address);
        $this->setAddressCountry($address);
        $this->setAddressPostalCode($address);

        return $this->address;
    }
}