<?php
/**
 * @package waPlugins
 * @subpackage Payment
 * @name Mixplat
 * @description Mixplat payment module
 * @payment-type online
 *
 * @property-read $api_key
 * @property-read $project_id
 * @property-read $form_id
 * @property-read $test
 * @property-read $send_receipt
 * @property-read $payment_method
 * @property-read $payment_object
 * @property-read $payment_object_delivery
 * @property-read $payment_scheme
 * @property-read $allow_all
 * @property-read $mixplat_ip_list
 *
 */
waAutoload::getInstance()->add('MixplatLib', 'wa-plugins/payment/mixplat/lib/mixplat/lib.php');
class mixplatPayment extends waPayment implements waIPayment, waIPaymentRefund, waIPaymentCapture, waIPaymentCancel
{
    private $order_id;


    public function allowedCurrency()
    {
        return array('RUB');
    }

    public function supportedOperations()
    {
        return array(
            self::OPERATION_AUTH_CAPTURE,
            self::OPERATION_AUTH_ONLY,
            self::OPERATION_CAPTURE,
            self::OPERATION_REFUND,
            self::OPERATION_CANCEL,
        );
    }

    public function payment($payment_form_data, $order_data, $auto_submit = false)
    {
        $order = waOrder::factory($order_data);
        $allowed_currency = $this->allowedCurrency();
        if (!in_array($order->currency, $allowed_currency)) {
            throw new waException(sprintf('Unsupported currency %s', $order->currency));
        }

        $amount = intval($order->total * 100);

        $c = new waContact($order_data['customer_contact_id']);

        $email = $c->get('email', 'default');

        $notifyUrl = $this->getRelayUrl().'?transaction_result=result&app_id='.$this->app_id.'&merchant_id='.$this->merchant_id.'&order_id='.$order_data['order_id'];
        $successUrl = $this->getRelayUrl() . '?transaction_result=success&order_id='.$order_data['order_id'];
        $failUrl = $this->getRelayUrl() . '?transaction_result=failure';

        $data = array(
            'amount'              => $amount,
            'test'                => $this->test,
            'project_id'          => $this->project_id,
            'payment_form_id'     => $this->form_id,
            'request_id'          => MixplatLib::getIdempotenceKey(),
            'merchant_payment_id' => $order_data['order_id'],
            'user_email'          => $email,
            'url_success'         => $successUrl,
            'url_failure'         => $failUrl,
            'notify_url'          => $notifyUrl,
            'payment_scheme'      => $this->payment_scheme,
            'description'         => $order->description,
        );
        if ($this->send_receipt) {
            $data['items'] = $this->getReceiptItems($order);
        }

        $data['signature'] = MixplatLib::calcPaymentSignature($data, $this->api_key);
        self::log($this->id, $data);
        try {
            $result = MixplatLib::createPayment($data);
        } catch (Exception $e) {
            return null;
        }

        $view = wa()->getView();
        $form_url = $result->redirect_url;
        $view->assign(compact( 'form_url', 'auto_submit'));

        return $view->fetch($this->path.'/templates/payment.html');
    }

    private function getReceiptItems(waOrder $order)
    {
        $items = [];
        foreach ($order->items as $item) {
            $item['amount'] = $item['price'] - ifset($item['discount'], 0.0);
            $vatId = $this->getTaxId($item);
            $items[] = array(
                "name"     => $item['name'],
                "quantity" => $item['quantity'],
                "sum"      => round($item['amount'] * $item['quantity'] * 100),
                "vat"      => $vatId,
                "method"   => $this->payment_method,
                "object"   => $this->payment_object,
            );
        }

        #shipping
        if (strlen($order->shipping_name) || $order->shipping) {

            $item = array(
                'tax_rate' => $order->shipping_tax_rate,
            );
            if ($order->shipping_tax_included !== null) {
                $item['tax_included'] = $order->shipping_tax_included;
            }
            $vatId = $this->getTaxId($item);
            $items[] = array(
                "name"     => $order->shipping_name,
                "quantity" => 1,
                "sum"      => round($order->shipping * 100),
                "vat"      => $vatId,
                "method"   => $this->payment_method,
                "object"   => $this->payment_object_delivery,
            );
        }

        $amount = intval($order->total * 100);
        $items = MixplatLib::normalizeReceiptItems($items, $amount);
        return $items;

    }

