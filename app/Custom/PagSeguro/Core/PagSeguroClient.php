<?php

namespace App\Custom\PagSeguro\Core;

class PagSeguroClient {

    /**
     * Define o valor de sandbox
     * @var boolean
     */
    private bool $sandbox;

    /**
     * Define o token do pagseguro
     * @var string
     */
    protected string $token;

    /**
     * Define a url base
     * @var string
     */
    protected string $base_url;

    /**
     * Define o bearer token
     * @var string
     */
    protected string $bearer_token;

    /**
     * Inicializador da classe de requisições do PagSeguroV2
     * @param boolean $sandbox
     */
    public function __construct()
    {
        $this->sandbox = config('pagseguro.sandbox');
        $this->token = config('pagseguro.token');

        $sandbox_link = $this->sandbox ?  'sandbox.' : '';
        $this->base_url = 'https://'.$sandbox_link.'api.pagseguro.com';

        // $this->setBearerToken();
    }

    /**
     * Realiza a execução da requisição
     * 
     * @param string $endpoint
     * @param string $method
     * @param array $post_fields
     * @param array $header
     * 
     * @return string|bool
    */
    protected function request(string $endpoint, string $method = "PUT", array $post_fields = [], array $header = []){
        try{
            $curl = curl_init();

            $headers = empty($header) ? [
                "Authorization: Bearer " . $this->token,
                "Content-Type: application/json"
            ] : $header;

            curl_setopt_array($curl, array(
                CURLOPT_URL => $endpoint,
                CURLINFO_HEADER_OUT => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
            ));

            if (!empty($post_fields)) {
                curl_setopt_array($curl, array(
                    CURLOPT_POSTFIELDS => json_encode($post_fields)
                ));
            }

            $response = curl_exec($curl);

            if(curl_error($curl)){
                throw new PagSeguroException("Ocorreu um erro na execução do curl" . curl_error($curl));
            }

            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if ($status >= 400) {
                throw new PagSeguroException("Erro na API: HTTP $status - $response");
            }

            curl_close($curl);

            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new PagSeguroException('Erro ao decodificar resposta');
            }

            return $data;
        }catch(\Exception $e){
            throw new PagSeguroException($e->getMessage());
        }
    }
}