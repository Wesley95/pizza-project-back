<?php

namespace App\Custom\PagSeguro\Builders;

use App\Custom\PagSeguro\Core\PagSeguroException;

class CardBuilder {

    /**
     * Define o valor do objeto card | cartão de crédito | cartão de débito
     * @var array
     */
    private array $card;

    /**
     * Inicializador da classe que gera o objeto card
     */
    public function __construct()
    {
    }

    /**
     * Inicialização do objeto card
     */
    private function initializeCard(){
        $this->card = [
            'holder' => []
        ];
    }

    /**
     * Função responsável por setar o número do cartão de crédito
     * Número do cartão de crédito ou cartão de débito.
     * 
     * @param array $card
     * 
     */
    private function setCardNumber(array $card){
        if(!isset($card['number']) || strlen($card['number']) < 14 || strlen($card['number']) > 19 || !is_numeric($card['number']))
            throw new PagSeguroException('O número do cartão é obrigatório e seu valor deve conter entre 14 e 19 caractéres');

        $this->card['number'] = $card['number'];

        return $this;
    }

    /**
     * Função responsável por setar o valor do mês de expiração
     * Mês de expiração do cartão de crédito, cartão de débito ou token de rede.
     * 
     * @param array $card
     * 
     */
    private function setCardExpirationMonth(array $card){
        if(!isset($card['exp_month']) || strlen($card['exp_month']) < 1 || strlen($card['exp_month']) > 2 || !is_numeric($card['exp_month']))
            throw new PagSeguroException('O mês de expiração deve ser numérico com 1/2 caractéres');

        if(!($card['exp_month'] >= 1 && $card['exp_month'] <= 12))
            throw new PagSeguroException("Mês de expiração inválido");
    
        $this->card['exp_month'] = $card['exp_month'];

        return $this;
    }

    /**
     * Função responsável por setar o valor do ano de expiração
     * Ano de expiração do cartão de crédito, cartão de débito ou token de rede.
     * 
     * @param array $card
     * 
     */
    private function setCardExpirationYear(array $card){
        if(!isset($card['exp_year']) || strlen($card['exp_year']) < 2 || strlen($card['exp_year']) > 4 || !is_numeric($card['exp_year']))
            throw new PagSeguroException('O ano de expiração deve ser numérico com 2/4 caractéres');

        $year = (int) $card['exp_year'];
        if ($year < (int) date('Y'))
            throw new PagSeguroException("Ano de expiração inválido");

        $this->card['exp_year'] = $card['exp_year'];
        
        return $this;
    }

    /**
     * Função responsável por setar o valor do CVV do cartão
     * Código de Segurança do cartão de crédito, cartão de débito ou token de rede.
     * 
     * @param array $card
     * 
     */
    private function setCardCVV(array $card){
        if(!isset($card['security_code']) || strlen($card['security_code']) < 3 || strlen($card['security_code']) > 4 || !is_numeric($card['security_code']))
            throw new PagSeguroException('O CVV(Código de verificação) deve ser numérico com 3/4 caractéres');

        $this->card['security_code'] = $card['security_code'];
        
        return $this;
    }

    /**
     * Função responsável por setar o store do card.
     * Indica se o cartão deverá ser armazenado no PagSeguro para futuras compras.
     * - Se informar false ou omitir esse parâmetros não será armazenado.
     * - Informe true para que seja armazenado, na resposta da requisição você terá o token do cartão em payment_method.card.id. Função indisponível para o método de pagamento cartão de débito e token de rede.
     * 
     * @param array $card
     * 
     */
    private function setCardStore(array $card){
        if(!isset($card['store']) || !is_bool($card['store'])){
            throw new PagSeguroException("O campo store é obrigatório para cartão de crédito e deve ser booleano");
        }

        $this->card['store'] = $card['store'];
        
        return $this;
    }

    /**
     * Função responsável por setar os dados do protador do cartão de crédito, débito ou token de rede.
     * 
     * @param array $card
     * 
     */
    private function setCardHolder(array $card){
        if(isset($card['name'])){
            if(strlen($card['name']) < 1 || strlen($card['name']) > 30)
                throw new PagSeguroException("O nome do portador do cartão deve conter entre 1 e 30 caractéres");

            $this->card['holder']['name'] = $card['name'];
        }

        return $this;
    }

    /**
     * 
     */
    public function fromArray(array $card)
    {
        $this->initializeCard();

        if (isset($card['id'])) {
            return $this->buildFromId($card);
        }

        if (isset($card['encrypted'])) {
            return $this->buildFromEncrypted($card);
        }

        return $this->buildFromRaw($card);
    }

    /**
     * Identificador PagSeguro do cartão de crédito salvo (Cartão Tokenizado pelo PagSeguro). 
     * Função indisponível para o método de pagamento cartão de débito e token de rede.
     * 
     * @param array $card
     * 
     * @return mixed
     */
    private function buildFromId(array $card) {
        if(strlen($card['id']) != 41)
            throw new PagSeguroException('O id deve conter 41 caractéres');

        $this->card['id'] = $card['id'];

        return $this;
    }

    /**
     * Trabalha com o encrypted do cartão para identificação direto com o PagSeguro
     * 
     * @param array $card
     * 
     * @return mixed
     */
    private function buildFromEncrypted(array $card) {
        $this->card['encrypted'] = $card['encrypted'];
        unset($this->card['holder']);
        $this->setCardStore($card);

        return $this;
    }

    /**
     * Monta o objeto do cartão com os dados vindos do formulário
     * 
     * @param array $card
     * 
     * @return mixed
     */
    private function buildFromRaw(array $card) {
        $this->setCardNumber($card);
        $this->setCardExpirationMonth($card);
        $this->setCardExpirationYear($card);
        $this->setCardCVV($card);
        $this->setCardHolder($card);

        //APENAS PARA CARTÃO DE CRÉDITO
        $this->setCardStore($card);
    }

    /**
     * Função responsável por setar o valor de payment_method.card, seguindo as regras da API Charge V2 PagSeguro
     * Objeto contendo os dados de cartão de crédito, cartão de débito ou token de rede.
     * 
     * @param array $card
     * 
     * @return mixed
     */
    public function build(array $card){
        $this->fromArray($card);
        return $this->card;
    }
}