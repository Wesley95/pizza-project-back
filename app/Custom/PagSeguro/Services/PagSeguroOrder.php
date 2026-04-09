<?php

namespace App\Custom\PagSeguro\Services;

use App\Custom\PagSeguro\Core\PagSeguroClient;

class PagSeguroOrder extends PagSeguroClient {

    /**
     * Realiza o envio do objeto para criação de pedido/order para a api do PagSeguro
     * 
     * @param array $data
     */
    public function create($data)
    {
        return $this->request($this->base_url . '/orders', 'POST', $data);
    }

    /**
     * Realiza o envio do id para requisição dos dados do pedido
     * 
     * @param string $orderId
     */
    public function get($orderId)
    {
        return $this->request($this->base_url . "/orders/{$orderId}", 'GET');
    }
}