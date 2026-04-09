<?php

namespace App\Custom\PagSeguro\Builders;

use App\Custom\PagSeguro\Core\PagSeguroException;

class CustomerBuilder {
    /**
     * Define o valor de customer
     * 
     * @var array
     */
    private $customer;

    /**
     * Função responsável por setar o e-mail do cliente
     * 
     * @param array $customer
     * 
     */
    private function setCustomerEmail(array $customer){
        if(isset($customer['email'])){
            if(strlen($customer['email']) < 10 || strlen($customer['email']) > 255)
                throw new PagSeguroException("O e-mail do cliente deve conter de 10 a 255 caractéres");
            
            if(!filter_var($customer['email'], FILTER_VALIDATE_EMAIL))
                throw new PagSeguroException("E-mail do cliente inválido");

            $this->customer['email'] = $customer['email'];
        }

        return $this;
    }

    /**
     * Função responsável por setar o nome do cliente
     * 
     * @param array $customer
     * 
     */
    private function setCustomerName(array $customer){
        if(isset($customer['name'])){
            $customer['name'] = mb_substr($customer['name'], 0, 30);

            if(mb_strlen($customer['name']) < 1 || mb_strlen($customer['name']) > 30)
                throw new PagSeguroException("O nome do cliente deve conter de 1 a 30 caractéres");

            $this->customer['name'] = $customer['name'];
        }

        return $this;
    }

    /**
     * Função responsável por setar o documento do cliente
     * 
     * @param array $customer
     * 
     */
    private function setCustomerTaxId(array $customer){
        if(isset($customer['tax_id'])){
            $tax_id = preg_replace('/\D/', '', trim($customer['tax_id']));
            if(strlen($tax_id) != 11 && strlen($tax_id) != 14)
                throw new PagSeguroException("O documento do cliente deve conter de 11(CPF) ou 14(CNPJ) caractéres");

            $this->customer['tax_id'] = $tax_id;
        }

        return $this;
    }

    /**
     * Função responsável por setar os dados do cliente
     * 
     * @param array $customer
     * 
     */
    public function build(array $customer){
        $this->setCustomerEmail($customer);
        $this->setCustomerName($customer);
        $this->setCustomerTaxId($customer);

        return $this->customer;
    }
}