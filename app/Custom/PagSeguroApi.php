<?php

namespace App\Custom;

use App\Exceptions\PagSeguroException;

class PagSeguroApi {
    /**
     * Constante que representa o valor fixo de currency (PagSeguro só tem suporte para valores em moeda nacional)
     */
    const CURRENCY = "BRL";

    /**
     * Define o valor de sandbox
     * @var boolean
     */
    private bool $sandbox;

    /**
     * Define o token do pagseguro
     * @var string
     */
    private string $token;

    /**
     * Define o valor de routes
     * @var array
     */
    private array $routes;

    /**
     * Define o valor de charge
     * @var array
     */
    private array $charge;

    /**
     * Define o valor de order
     * @var array
     */
    private array $order;

    /**
     * Define o valor de items
     * @var array
     */
    private array $items;

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
     * Define o valor do objeto holder
     * @var array
     */
    private array $holder;

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
        $this->sandbox = config('pagseguro.sandbox');
        $this->token = config('pagseguro.token');
        
        $this->initRoutes();
        $this->initFields();
    }

    /**
     * Inicializador das rotas do PagSeguro utilizadas
     */
    private function initRoutes(){
        $sandbox_link = $this->sandbox ?  'sandbox.' : '';
        $link = 'https://'.$sandbox_link.'api.pagseguro.com';

        $this->routes = [
            'charge' => $link.'/charges',
            'public-key' => $link.'/public-keys',
            'order' => $link.'/orders'
        ];
    }

    /**
     * Initializa os campos do array principal
     */
    private function initFields(){
        $this->initializeCharge();
        $this->initializeOrder();
        $this->initializeItems();
        $this->initializeCard();
        $this->initializeAmount();
        $this->initializePaymentMethod();
        $this->initializeAddress();
        $this->initializeCustomer();
        $this->initializeRecurring();
    }

    /**
     * Inicialização do objeto charge
     */
    private function initializeCharge(){
        $this->charge = [];
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
     * Inicialização do objeto items
     */
    private function initializeItems(){
        $this->items = [];
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
     * Inicialização do objeto customer
     */
    private function initializeCustomer(){
        $this->customer = [];
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
    public function setReference(string $reference = ''){
        if(strlen($reference) == 0 || strlen($reference) > 64)
            throw new PagSeguroException("A referência deve conter entre 0 e 64 caractéres.");

        $this->charge['reference_id'] = $reference;

        return $this;
    }

    /**
     * Função responsável por setar a descrição, seguindo as regras da API Charge V2 PagSeguro
     * 
     * @param string $description
     * 
     */
    public function setDescription(string $description = ''){
        $description = mb_substr($description, 0, 64);

        if(mb_strlen($description) == 0 || mb_strlen($description) > 64)
            throw new PagSeguroException("A descrição deve conter entre 1 e 64 caractéres.");

        $this->charge['description'] = $description;

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

        $this->items[] = [
            'name' => $item['name'],
            'quantity' => (int)$item['quantity'],
            'unit_amount' => (int)$item['unit_amount']
        ];

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
    public function setAmount(string $value = ''){
        if(!is_numeric($value) || $value == '0' || strlen($value) > 9)
            throw new PagSeguroException('O valor do produto deve ser numérico, maior do que zero e não deve conter mais do que 9 caracéres.');

        $this->amount_obj['value'] = $value;

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
    public function setCardAttributes(array $card){
        if(!isset($card['id'])){
            if(!isset($card['encrypted'])){
                $this->setCardNumber($card);
                $this->setCardExpirationMonth($card);
                $this->setCardExpirationYear($card);
                $this->setCardCVV($card);
    
                //APENAS PARA CARTÃO DE CRÉDITO
                $this->setCardStore($card);
            }else{
                $this->card['encrypted'] = $card['encrypted'];

                $this->setCardStore($card);
            }
        }else{
            /**
             * Identificador PagSeguro do cartão de crédito salvo (Cartão Tokenizado pelo PagSeguro). 
             * Função indisponível para o método de pagamento cartão de débito e token de rede.
             */
            if(strlen($card['id']) != 41)
                throw new PagSeguroException('O id deve conter 42 caractéres');

            $this->card['id'] = $card['id'];
        }

        return $this;
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
        if(isset($this->payment_method['type']) && $this->payment_method['type'] == "CREDIT_CARD"){
            if(!isset($card['store']) || !is_bool($card['store'])){
                throw new PagSeguroException("O campo store é obrigatório para cartão de crédito e deve ser booleano");
            }

            $this->card['store'] = $card['store'];
        }
    }

    /**
     * Função responsável por setar os dados do protador do cartão de crédito, débito ou token de rede.
     * 
     * @param array $holder
     * 
     */
    public function setCardHolder(array $holder){
        if(isset($holder['name'])){
            if(strlen($holder['name']) < 1 || strlen($holder['name']) > 30)
                throw new PagSeguroException("O nome do portador do cartão deve conter entre 1 e 30 caractéres");

            $this->card['holder']['name'] = $holder['name'];
        }

        return $this;
    }

    /**
     * Função responsável por setar os dados do endereço do comprador
     * 
     * @param array $address
     * 
     */
    public function setAddress(array $address){
        $this->setAddressStreet($address);
        $this->setAddressNumber($address);
        $this->setAddressComplement($address);
        $this->setAddressLocality($address);
        $this->setAddressCity($address);
        $this->setAddressRegion($address);
        $this->setAddressRegionCode($address);
        $this->setAddressCountry($address);
        $this->setAddressPostalCode($address);

        return $this;
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
    }

    /**
     * Função responsável por setar o complemento da residência
     * 
     * @param array $address
     * 
     */
    private function setAddressComplement(array $address){
        if(isset($address['complement'])){
            if(strlen($address['complement']) < 1 || strlen($address['complement']) > 40)
                throw new PagSeguroException("O complemento deve conter entre 1 e 40 caractéres");

            $this->address['complement'] = $address['complement'];
        }
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
    }

    /**
     * Função responsável por setar os dados do cliente
     * 
     * @param array $customer
     * 
     */
    public function setCustomer(array $customer){
        $this->setCustomerEmail($customer);
        $this->setCustomerName($customer);
        $this->setCustomerTaxId($customer);

        return $this;
    }

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
     * Função responsável por montar o objeto principal - charge
     * 
     * @return mixed
     */
    private function mountCharge(){
        switch($this->payment_method['type']){
            case "CREDIT_CARD":
                $this->mountCard();
            break;
            case "BOLETO":
                $this->mountTicket();
            break;
        }        

        $this->charge['amount'] = $this->amount_obj;
        $this->charge['payment_method'] = $this->payment_method;
    }

    /**
     * Função responsável por montar o objeto principal - order
     * 
     * @return mixed
     */
    private function mountOrder(){
        $this->order['customer'] = $this->customer;
        $this->order['items'] = $this->items;

        if (empty($this->order['items'])) {
            throw new PagSeguroException("Pedido deve conter ao menos um item");
        }
    }

    /**
     * Realiza a montagem do objeto card
     * 
     * @return mixed
     */
    private function mountCard() {
        $this->card['holder'] = $this->holder;
        $this->payment_method['card'] = $this->card;

        $this->mountAddress();
        $this->mountCustomer();
        $this->mountRecurring();
    }

    /**
     * Realiza a montagem do objeto boleto
     * 
     * @return mixed
     */
    private function mountTicket() {
        $this->payment_method['boleto'] = $this->ticket;
        $this->payment_method['boleto']['holder'] = $this->customer;
        $this->payment_method['boleto']['holder']['address'] = $this->address;
    }
    
    /**
     * Realiza a montagem do endereço
     * 
     * @return mixed
     */
    private function mountAddress() {
        if(!empty($this->address))
            $this->charge['address'] = $this->address;
    }

    /**
     * Realiza a montagem do objeto customer
     * 
     * @return mixed
     */
    private function mountCustomer() {
        if(!empty($this->customer))
            $this->charge['customer'] = $this->customer;
    }

    /**
     * Realiza a montagem do objeto recurring
     * 
     * @return mixed
     */
    private function mountRecurring() {
        if(!empty($this->recurring))
            $this->charge['recurring'] = $this->recurring;
    }

    /**
     * Função responsável por enviar a cobrança
     * 
     * @return mixed
     */
    public function sendCharge(){
        $this->mountCharge();

        $response = $this->execCurl($this->routes['charge'], 'POST', $this->charge, [
            "Authorization: ".$this->token,
            'Content-Type: application/json;charset=UTF-8',
            'x-api-version:1.0'
        ]);

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new PagSeguroException('Erro ao decodificar resposta');
        }

        return $data;
    }

    /**
     * Função responsável por enviar o pedido
     * 
     * @return mixed
     */
    public function sendOrder(){
        $this->mountOrder();

        $response = $this->execCurl($this->routes['order'], 'POST', $this->order, [
            "Authorization: Bearer {$this->token}",
            'Content-Type: application/json',
        ]);

        return json_decode($response);
    }

    /**
     * Função responsável por criar uma public key
     * 
     * @return mixed
     */
    public function createPublicKey(){
        $response = $this->execCurl($this->routes['public-key'],'POST',[
            'type' => 'card'
        ],[
            'Accept: application/json',
            'Authorization: Bearer ' . $this->token,
            'Content-type: application/json',
        ]);

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new PagSeguroException('Erro ao decodificar resposta');
        }

        return $data;
    }

    /**
     * Realiza uma busca e verificação dos items de uma ordem
     * 
     * @param $order_id
     */
    // public function getCharge($charge){
    //     $response = $this->execCurl($this->routes['charge']."/$charge",'GET',[],[
    //         "Authorization: ".$this->token,
    //         'Content-Type: application/json',
    //         'x-api-version: 1.0',
    //     ]);

    //     return json_decode($response);
    // }

    /**
     * Realiza uma busca e verificação dos items de uma ordem por referencia
     * 
     * @param $order_id
     */
    // public function getChargeByReference($reference){
    //     $response = $this->execCurl($this->routes['charge']."?reference_id=$reference",'GET',[],[
    //         "Authorization: Bearer $this->token",
    //         'Content-Type: application/json',
    //         'x-api-version: 1.0',
    //     ]);

    //     return json_decode($response);
    // }

    /**
     * Realiza a execução da requisição
     * 
     * @param string $url
     * @param string $method
     * @param array $post_fields
     * @param array $header
     * 
     * @return string|bool
    */
    private function execCurl(string $url, string $method = "PUT", array $post_fields = [], array $header = []){
        try{
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLINFO_HEADER_OUT => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $header,
            ));

            if (!empty($post_fields)) {
                curl_setopt_array($curl, array(
                    CURLOPT_POSTFIELDS => json_encode($post_fields)
                ));
            }
    
            $response = curl_exec($curl);
    
            if(curl_error($curl)){
                throw new PagSeguroException("Ocorreu um erro na execução do curl");
            }
    
            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if ($status >= 400) {
                throw new PagSeguroException("Erro na API: HTTP $status - $response");
            }

            curl_close($curl);

            return $response;
        }catch(\Exception $e){
            throw new PagSeguroException($e->getMessage());
        }
    }
}