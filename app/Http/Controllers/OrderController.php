<?php

namespace App\Http\Controllers;

use App\Custom\PagSeguro\Builders\AddressBuilder;
use App\Custom\PagSeguro\Builders\CardBuilder;
use App\Custom\PagSeguro\Builders\ChargeBuilder;
use App\Custom\PagSeguro\Builders\CustomerBuilder;
use App\Custom\PagSeguro\Builders\OrderBuilder;
use App\Custom\PagSeguro\Builders\QRCodesBuilder;
use App\Custom\PagSeguro\Builders\ReferenceBuilder;
use App\Custom\PagSeguro\Core\PagSeguroClient;
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
use App\Custom\PagSeguroApi;
use App\Http\Requests\PaymentRequest;
use App\Http\Requests\ShippingDataRequest;
use App\Models\OrderShippingData;

class OrderController extends Controller
{
    use ApiResponse;

    private $product;
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
            ->with('orderProducts','orderProducts.orderProductIngredients')->first();

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
                $product_ids = array_map(fn($e) => $e['productId'] , $cart);
        
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
                    'token' => $order->token
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
     * @param App\Http\Requests\ShippingDataRequest $request
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
                "cep" => $request->cep,
                "street" => $request->street,
                "neighborhood" => $request->neighborhood,
                "complement" => $request->complement,
                "number" => $request->number,
                "uf" => $request->uf,
                "city" => $request->city,
                "reference" => $request->reference,
                "is_delivery" => $request->shipping == "delivery",
                "order_id" => $order->id,
            ]);

            if($order && $shipping) {
                $expiration_date = (new \DateTime())->modify('+15 minutes')->format('c');
                
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

                $response = (new PagSeguroOrder())->create($ps_order);

                if(isset($response['id'])) {
                    $order->update([
                        'transaction_id' => $response['id'],
                        'payment_data' => json_encode($response)
                    ]);

                    return $this->success([
                        'id' => $order->id,
                        'token' => $order->token
                    ]);
                } else {
                    return $this->error("Ocorreu um erro durante a criação do pedido via PagBank");
                }
            }

            return $this->error("Ocorreu um erro durante a criação do pedido via PagBank");
        }catch(\Exception $e) {
            return $this->error("Ocorreu um erro no armazenamento dos dados de entrega.");
        }
    }

    /**
     * Realiza o pagamento via cartão de crédito
     * 
     * @param App\Http\Requests\PaymentRequest $request
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
    
                return $this->notFound("A ordem não está disponível para pagamento");
            }
    
            $ref = (new ReferenceBuilder())->build($order->token);
            $ps_order = (new OrderBuilder())
                ->setReference($ref);
    
            $ps_order = $ps_order->build();
    
            $exp = explode("/", $request->expiration);
    
            $card = (new CardBuilder())->build([
                'number' => $request->card,
                'exp_month' => $exp[0],
                'exp_year' => "20" . $exp[1],
                'cvv' => $request->cvv,
                'security_code' => $request->cvv,
                'store' => false,
                'name' => $request->name
            ]);
    
            $charge = (new ChargeBuilder())
                ->setAmount($order->total)
                ->setPaymentMethod([
                    'capture' => true,
                    'installments' => 1,
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
                foreach ($response['charges'] as $value) {
                    if (isset($value['status'], $value['reference_id']) && $value['status'] === 'PAID' && $value['reference_id'] === $order->token) {
                        $paid = true;
                        break;
                    }
                }
    
                if ($paid) {
                    $order->update([
                        'status' => 'confirmed',
                        'payment_data' => json_encode($response),
                        'payment_status' => 'paid'
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
            return $this->error(/*"Ocorreu um erro no armazenamento dos dados de entrega. " .*/ $e->getMessage());
        }
        return response()->json($request->all());
    }    

    public function publicKey() {
        $publicKey = file_get_contents(storage_path('app/secure/pagseguro-keys/public-key.pem'));

        return response()->json([
            'public_key' => $publicKey,
            'created_at' => \Carbon\Carbon::createFromFormat('d/m/Y', '07/04/2026')->timestamp * 1000
        ]);
    }
}