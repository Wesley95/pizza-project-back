<?php

namespace App\Custom\PagSeguro\Core;

class PagSeguroRequest extends PagSeguroClient {

    public function createCredencials() {
        return $this->request($this->base_url . "/oauth2/application", 'POST', [
            'name' => 'Pizzaria'
        ], [
            "Authorization: Bearer " . $this->token,
            "Content-Type: application/json",
            'Accept: application/json'
        ]);
    }

    public function createToken() {
        return $this->request($this->base_url . "/oauth2/token", 'POST', [
            'grant_type' => 'client_credentials',
            'scope' => 'create.certificate orders.read orders.write'
        ], [
            'Authorization' => 'Basic ' . base64_encode(config('pagseguro.client_id') . ":" . config('pagseguro.client_secret')),
            'Content-Type' => 'application/x-www-form-urlencoded'
        ]);
    }

    /**
     * Função responsável por criar uma public key
     * 
     * @return mixed
     */
    public function createPublicKey(){
        return $this->request($this->base_url . "/public-keys",'POST',[
            'type' => 'card'
        ],[
            'Accept: application/json',
            'Authorization: Bearer ' . $this->token,
            'Content-type: application/json',
        ]);
    }
}