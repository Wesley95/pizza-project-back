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

    public function teste()
    {
        $client = new \GuzzleHttp\Client();

        $client_id = config('pagseguro.client_id');
        $client_secret = config('pagseguro.client_secret');

        $response = $client->post('https://sandbox.api.pagseguro.com/oauth2/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'grant_type' => 'client_credentials',
                'scope' => 'create.certificate orders.read orders.write',
            ]),
        ]);

        return json_decode($response->getBody(), true);
    }
}