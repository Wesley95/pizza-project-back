<?php

namespace App\Custom\PagSeguro\Builders;

use App\Custom\PagSeguro\Core\PagSeguroException;
use App\Custom\PagSeguro\Utils\PagSeguroHelper;

class ChargeBuilder {
    /**
     * Constante que representa o valor fixo de currency (PagSeguro só tem suporte para valores em moeda nacional)
     */
    const CURRENCY = "BRL";

    /**
     * Define o valor de charge
     * @var array
     */
    private array $charge;

    /**
     * Define o valor do objeto card | cartão de crédito | cartão de débito
     * @var array
     */
    private array $card;

    /**
     * Define o valor do objeto boleto | boleto bancário
     * @var array
     */
    private array $ticket;

    /**
     * Define o valor do objeto amount
     * @var array
     */
    private array $amount_obj;

    /**
     * Define o valor do objeto payment_method
     * @var array
     */
    private array $payment_method;

    /**
     * Define o valor do objeto address
     * @var array
     */
    private array $address;

    /**
     * Define o valor do objeto customer
     * @var array
     */
    private array $customer;

    /**
     * Define o valor do objeto recurring
     * @var array
     */
    private array $recurring;

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
        $this->initializeCharge();
        $this->initializeCard();
        $this->initializeAmount();
        $this->initializePaymentMethod();
        $this->initializeAddress();
        $this->initializeRecurring();
    }

    /**
     * Inicialização do objeto charge
     */
    private function initializeCharge(){
        $this->charge = [];
    }

    /**
     * Inicialização do objeto card
     */
    private function initializeCard(){
        $this->card = [];
    }

    /**
     * Inicialização do objeto amount
     */
    private function initializeAmount(){
        $this->amount_obj = [
            'currency' => self::CURRENCY
        ];
    }

    /**
     * Inicialização do objeto payment_method
     */
    private function initializePaymentMethod(){
        $this->payment_method = [];
    }

    /**
     * Inicialização do objeto address
     */
    private function initializeAddress(){
        $this->address = [];
    }

    /**
     * Inicialização do objeto recurring
     */
    private function initializeRecurring(){
        $this->recurring = [];
    }

    /**
     * Função responsável por setar a referência, seguindo as regras da API Charge V2 PagSeguro
     * 
     * @param string $reference
     * 
     */
    public function setReference(string $reference = ""){
        $this->charge['reference_id'] = $reference;

        return $this;
    }

    /**
     * Função responsável por setar a descrição, seguindo as regras da API Charge V2 PagSeguro
     * 
     * @param string $description
     * 
     */
    public function setDescription(string $description = ""){
        $description = mb_substr($description, 0, 64);

        if(mb_strlen($description) == 0 || mb_strlen($description) > 64)
            throw new PagSeguroException("A descrição deve conter entre 1 e 64 caractéres.");

        $this->charge['description'] = $description;

        return $this;
    }

    /**
     * Função responsável por setar o valor de amount, seguindo as regras da API Charge V2 PagSeguro
     * Valor a ser cobrado em centavos.
     * Apenas números inteiros positivos. Exemplo:
     * - R$ 1.500,99 = 150099
     * 
     * @param string $amount
     * 
     */
    public function setAmount(string $value = ""){
        if(!is_numeric($value) || $value == '0' || strlen($value) > 9)
            throw new PagSeguroException('O valor do produto deve ser numérico, maior do que zero e não deve conter mais do que 9 caracéres.');

        $this->amount_obj['value'] = PagSeguroHelper::formatAmountValue($value);

        return $this;
    }

    /**
     * Função responsável por setar o valor de payment_method, seguindo as regras da API Charge V2 PagSeguro
     * 
     * @param array $payment
     * 
     */
    public function setPaymentMethod(array $payment){
        $this->setPaymentType($payment);
        $this->setCapture($payment);
        $this->setInstallments($payment);
        $this->setCard($payment['card'] ?? []);

        return $this;
    }

    /**
     * Função responsável por setar o valor de type, seguindo as regras da API Charge V2 PagSeguro
     * 
     * @param array $request
     * 
     */
    private function setPaymentType(array $payment){
        $types = ['BOLETO', 'CREDIT_CARD'];

        if(!isset($payment['type']))
            throw new PagSeguroException('O tipo de pagamento é obrigatório');
    
        if(!in_array(strtoupper($payment['type']), $types))
            throw new PagSeguroException('O tipo de pagamento deve ser ['.implode(', ',$types).']');

        $this->payment_method['type'] = $payment['type'];
    }

    /**
     * Função responsável por setar o valor da data de expiração do boleto, seguindo as regras da API Charge V2 PagSeguro
     * Formato: “yyyy-MM-dd”
     * 
     * @param string $date
     */
    public function setTicketDueDate(string $date){
        if(isset($date)){
            $dateTime = \DateTime::createFromFormat('Y-m-d', $date);

            if (!$dateTime || $dateTime->format('Y-m-d') !== $date)
                throw new PagSeguroException("A data de expiração deve conter 10 caractéres no formato yyyy-MM-dd e deve ser válida");

            $this->ticket['due_date'] = $date;
        }

        return $this;
    }

    /**
     * Função responsável por setar o valor de capture, seguindo as regras da API Charge V2 PagSeguro
     * 
     * Parâmetro que indica se uma transação de cartão de crédito deve ser apenas pré-autorizada (reserva o valor da cobrança no cartão do cliente por até 5 dias) 
     * ou se a transação deve ser capturada automaticamente (cobrança realizada em apenas um passo).
     * - Informar false para pré-autorizar
     * - Ou true para cobrança em um passo.
     * 
     * @param array $request
     * 
     */
    private function setCapture(array $payment){
        if($payment['type'] == "CREDIT_CARD" && !isset($payment['capture']))
            throw new PagSeguroException("O campo capture é obrigatório para pagamentos do tipo CREDIT_CARD(Cartão de Crédito)");

        if(isset($payment['capture'])){
            if(!is_bool($payment['capture']))
                throw new PagSeguroException("O campo capture deve ser booleano");

            $this->payment_method['capture'] = $payment['capture'];
        }
    }

    /**
     * Função responsável por setar o valor de installments, seguindo as regras da API Charge V2 PagSeguro
     * 
     * @param array $payment
     * 
     */
    private function setInstallments(array $payment){
        if($payment['type'] == "CREDIT_CARD" && !isset($payment['installments']))
            throw new PagSeguroException("O campo installments é obrigatório para pagamentos do tipo CREDIT_CARD(Cartão de Crédito)");
        
        if(isset($payment['installments'])){
            if(strlen($payment['installments']) == 0 || strlen($payment['installments']) > 2 || !is_numeric($payment['installments']))
                throw new PagSeguroException("O campo installments deve ser numérico e conter no máximo 2 números");

            $this->payment_method['installments'] = $payment['installments'];
        }
    }

    /**
     * Função responsável por setar o valor de payment_method.card, seguindo as regras da API Charge V2 PagSeguro
     * Objeto contendo os dados de cartão de crédito, cartão de débito ou token de rede.
     * 
     * @param array $card
     * 
     */
    public function setCard(array $card){
        $this->payment_method['card'] = $card;
        return $this;
    }

    /**
     * Função responsável por setar os dados do endereço do comprador
     * 
     * @param array $address
     * 
     */
    public function setAddress(array $address){
        $this->address = $address;
        return $this;
    }

    /**
     * Função responsável por setar os dados do cliente
     * 
     * @param array $customer
     * 
     */
    public function setCustomer(array $customer){
        $this->customer = $customer;
        return $this;
    }

    /**
     * Função responsável por definir se a cobrança é recorrente ou não
     * 
     * @param array $recurring
     * 
     */
    public function setRecurring(array $recurring){
        $types = ['INITIAL','SUBSEQUENT'];

        if(isset($recurring['type'])){
            $type = strtoupper($recurring['type']);
            if(!in_array($type, $types))
                throw new PagSeguroException('O tipo de recorrência deve ser ['. implode(', ', $types) .']');

            $this->recurring['type'] = $type;
        }

        return $this;
    }

    /**
     * Realiza a montagem do objeto boleto
     * 
     * @return mixed
     */
    private function mountTicket() {
        $this->payment_method['boleto'] = $this->ticket;

        if(!empty($this->customer))
            $this->payment_method['boleto']['holder'] = $this->customer;

        if(!empty($this->address))
            $this->payment_method['boleto']['holder']['address'] = $this->address;
    }
    
    // /**
    //  * Realiza a montagem do objeto recurring
    //  * 
    //  * @return mixed
    //  */
    // private function mountRecurring() {
    //     if(!empty($this->recurring))
    //         $this->charge['recurring'] = $this->recurring;
    // }

    /**
     * Função responsável por montar o objeto principal - charge
     * 
     * @return mixed
     */
    public function build(){
        switch($this->payment_method['type']){
            case "BOLETO":
                $this->mountTicket();
            break;
        }        

        $this->charge['amount'] = $this->amount_obj;
        $this->charge['payment_method'] = $this->payment_method;

        return $this->charge;
    }
}