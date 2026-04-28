<?php

namespace App\Custom\PagSeguro\Services;

use App\Custom\PagSeguro\Core\PagSeguroClient;

class PagSeguroCharge extends PagSeguroClient {

    /**
     * Realiza o envio dos dados para criação da cobrança direta
     * 
     * @param array $data
     * 
     * @return mixed
     */
    public function createCharge($data) {
        return $this->request($this->base_url . "/charges", "POST", $data);
    }

    /**
     * Realiza a criação do pagamento tipo pix, aguardando o retorno com QRCodes e CopyPaste
     * 
     * @param $orderId
     * 
     * @return mixed
     */
    public function createPix($orderId)
    {
        return $this->request($this->base_url . "/orders/{$orderId}/charges", 'POST', [
            "payment_method" => [
                "type" => "PIX"
            ]
        ]);
    }

    /**
     * Realiza a criação do pagamento tipo credit_card.
     * 
     * @param $orderId
     * @param $cardData
     * 
     * @return mixed
     */
    public function createCreditCard($orderId, $cardData)
    {
        return $this->request($this->base_url . "/orders/{$orderId}/pay", 'POST', $cardData, [
            'Authorization: Bearer ' . $this->token,
            'Accept: application/json',
            'Content-Type: application/json',
        ]);
    }

    /**
     * Realiza a busca das parcelas
     * 
     * @return mixed
     */
    public function checkFees(array $data) 
    {
        $url = http_build_query($data);

        return $this->request($this->base_url . "/charges/fees/calculate?". $url, 'GET', [], [
            'Authorization: Bearer ' . $this->token,
            'Accept: application/json',
            'Content-Type: application/json',
        ]);
    }

    /**
     * Realiza a captura do pagamento com o id
     * 
     * @param $chargeId
     * 
     * @return mixed
     */
    public function get($chargeId)
    {
        return $this->request('GET', "/charges/{$chargeId}");
    }
}