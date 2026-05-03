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
use App\Http\Services\OrderService;
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
     * @param \App\Http\Services\OrderService $orderService
     * 
     * @return mixed
     */
    public function show(Request $request, OrderService $orderService){
        try {
            $data = $orderService->show($request->except('_-token'), $this->image_path);

            if(isset($data['status']) && $data['status'] == 404) return $this->notFound($data['message'] ?? '');

            if(isset($data['success']) && $data['success']) {
                return $this->success($data);
            }

            return $this->error($data['message'] ?? 'Ocorreu um erro durante a busca do pedido');
        }catch(\Exception $e) {
            return $this->error("Ocorreu um erro na busca do pedido: " . $e->getMessage());
        }
    }

    /**
     * Realiza a criação de um novo pedido
     * 
     * @param \Illuminate\Http\Request $request
     * @param \App\Http\Services\OrderService $orderService
     * 
     * @return mixed
     */
    public function create(Request $request, OrderService $orderService) {
        try{
            $order = $orderService->create($request->except('_token'), $this->product);

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
     * @param string|int $id
     * @param \App\Http\Services\OrderService $orderService
     * 
     * @return mixed
     */
    public function setShippingData(ShippingDataRequest $request, string|int $id, OrderService $orderService) {
        try{
            $data = $orderService->setShippingData($request->except('_token'), $id);

            if(!empty($data['status']) && $data['status'] == 404) return $this->notFound($data['message'] ?? '');

            if(!empty($data['success']) && $data['success']) {
                return $this->success($data);
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
     * @param string|int $id
     * @param \App\Http\Services\OrderService $orderService
     * 
     * @return mixed
     */
    public function setPayment(PaymentRequest $request, string|int $id, OrderService $orderService) {
        try{    
            $data = $orderService->setPayment($request->except('_token'), $id);

            if(!empty($data['status']) && $data['status'] == 404) return $this->notFound($data['message'] ?? '');

            if(!empty($data['success']) && $data['success']) {
                return $this->success($data);
            }

            return $this->error("Ocorreu um erro durante a criação do pagamento");
        }catch(\Exception $e) {
            return $this->error("Ocorreu um erro no armazenamento dos dados de entrega. " . $e->getMessage());
        }
    }

    /**
     * Realiza a recriação da ordem expirada
     * 
     * @param \Illuminate\Http\Request $request
     * @param \App\Http\Services\OrderService $orderService
     * 
     * @return mixed
     * 
     */
    public function recreateOrder(Request $request, OrderService $orderService) {
        try {
            $data = $orderService->recreateOrder($request->except('_token'));

            if(!empty($data['status']) && $data['status'] == 404) return $this->notFound($data['message'] ?? '');

            if(!empty($data['success']) && $data['success']) {
                return $this->success($data);
            }

            return $this->error("Ocorreu um erro durante recriação do pedido");
        } catch (\Exception $e) {
            return $this->error("Ocorreu um erro durante a recuperação do pedido");
        }
    }

    /**
     * Realiza a checagem das parcelas direto no PagSeguro
     * 
     * @param \Illuminate\Http\Request $request
     * @param \App\Http\Services\OrderService $orderService
     * 
     * @return mixed
     */
    public function toCheckFees(Request $request, OrderService $orderService) {
        try{
            $data = $orderService->toCheckFees($request->except('_token'));

            if(!empty($data['status']) && $data['status'] == 404) return $this->notFound($data['message'] ?? '');

            if(!empty($data['success']) && $data['success']) {
                return $this->success($data);
            }

            return $this->success([
                'installments' => [],
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