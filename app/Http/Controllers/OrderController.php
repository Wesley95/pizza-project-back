<?php

namespace App\Http\Controllers;

use App\Custom\PagSeguro\Builders\AddressBuilder;
use App\Custom\PagSeguro\Builders\CardBuilder;
use App\Custom\PagSeguro\Builders\ChargeBuilder;
use App\Custom\PagSeguro\Builders\CustomerBuilder;
use App\Custom\PagSeguro\Builders\OrderBuilder;
use App\Custom\PagSeguro\Builders\QRCodesBuilder;
use App\Custom\PagSeguro\Builders\ReferenceBuilder;
use App\Custom\PagSeguro\Core\PagSeguroRequest;
use App\Custom\PagSeguro\Services\PagSeguroCharge;
use App\Custom\PagSeguro\Services\PagSeguroOrder;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderProductIngredient;
use App\Models\Product;
use App\Models\Repositories\ProductRepository;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Http\Requests\PaymentRequest;
use App\Http\Requests\ShippingDataRequest;
use App\Models\OrderShippingData;

class OrderController extends Controller
{
    use ApiResponse;

    private ProductRepository $product;
    private string $image_path = 'uploads' . DIRECTORY_SEPARATOR . 'products';

    public function __construct(ProductRepository $productRepository) {
        $this->product = $productRepository;
    }

    /**
     * Realiza a captura do pedido caso esteja válido
     * 
     * @param \Illuminate\Http\Request $request
     * 
     * @return mixed
     */
    public function show(Request $request){
        try {
            $id = $request->id ?? 0;
            $token = $request->token ?? "";
            $products = [];

            $order = Order::where([
                'id' => $id,
                'token' => $token
            ])
            ->with('orderProducts','orderProducts.orderProductIngredients','shippingData')->first();

            if(!$order) return $this->notFound('Pedido não encontrado');

            if($order->status != "pending") {
                $message = "";

                switch($order->status) {
                    case "confirmed":
                    case "preparing":
                    case "ready":
                        $message = "Seu pedido está sendo preparado. Tempo estimado: 30-40min";
                        break;
                    case "out_for_delivery":
                            $message = "Seu pedido saiu para a entrega";
                        break;
                    case "delivered":
                        $message = "Pedido entregue.";
                        break;
                    case "cancelled":
                        $message = "O pedido foi cancelado.";
                        break;
                }

                return $this->success([
                    'status'  => $order->status,
                    'paid' => $order->payment_status == 'paid',
                    'available' => false,
                    'message' => $message
                ]);
            }

            $payment_data = json_decode($order->payment_data, true);
            $qrcode = $payment_data['qr_codes'][0] ?? [];
            $order->qrcode = [
                'expiration_date' => $qrcode['expiration_date'] ?? "" ,
                'text' => $qrcode['text'] ?? ""
            ];

            $products_id = $order->orderProducts->map(fn($e) => $e->product_id)->values();
            
            if(count($products_id) > 0) {
                $products = Product::whereIn('id', $products_id)->get()->keyBy(fn($item) => 'id-' . $item->id);
            }
            
            $order_products = [];

            foreach($order->orderProducts as $value) {
                $original_product = $products['id-' . $value['product_id']] ?? null;
                $value = $value;
                $value->image = isset($original_product->image) ? asset('storage/' . $this->image_path . "/" . $original_product->image) : null;

                $order_products[] = $value;
            }

            $order->setRelation('orderProducts', collect($order_products));

            return $this->success([
                'order' => $order
            ]);

        }catch(\Exception $e) {
            return $this->error("Ocorreu um erro na busca do pedido: " . $e->getMessage());
        }
    }

