<?php

namespace App\Custom\PagSeguro\Builders;

use App\Custom\PagSeguro\Core\PagSeguroException;
use App\Custom\PagSeguro\Utils\PagSeguroHelper;

class OrderBuilder {

    /**
     * Define o valor de order
     * @var array
     */
    private array $order;

    /**
     * Inicializador da classe de requisições do PagSeguroV2
     * @param boolean $sandbox
     */
    public function __construct()
    {
        $this->initFields();
    }

    /**
     * Initializa os campos do array principal
     */
    private function initFields(){
        $this->initializeOrder();
    }

    /**
     * Inicialização do objeto order
     */
    private function initializeOrder(){
        $this->order = [
            'reference_id' => null,
            'customer' => [],
            'items' => []
        ];
    }

    /**
     * Realiza a implementação do endereço de entrega do pedido
     * 
     * @param array $address
     * 
     * @return this;
     */
    public function setShippingData(array $address) {
        $this->order['shipping'] = [
            'address' => $address
        ];
        return $this;
    }

    /**
     * Função responsável por setar os dados do cliente
     * 
     * @param array $data
     * 
     */
    public function setCustomer(array $data){
        $this->order['customer'] = $data;
        return $this;
    }

    /**
     * Função responsável por setar o item ao pedido, seguindo as regras da API Order V2 PagSeguro
     * 
     * @param array $item
     */
    public function setItem(array $item){
        if (!isset($item['name']) || strlen($item['name']) > 255)
            throw new PagSeguroException("Nome do item inválido");

        if (!isset($item['quantity']) || $item['quantity'] <= 0)
            throw new PagSeguroException("Quantidade inválida");

        if (!isset($item['unit_amount']) || $item['unit_amount'] <= 0)
            throw new PagSeguroException("Valor inválido");

        $this->order['items'][] = [
            'name' => $item['name'],
            'quantity' => (int)$item['quantity'],
            'unit_amount' => PagSeguroHelper::formatAmountValue($item['unit_amount'])
        ];

        return $this;
    }

    public function setReference(string $reference) {
        $this->order['reference_id'] = $reference;
        return $this;
    }

    /**
     * Realiza a montagem e retorno do objeto order
     */
    public function build() {
        return $this->order;
    }
}