    public function refund($transaction_raw_data)
    {
        $query  = array(
            'payment_id' => $transaction_raw_data['transaction']['native_id'],
            'amount'     => intval($transaction_raw_data['transaction']['amount'] * 100),
        );
        $query['signature'] = MixplatLib::calcActionSignature($query, $this->api_key);
        self::log($this->id, $query);
        try {
            $return = MixplatLib::refundPayment($query);
            $result = ['result' => 0, 'description' => 'Id возврата'. $result->refund_id];
        } catch (Exception $e){
            $result = ['result' => 1, 'description' => $e->getMessage()];
        }
        self::log($this->id, $result);
        return $result;
    }

    public function capture($transaction_raw_data)
    {
        $query  = array(
            'payment_id' => $transaction_raw_data['transaction']['native_id'],
            'amount'     => intval($transaction_raw_data['transaction']['amount'] * 100),
        );
        $query['signature'] = MixplatLib::calcActionSignature($query, $this->api_key);
        self::log($this->id, $query);
        try {
            $return = MixplatLib::confirmPayment($query);
            $result = ['result' => 0, 'description' => ''];
        } catch (Exception $e){
            $result = ['result' => 1, 'description' => $e->getMessage()];
        }
        self::log($this->id, $result);
        return $result;
    }

    public function cancel($transaction_raw_data)
    {
        $query  = array(
            'payment_id' => $transaction_raw_data['transaction']['native_id'],
        );
        $query['signature'] = MixplatLib::calcActionSignature($query, $this->api_key);
        self::log($this->id, $query);
        try {
            $return = MixplatLib::cancelPayment($query);
            $result = ['result' => 0, 'description' => ''];
        } catch (Exception $e){
            $result = ['result' => 1, 'description' => $e->getMessage()];
        }
        self::log($this->id, $result);
        return $result;
    }


    protected function callbackInit($request)
    {
        $this->app_id = $request['app_id'];
        $this->order_id = $request['order_id'];
        $this->merchant_id = $request['merchant_id'];
        return parent::callbackInit($request);
    }

    /**
     *
     * @param $data - get from gateway
     * @return array
     */
    protected function callbackHandler($request)
    {
        $content = file_get_contents("php://input");
        $data = json_decode($content, true);
        self::log($this->id, $data);
        $request = array_merge($request, $data);
        $transaction_data = $this->formalizeData($request);
        $transaction_result = ifempty($request['transaction_result'], 'success');
        $url = null;
        $app_payment_method = null;
        switch ($transaction_result) {
            case 'result':
                if (!$this->isValidRequest($pmconfigs)) {
                    throw new waException('unknown source');
                }

                $sign = MixplatLib::calcActionSignature($data, $this->api_key);
                if (strcmp($sign, $data['signature']) !== 0) {
                    throw new waException('sign error');
                }
                if($request['status'] === 'success'){
                    $app_payment_method = self::CALLBACK_PAYMENT;
                    $transaction_data['state'] = self::STATE_CAPTURED;
                    $transaction_data['type'] = $this->payment_scheme == 'sms'?self::OPERATION_AUTH_CAPTURE:self::OPERATION_CAPTURE;
                }
                if($request['status_extended'] === 'pending_authorized'){
                    $app_payment_method = self::CALLBACK_PAYMENT;
                    $transaction_data['state'] = self::STATE_AUTH;
                    $transaction_data['type'] = self::OPERATION_AUTH_ONLY;
                }
                break;
            case 'success':
                $url = $this->getAdapter()->getBackUrl(waAppPayment::URL_SUCCESS, $transaction_data);
                break;
            case 'failure':
                if ($this->order_id && $this->app_id) {
                    $app_payment_method = self::CALLBACK_CANCEL;
                    $transaction_data['state'] = self::STATE_CANCELED;
                }
                $url = $this->getAdapter()->getBackUrl(waAppPayment::URL_FAIL, $transaction_data);
                break;
            default:
                $url = $this->getAdapter()->getBackUrl(waAppPayment::URL_FAIL, $transaction_data);
                break;
        }
        if ($app_payment_method) {
            $transaction_data = $this->saveTransaction($transaction_data, $request);
            $this->execAppCallback($app_payment_method, $transaction_data);
        }
        if ($transaction_result == 'result') {
            echo json_encode(['result' => 'ok']);
            return array(
                'template' => false,
            );
        } else {
            return array(
                'redirect' => $url,
            );
        }
    }