    /**
     * Realiza a criação de um novo pedido
     * @param \Illuminate\Http\Request $request
     * 
     * @return mixed
     */
    public function create(Request $request) {
        try{
            $order = DB::transaction(function () use ($request) {
                $cart = $request->all();
                $product_ids = array_map(fn($e) => $e['productId'] ?? 0 , $cart);
        
                $originalProducts = $this->product->getActivedProducts($product_ids)->keyBy(fn($item) => 'id-' . $item['id']);
        
                $products = [];
                $total = 0;
                foreach($cart as $value) { 
                    if(empty($value) || !$originalProducts->has('id-' . $value['productId'])) continue;
        
                    $original = $originalProducts['id-' . $value['productId']];
        
                    $applied_discount = $original->price * ($original->discount / 100);
                    $final_price = $original->price - $applied_discount;
                    
                    $originalIng = $original->ingredients->keyBy(fn($item) => 'id-' . $item['id']);
                    $ingredients = [];
        
                    foreach(($value['ingredients'] ?? []) as $ing) {
                        if(empty($ing) || !$originalIng->has('id-' . $ing['id'])) continue;
                        $cur_ing = $originalIng['id-' . $ing['id']];
        
                        if(!$cur_ing->status || (!$ing['checked'] && !$ing['included'])) continue;
        
                        $is_extra = !$cur_ing->pivot->included && $ing['checked'];
                        $ing_price = $cur_ing->pivot->price;
        
                        $ingredients[] = [
                            'name' => $cur_ing->name,
                            'price' => $ing_price,
                            'is_extra' => $is_extra,
                            'ingredient_id' => $cur_ing->id,
                        ];
        
                        if($is_extra)
                            $final_price += $ing_price;
                    }
        
                    $quantity = max(1, intval($value['count'] ?? 1));
        
                    $products[] = [
                        'product_id' => $original->id,
                        'quantity' => $quantity,
                        'name' => $original->name,
                        'original_price' => $original->price,
                        'applied_discount_amount' => $applied_discount,
                        'price' => $final_price * $quantity,
                        'ingredients' => $ingredients
                    ];
        
                    $total += ($final_price * $quantity);
                }
        
                do {
                    $token = 'ORD-' . now()->format('ymdHis') . '-' . Str::upper(Str::random(4));
                } while (Order::where('token', $token)->exists());
        
                $order = Order::create([
                    'status' => 'pending',
                    'payment_status' => 'pending',
                    'transaction_id' => null,
                    'payment_data' => null,
                    'token' => $token,
                    'total' => $total
                ]);
        
                if($order) {
                    foreach($products as $p) {
                        $order_product = OrderProduct::create(array_merge($p, [
                            'order_id' => $order->id
                        ]));
        
                        if($order_product && count($p['ingredients']) > 0) {
                            OrderProductIngredient::insert(array_map(function($e) use($order_product) {
                                $e['order_product_id'] = $order_product->id;
                                return $e;                    
                            }, $p['ingredients']));
                        }
                    }
                }

                return $order;
            });

            if($order) {
                return $this->success([
                    'id' => $order->id,
                    'token' => $order->token,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'total' => $order->total,
                    'expiration_date' => $order->expiration_date
                ]);
            }

            return $this->error("Ocorreu um erro durante a criação da ordem de pedido.");

        }catch(\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Realiza o armazenamento dos dados da entrega
     * 
     * @param \App\Http\Requests\ShippingDataRequest $request
     * @param mixed $id
     */
    public function setShippingData(ShippingDataRequest $request, $id) {
        try{
            $order = Order::find($id);

            if(!$order)
                return $this->notFound("A ordem não foi encontrada");

            $shipping = OrderShippingData::create([
                "name" => $request->name,   
                "document" => $request->document,
                "email" => $request->email,
                "phone" => $request->phone,
                "cep" => $request->cep ?? "",
                "street" => $request->street ?? "",
                "neighborhood" => $request->neighborhood ?? "",
                "complement" => $request->complement ?? "",
                "number" => $request->number ?? "",
                "uf" => $request->uf ?? "",
                "city" => $request->city ?? "",
                "reference" => $request->reference ?? "",
                "is_delivery" => $request->shipping == "delivery",
                "order_id" => $order->id,
            ]);

            if($order && $shipping) {
                $response = $this->generateOrder($order, $shipping);

                if(isset($response['id'])) {
                    $order->update([
                        'transaction_id' => $response['id'],
                        'payment_data' => json_encode($response),
                        'expiration_date' => now()->addMinutes(20)
                    ]);

                    return $this->success([
                        'id' => $order->id,
                        'token' => $order->token,
                        'shipping' => collect($shipping)->only([
                            'name', 'document', 'email', 'phone', 'cep', 'street', 'neighborhood', 
                            'complement', 'number', 'uf', 'city', 'reference', 'is_delivery'
                        ])->toArray()
                    ]);
                } else {
                    return $this->error("Ocorreu um erro durante a criação do pedido via PagBank");
                }
            }

            return $this->error("Ocorreu um erro durante a criação do pedido via PagBank");
        }catch(\Exception $e) {
            return $this->error("Ocorreu um erro no armazenamento dos dados de entrega." . $e->getMessage());
        }
    }

    /**
     * Realiza o pagamento via cartão de crédito
     * 
     * @param \App\Http\Requests\PaymentRequest $request
     * @param int $id
     */
    public function setPayment(PaymentRequest $request, $id) {
        try{    
            $order = Order::find($id);
    
            if(!$order)
                return $this->notFound("A ordem não foi encontrada");
    
            if($order->payment_status != 'pending') {
                if($order->payment_status == 'paid')
                    return $this->success([
                        'paid' => true
                    ]);
            }
    
            $ref = (new ReferenceBuilder())->build($order->token);
            $ps_order = (new OrderBuilder())
                ->setReference($ref);
    
            $ps_order = $ps_order->build();
    
            $card = (new CardBuilder())->build([
                'encrypted' => $request->encryptedCard,
                'store' => false
            ]);
    
            $charge = (new ChargeBuilder())
                ->setAmount($order->total)
                ->setPaymentMethod([
                    'capture' => true,
                    'installments' => $request->installments ?? 1,
                    'type' => 'CREDIT_CARD',
                    'card' => $card
                ])->build();

            $response = (new PagSeguroCharge())->createCreditCard($order->transaction_id, [
                'charges' => [
                    array_merge($ps_order, $charge)
                ]
            ]);

            if(isset($response['id'])) {
                $order->update([
                    'payment_data' => json_encode($response)
                ]);
            }
    
            $paid = false;
    
            if (isset($response['id'], $response['charges']) && is_array($response['charges'])) {
                $bigger = null;
                
                foreach ($response['charges'] as $value) {
                    $cur = new \DateTime($value['created_at']) ?? now();

                    if($bigger == null || $cur > new \DateTime($bigger['created_at']) && $value['reference_id'] === $order->token) {
                        $bigger = $value;
                    }
                }
    
                if (isset($bigger['id'])) {
                    $confirmed = $bigger['status'] == "PAID";
                    $paid = $confirmed;

                    $order->update([
                        'status' => $confirmed ? "confirmed" : "pending",
                        'payment_data' => json_encode($response),
                        'payment_status' => $confirmed ? 'paid' : "failed"
                    ]);
                }
            }

            return $this->success([
                'paid' => $paid,
                'available' => !$paid,
                'message' => $paid ? 'Seu pedido foi pago com sucesso e em breve iniciaremos a produção.' : "Ocorreu um problema durante o pagamento.",
                'status' => $order->status
            ]);
        }catch(\Exception $e) {
            return $this->error("Ocorreu um erro no armazenamento dos dados de entrega. " . $e->getMessage());
        }
    }

    /**
     * Realiza a recriação da ordem expirada
     * 
     * @param \Illuminate\Http\Request $request
     */
    public function recreateOrder(Request $request) {
        $id = $request->id ?? 0;
        $token = $request->token ?? "";
        $now = new \DateTime();

        $old_order = Order::where([
            'id' => $id,
            'token' => $token
        ])
        ->with('orderProducts','orderProducts.orderProductIngredients','shippingData')->first();

        if(!$old_order)
            return $this->error("Ordem não encontrada");

        if($old_order->payment_status == 'paid')
            return $this->error("A ordem já se encontra paga");

        if($old_order->status == 'cancelled')
            return $this->error("A ordem já está cancelada");

        $expiration_date = new \DateTime($old_order->expiration_date);
        $seconds = $now->getTimestamp() - $expiration_date->getTimestamp();
        $minutes = $seconds / 60;

        if($minutes >= 15) {
            $old_order->update([
                'status' => 'cancelled'
            ]);

            return $this->error('A ordem não pode mais ser recriada.');
        }

        do {
            $new_token = 'ORD-' . now()->format('ymdHis') . '-' . Str::upper(Str::random(4));
        } while (Order::where('token', $new_token)->exists());

        $new_order = DB::transaction(function () use ($old_order, &$new_order) {
            $new_token = 'ORD-' . now()->format('ymdHis') . '-' . Str::upper(Str::random(6));

            $new_order = Order::create([
                'status' => 'pending',
                'payment_status' => 'pending',
                'token' => $new_token,
                'total' => $old_order->total
            ]);

            foreach ($old_order->orderProducts as $value) {

                $data = collect($value)->only([
                    'applied_discount_amount',
                    'name',
                    'original_price',
                    'price',
                    'product_id',
                    'quantity'
                ])->toArray();

                $data['order_id'] = $new_order->id;

                $order_product = OrderProduct::create($data);

                foreach ($value->orderProductIngredients ?? [] as $i) {
                    $ingredients[] = [
                        "ingredient_id" => $i->ingredient_id,
                        "is_extra" => $i->is_extra,
                        "name" => $i->name,
                        "price" => $i->price,
                        "order_product_id" => $order_product->id
                    ];
                }
            }

            if (!empty($ingredients)) {
                OrderProductIngredient::insert($ingredients);
            }

            $shipping = null;
            if ($old_order->shippingData) {
                $shipping = OrderShippingData::create(array_merge(
                    collect($old_order->shippingData)->only([
                        "cep","city","complement","document","email",
                        "is_delivery","name","neighborhood","number",
                        "phone","reference","street","uf"
                    ])->toArray(),[
                    'order_id' => $new_order->id
                ]));
            }

            $response = $this->generateOrder($new_order, $shipping);

            if(!isset($response['id'])) {
                throw new \Exception("Erro ao gerar cobrança no PagSeguro");
            }

            $new_order->update([
                'transaction_id' => $response['id'],
                'payment_data' => json_encode($response)
            ]);

            $old_order->update([
                'status' => 'cancelled'
            ]);

            return $new_order;
        });

        return $this->success([
            'id' => $new_order->id,
            'token' => $new_order->token,
            'status' => $new_order->status,
            'payment_status' => $new_order->payment_status,
            'total' => $new_order->total,
            'expiration_date' => $new_order->expiration_date,
            'shipping' => collect($new_order->shippingData)->only([
                'name', 'document', 'email', 'phone', 'cep', 'street', 'neighborhood', 
                'complement', 'number', 'uf', 'city', 'reference', 'is_delivery'
            ])->toArray()
        ]);
    }

    /**
     * Gera uma nova ordem para o cliente
     * 
     * @param $order
     * @param $shipping
     */
    private function generateOrder($order, $shipping) {
        $expiration_date = (new \DateTime())->modify('+20 minutes')->format('c');
        
        $ref = (new ReferenceBuilder())->build($order->token);
        $qrcode = (new QRCodesBuilder())
            ->setAmount($order->total)
            ->setPixDueDate($expiration_date)
            ->build();

        $customer = (new CustomerBuilder())->build([
            'name' => $shipping->name,
            'tax_id' => $shipping->document,
            'email' => $shipping->email
        ]);

        $shipping = (new AddressBuilder())->build([
            'postal_code' => $shipping->cep,
            'country' => "BRA",
            'street' => $shipping->street,
            'locality' => $shipping->neighborhood,
            'complement' => $shipping->complement,
            'number' => $shipping->number,
            'region_code' => $shipping->uf,
            'city' => $shipping->city,
        ]);

        $ps_order = (new OrderBuilder())
            ->setReference($ref)
            ->setCustomer($customer)
            ->setShippingData($shipping);

        foreach($order->orderProducts as $p) {
            $ps_order->setItem([
                'name' => $p['name'],
                'quantity' => $p['quantity'],
                'unit_amount' => $p['price']
            ]);
        }
        
        $ps_order = $ps_order->build();

        $ps_order['qr_codes'] = [
            $qrcode
        ];

        return (new PagSeguroOrder())->create($ps_order);
    }

    /**
     * Realiza a checagem das parcelas direto no PagSeguro
     * 
     * @param Illuminate\Http\Request $request
     * 
     */
    public function toCheckFees(Request $request) {
        try{
            $total = str_replace([',','.'],"", Order::where('id',$request->id ?? 0)->value('total') ?? 0);

            $data = [
                'payment_methods' => 'CREDIT_CARD',
                'value' => $total,
                'max_installments' => 4,
                'max_installments_no_interest' => 3,
                'credit_card_bin' => $request->bin ?? '',
            ];
    
            $response = (new PagSeguroCharge())->checkFees($data);
    
            if(empty($response['payment_methods']['credit_card'] ?? [])) {
                return $this->error("Ocorreu um erro na busca dos dados de parcelamento");
            }        
    
            $card = reset($response['payment_methods']['credit_card']);
    
            if(empty($card) || empty($card['installment_plans'])) {
                return $this->error("Ocorreu um erro na busca dos dados de parcelamento");
            }
                
            $plans = $card['installment_plans'] ?? [];
                
            if(empty($plans) || !is_array($plans)) {
                return $this->error("Ocorreu um erro na busca dos dados de parcelamento");
            }
    
            $installments = [];
            foreach($plans as $value) {
                $installment_total = $value['amount']['value'] ?? $value['installment_value'] * $value['installments'];
                $installments[] = [
                    'installment' => $value['installments'],
                    'installment_value' => $value['installment_value'] / 100,
                    'interest_free' => $value['interest_free'],
                    'total' => $installment_total / 100,
                    'interest' => [
                        'value' => abs($total - $installment_total) / 100
                    ]
                ];
            }
            
            return $this->success([
                'installments' => $installments
            ]);
        }catch(\Exception $ex) {
            return $this->success([
                'installments' => [],
                'error' => $ex->getMessage()
            ]);
        }
    }

    /**
     * Gera a chave pública do pagseguro
     * 
     * @return mixed
     */
    public function getPublicKey() {
        $public_key = "";
        
        try{
            $response = (new PagSeguroRequest())->createPublicKey();
            
            $public_key = !isset($response['public_key']) ? "error" : $response['public_key'];
        }catch(\Exception $e){
            $public_key = 'error';
        }

        return $this->success([
            'public_key' => $public_key
        ]);
    }
}