<?php

namespace App\Http\Services;

use App\Custom\PagSeguro\Builders\AddressBuilder;
use App\Custom\PagSeguro\Builders\CardBuilder;
use App\Custom\PagSeguro\Builders\ChargeBuilder;
use App\Custom\PagSeguro\Builders\CustomerBuilder;
use App\Custom\PagSeguro\Builders\OrderBuilder;
use App\Custom\PagSeguro\Builders\QRCodesBuilder;
use App\Custom\PagSeguro\Builders\ReferenceBuilder;
use App\Custom\PagSeguro\Services\PagSeguroCharge;
use App\Custom\PagSeguro\Services\PagSeguroOrder;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderProductIngredient;
use App\Models\OrderShippingData;
use App\Models\Product;
use App\Models\Repositories\OrderRepository;
use App\Models\Repositories\ProductRepository;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OrderService {

    /**
     * Realiza o retorno da listagem formatada para menu
     * 
     * @param array $filter
     * @param string $image_path
     * 
     * @return array
     */
    public function show(array $filter, string $image_path) : array
    {
        $id = $filter['id'] ?? 0;
        $token = $filter['token'] ?? "";
        $products = [];

        $order = Order::where([
            'id' => $id,
            'token' => $token
        ])
        ->with('orderProducts','orderProducts.orderProductIngredients','shippingData')->first();

        if(!$order) return [
            'success' => false,
            'message' => 'Produto não encontrado',
            'status' => 404
        ];

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

            return [
                'success' => true,
                'status'  => $order->status,
                'paid' => $order->payment_status == 'paid',
                'available' => false,
                'message' => $message
            ];
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
            $value->image = isset($original_product->image) ? asset('storage/' . $image_path . "/" . $original_product->image) : null;

            $order_products[] = $value;
        }

        $order->setRelation('orderProducts', collect($order_products));

        return [
            'success' => true,
            'order' => $order
        ];
    }

    /**
     * Realiza a criação do pedido dentro da transaction para evitar sujeira no banco de dados
     * em caso de erro
     * 
     * @param array $filter
     * @param \App\Models\Repositories\ProductRepository $productRepository
     * 
     * @return mixed
     */
    public function create(array $filter, ProductRepository $productRepository) : mixed {
        return DB::transaction(function () use ($filter, $productRepository) {
            $cart = $filter;
            $product_ids = array_map(fn($e) => $e['productId'] ?? 0 , $cart);

            $originalProducts = $productRepository->getActivedProducts($product_ids)->keyBy(fn($item) => 'id-' . $item['id']);

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
    }

    /**
     * Cria os dados de entrega/cliente
     * 
     * @param array $filter
     * @param string|int $id
     * 
     * @return array
     */
    public function setShippingData(array $filter, string|int $id) : array
    {
        $order = Order::find($id);

        if(!$order)
            return [
                'success' => false,
                'status' => 404,
                'message' => 'A ordem não foi encontrada'
            ];

        $shipping = OrderShippingData::create([
            "name" => $filter['name'] ?? "",   
            "document" => $filter['document'] ?? "",
            "email" => $filter['email'] ?? "",
            "phone" => $filter['phone'] ?? "",
            "cep" => $filter['cep'] ?? "",
            "street" => $filter['street'] ?? "",
            "neighborhood" => $filter['neighborhood'] ?? "",
            "complement" => $filter['complement'] ?? "",
            "number" => $filter['number'] ?? "",
            "uf" => $filter['uf'] ?? "",
            "city" => $filter['city'] ?? "",
            "reference" => $filter['reference'] ?? "",
            "is_delivery" => $filter['shipping'] == "delivery",
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

                return [
                    'success' => true,
                    'id' => $order->id,
                    'token' => $order->token,
                    'shipping' => collect($shipping)->only([
                        'name', 'document', 'email', 'phone', 'cep', 'street', 'neighborhood', 
                        'complement', 'number', 'uf', 'city', 'reference', 'is_delivery'
                    ])->toArray()
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Ocorreu um erro durante a criação do pedido via PagBank"
                ];
            }
        }

        return [
            'success' => false
        ];
    }

    /**
     * Realiza o pagamento
     * 
     * @param array $filter
     * @param string|int $id
     * 
     * @return array
     * 
     */
    public function setPayment(array $filter, string|int $id) : array
    {
        $order = Order::find($id);
    
        if(!$order)
            return [
                'success' => false,
                'status' => 404,
                'message' => 'A ordem não foi encontrada'
            ];

        if($order->payment_status != 'pending') {
            if($order->payment_status == 'paid')
                return [
                    'success' => true,
                    'paid' => true
                ];
        }

        $ref = (new ReferenceBuilder())->build($order->token);
        $ps_order = (new OrderBuilder())
            ->setReference($ref);

        $ps_order = $ps_order->build();

        $card = (new CardBuilder())->build([
            'encrypted' => $filter['encryptedCard'],
            'store' => false
        ]);

        $charge = (new ChargeBuilder())
            ->setAmount($order->total)
            ->setPaymentMethod([
                'capture' => true,
                'installments' => $filter['installments'] ?? 1,
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

        return [
            'success' => true,
            'paid' => $paid,
            'available' => !$paid,
            'message' => $paid ? 'Seu pedido foi pago com sucesso e em breve iniciaremos a produção.' : "Ocorreu um problema durante o pagamento.",
            'status' => $order->status
        ];
    }

    /**
     * Gera uma nova ordem para o cliente
     * 
     * @param \App\Models\Order $order
     * @param \App\Models\OrderShippingData $shipping
     */
    private function generateOrder(Order $order, OrderShippingData $shipping) {
        $expiration_date = (new \DateTime())->modify('+10 minutes')->format('c');
        
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

        $ps_order = (new OrderBuilder())
            ->setReference($ref)
            ->setCustomer($customer);

        if($shipping->is_delivery) {
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

            $ps_order = $ps_order->setShippingData($shipping);
        }

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
     * Realiza a recuperação de um pedido que expirou há pouco tempo
     * 
     * @param array $filter
     * 
     * @return array
     */
    public function recreateOrder(array $filter) : array
    {
        $id = $filter['id'] ?? 0;
        $token = $filter['token'] ?? "";
        $now = new \DateTime();

        $old_order = Order::where([
            'id' => $id,
            'token' => $token
        ])
        ->with('orderProducts','orderProducts.orderProductIngredients','shippingData')->first();

        if(!$old_order)
            return [
                'success' => false,
                'status' => 404,
                'message' => "Ordem não encontrada"
            ];

        if($old_order->payment_status == 'paid')
            return [
                'success' => false,
                'message' => "A ordem já se encontra paga"
            ];

        if($old_order->status == 'cancelled')
            return [
                'success' => false,
                'message' => "A ordem já está cancelada"
            ];

        $expiration_date = new \DateTime($old_order->expiration_date);
        $seconds = $now->getTimestamp() - $expiration_date->getTimestamp();
        $minutes = $seconds / 60;

        if($minutes >= 15) {
            $old_order->update([
                'status' => 'cancelled'
            ]);

            return [
                'success' => false,
                'message' => 'A ordem não pode mais ser recriada.'
            ];
        }

        do {
            $new_token = 'ORD-' . now()->format('ymdHis') . '-' . Str::upper(Str::random(6));
        } while (Order::where('token', $new_token)->exists());

        $new_order = DB::transaction(function () use ($old_order, &$new_order, $new_token) {
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

        return [
            'success' => true,
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
        ];
    }

    /**
     * Realiza a captura das parcelas baseado nos primeiros dígitos do cartão
     * 
     * @param array $filter
     * 
     * @return array
     */
    public function toCheckFees(array $filter) : array
    {
        $total = str_replace([',','.'],"", Order::where('id',$filter['id'] ?? 0)->value('total') ?? 0);

        $data = [
            'payment_methods' => 'CREDIT_CARD',
            'value' => $total,
            'max_installments' => 4,
            'max_installments_no_interest' => 3,
            'credit_card_bin' => $filter['bin'] ?? '',
        ];

        $response = (new PagSeguroCharge())->checkFees($data);

        if(empty($response['payment_methods']['credit_card'] ?? [])) {
            return [
                'success' => false,
                'message' => "Ocorreu um erro na busca dos dados de parcelamento"
            ];
        }        

        $card = reset($response['payment_methods']['credit_card']);

        if(empty($card) || empty($card['installment_plans'])) {
            return [
                'success' => false,
                'message' => "Ocorreu um erro na busca dos dados de parcelamento"
            ];
        }
            
        $plans = $card['installment_plans'] ?? [];
            
        if(empty($plans) || !is_array($plans)) {
            return [
                'success' => false,
                'message' => "Ocorreu um erro na busca dos dados de parcelamento"
            ];
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
        
        return [
            'success' => true,
            'installments' => $installments
        ];
    }
}