    /**
     * @param array|checkBillResponse $result
     * @return array
     */
    protected function formalizeData($result)
    {
        $transaction_data = parent::formalizeData(null);
        $transaction_data['native_id'] = $result['payment_id'];
        $transaction_data['order_id'] = $result['merchant_payment_id'];
        $transaction_data['amount'] = number_format($result['amount'] / 100, 2, '.' ,'' );
        $view_data = [];
        if (!empty($result['card'])) {
            foreach($result['card'] as $key => $value) {
                $view_data[] = "$key: $value";
            }
        }
        if (!empty($result['mobile'])) {
            foreach($result['mobile'] as $key => $value) {
                $view_data[] = "$key: $value";
            }
        }

        if (!empty($result['wallet'])) {
            foreach($result['wallet'] as $key => $value) {
                $view_data[] = "$key: $value";
            }
        }
        $transaction_data['view_data'] = implode("\n", $view_data);

        return $transaction_data;
    }




    private function getTaxId($item)
    {
        if (!isset($item['tax_rate'])) {
            $tax = 'none'; //без НДС;
        } else {
            $tax_included = (!isset($item['tax_included']) || !empty($item['tax_included']));
            $rate = ifset($item['tax_rate']);
            if (in_array($rate, array(null, false, ''), true)) {
                $rate = 'none';
            }

            if (!$tax_included && $rate > 0) {
                throw new waPaymentException('Фискализация товаров с налогом не включенном в стоимость не поддерживается. Обратитесь к администратору магазина');
            }

            switch ($rate) {
                case 0:
                    $tax = 'vat0';//НДС по ставке 0%;
                    break;
                case 5:
                    if ($tax_included) {
                        $tax = 'vat5'; // НДС чека по ставке 5%;
                    } else {
                        $tax = 'vat105'; // НДС чека по расчетной ставке 5/105;
                    }
                    break;
                case 7:
                    if ($tax_included) {
                        $tax = 'vat7'; // НДС чека по ставке 7%;
                    } else {
                        $tax = 'vat107'; // НДС чека по расчетной ставке 7/107;
                    }
                    break;
                case 10:
                    if ($tax_included) {
                        $tax = 'vat10';//НДС чека по ставке 10%;
                    } else {
                        $tax = 'vat110';// НДС чека по расчетной ставке 10/110;
                    }
                    break;
                case 18:
                case 20:
                    if ($tax_included) {
                        $tax = 'vat20';//НДС чека по ставке 18%;
                    } else {
                        $tax = 'vat120';// НДС чека по расчетной ставке 18/118.
                    }
                    break;
                default:
                    $tax = 'none';//без НДС;
                    break;
            }
        }
        return $tax;
    }

    private function isValidRequest()
    {
        if (!$this->allow_all) {
            $mixplatIpList = explode("\n", $this->mixplat_ip_list);
            $mixplatIpList = array_map(function ($item) {return trim($item);}, $mixplatIpList);
            $ip = MixplatLib::getClientIp();
            if (!in_array($ip, $mixplatIpList)) {
                return false;
            }
        }
        return true;
    }
}
