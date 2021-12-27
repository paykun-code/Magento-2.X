<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Paykun\Checkout\Controller\Index;

use Magento\Framework\App\Action\Context;
use Paykun\Checkout\Controller\Errors\ValidationException;
use Paykun\Checkout\Controller\Payment;
use Paykun\Checkout\Controller\Errors\Error;
use Paykun\Checkout\Controller\Errors\ErrorCodes;

class PaykunProcessor extends \Magento\Framework\App\Action\Action {

    const GATEWAY_URL_PROD = "https://checkout.paykun.com/payment";
    const GATEWAY_URL_DEV = "https://sandbox.paykun.com/payment";
    const SUCCESS_URL = "paykun_checkout_gateway/index/success";
    const FAILED_URL = "paykun_checkout_gateway/index/cancelled";
    //const ALLOWED_CURRENCIES = ['INR'];

    protected $resultJsonFactory;
    protected $_scopeConfig;
    protected $_encryptor;
    protected $_checkoutSession;
    protected $_countryFactory;

    protected $_isError;
    protected $_errorMessage;
    protected $_errorCode;
    protected $_orderDetail;
    protected $_url;
    protected $_agreementsValidator;
    protected $_logger;
    protected $_checkoutHelper;
    protected $_messageManager;

    public function __construct(
        Context  $context,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\Checkout\Api\AgreementsValidatorInterface $agreementValidator,
        \Paykun\Checkout\Logger\Logger $logger,
        \Magento\Paypal\Helper\Checkout $checkoutHelper,
        \Magento\Framework\Message\ManagerInterface $messageManager
        ) {

            $this->resultJsonFactory    = $resultJsonFactory;
            $this->_encryptor           = $encryptor;
            $this->_checkoutSession     = $checkoutSession;
            $this->_countryFactory      = $countryFactory;
            $this->_url                 = $context->getUrl();
            $this->_agreementsValidator = $agreementValidator;
            $this->_logger              = $logger;
            $this->_checkoutHelper      = $checkoutHelper;
            $this->_messageManager      = $messageManager;
            parent::__construct($context);
    }

    private function addLog($log, $isError = false) {

        if($this->getConfig('debug')) {
            if(!$isError) {

                $this->_logger->addInfo($log);

            } else {

                $this->_logger->addError($log);

            }
        }
    }

    public function execute() {

        $result = $this->resultJsonFactory->create();
        $post = $this->getRequest()->getPostValue();
        try {

            //TODO : Check if the agreement is accepted by customer or not

            $orderId = $this->getRealOrderId();

            //$orderId = 44;

            if($orderId) {

                $this->setOrderDetail($orderId);
                //if(in_array($this->_orderDetail->getOrderCurrencyCode(), self::ALLOWED_CURRENCIES)) {
                if($this->_orderDetail->getOrderCurrencyCode()) {

                    $formData = $this->getPaykunPaymentDetail($orderId);

                    if($formData !== null && $this->_isError !== true) {
                        $this->addLog('Start Payment for Merchant Id => '.
                            $this->getConfig('merchant_gateway_key').', For the order Id => '.$orderId, $formData);

                        return $result->setData(['success' => true, 'formData' => $formData, 'gatewayUrl' => self::GATEWAY_URL_PROD]);

                    }

                    $this->addLog('Failed => ['.$this->_errorCode.'] => '. $this->_errorMessage, true);
                    return $result->setData(['success' => false, 'message' => $this->_errorMessage, 'code' => $this->_errorCode]);
                } else {

                    $this->addLog('Failed => ['.$this->_errorCode.'] => '. $this->_errorMessage, true);

                    $this->restoreCart(ErrorCodes::CURRIENCY_NOT_ALLOEWD_STRING);
                    return $result->setData(['success' => false, 'message' => ErrorCodes::CURRIENCY_NOT_ALLOEWD_STRING,
                        'code' => ErrorCodes::CURRIENCY_NOT_ALLOEWD_CODE]);
                }

            } else {

                $this->addLog('Failed => ['.ErrorCodes::SESSION_ORDER_ID_NOT_FOUND_STRING.'] => '. $this->_errorMessage, true);
                return $result->setData(['success' => false, 'message' => ErrorCodes::SESSION_ORDER_ID_NOT_FOUND_STRING,
                    'code' => ErrorCodes::SESSION_ORDER_ID_NOT_FOUND_CODE]);

            }

        } catch (\Exception $e) {

            $this->addLog('Failed => ['.$this->_errorCode.'] => '. $this->_errorMessage, true);
            return $result->setData(['success' => false, 'message' => $this->_errorMessage, 'code' => $this->_errorCode]);

        }


    }

    private function restoreCart($errorMsg) {


        $this->_checkoutHelper->cancelCurrentOrder($errorMsg);

        if ($this->_checkoutSession->restoreQuote()) {

            $this->addLog("Cart restored successfully.");
            //Redirect to payment step
            $this->_redirect('checkout', ['_fragment' => 'payment']);

        } else {

            $this->_messageManager->addErrorMessage("PAYKUN Couldn't restored your cart please try again.");
            $this->addLog("PAYKUN Couldn't restored your cart please try again.", true);
            $this->_redirect('checkout', ['_fragment' => 'payment']);

        }

    }

    private function setOrderDetail($orderId) {

        $objectManager      = \Magento\Framework\App\ObjectManager::getInstance();
        $orderData          = $objectManager->create('Magento\Sales\Model\Order')->load($orderId);
        $this->_orderDetail = $orderData;

    }

    private function getPaykunPaymentDetail($orderId) {

        try {
            $orderDetail = $this->getOrderDetail($orderId);

            //$this->debug($orderDetail, true);
            if($orderDetail !== null) {
                $this->addLog('Order Id Testing => '.$orderId, true);

                $liveOrSandboxMode = $this->getConfig('is_live_or_sandbox') ? true : false;
                $obj = new Payment(

                    $this->getConfig('merchant_gateway_key'),
                    $this->getConfig('merchant_access_key', true),
                    $this->getConfig('merchant_encryption_key', true),
                    $liveOrSandboxMode, true
                );

                // Initializing Order
                $obj->initOrder(

                    $orderDetail['orderId'],
                    $orderDetail['purpose'],
                    $orderDetail['amount'],
                    $orderDetail['successUrl'],
                    $orderDetail['failureUrl']

                );
                //$this->debug($orderDetail, true);
                // Add Customer
                $obj->addCustomer(

                    $orderDetail['customerDetail']['name'],
                    $orderDetail['customerDetail']['email'],
                    $orderDetail['customerDetail']['customerMono']

                );
                //$this->debug($orderDetail, true);
                // Add Shipping address

                $obj->addShippingAddress(

                    $orderDetail['shippingDetail']['country'],
                    $orderDetail['shippingDetail']['state'],
                    $orderDetail['shippingDetail']['city'],
                    $orderDetail['shippingDetail']['pinCode'],
                    $orderDetail['shippingDetail']['addressString']

                );

                // Add Billing Address
                $obj->addBillingAddress(

                    $orderDetail['billingDetail']['country'],
                    $orderDetail['billingDetail']['state'],
                    $orderDetail['billingDetail']['city'],
                    $orderDetail['billingDetail']['pinCode'],
                    $orderDetail['billingDetail']['addressString']


                );
                $obj->setCustomFields(['udf_1' => $orderId]);

                //get form data to submit
                return $obj->submit();

            }

            $this->_isError = true;
            $this->_errorMessage = ErrorCodes::SESSION_ORDER_ID_NOT_FOUND_STRING;
            $this->_errorCode = ErrorCodes::SESSION_ORDER_ID_NOT_FOUND_CODE;

            $this->addLog('Failed => ['.$this->_errorCode.'] => '. $this->_errorMessage, true);

            return null;

        } catch (ValidationException $e) {

            //$this->restoreCart($e->getMessage());
            $this->_isError = true;
            $this->_errorMessage = $e->getMessage();
            $this->_errorCode = $e->getCode();
            $this->addLog('Failed => ['.$this->_errorCode.'] => '. $this->_errorMessage, true);
            return null;

        }
    }

    private function getConfig($name, $isDecrypt = null) {

        try {

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $conf = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payment/paykun_gateway/'.$name);
            if($isDecrypt != null) {
                $conf = $this->_encryptor->decrypt($conf);
            }

            return $conf;
        } catch (ValidationException $e) {

            $this->_isError = true;
            $this->_errorMessage = ErrorCodes::INVALID_SYSTEM_CONFIG_STRING;
            $this->_errorCode = ErrorCodes::INVALID_SYSTEM_CONFIG_CODE;
            $this->addLog('Failed => ['.$this->_errorCode.'] => '. $this->_errorMessage, true);
            return null;

        }

    }

    private function getOrderIdForPaykun($orderId) {

        try {

            $orderNumber = str_pad((string)$orderId, 10, '0', STR_PAD_LEFT);
            return $orderNumber;

        } catch (ValidationException $e) {

            $this->_isError = true;
            $this->_errorMessage = ErrorCodes::SESSION_ORDER_ID_NOT_FOUND_STRING;
            $this->_errorCode = ErrorCodes::SESSION_ORDER_ID_NOT_FOUND_CODE;
            $this->addLog('Failed => ['.$this->_errorCode.'] => '. $this->_errorMessage, true);
        }

    }

    private function getRealOrderId() {

        try {

            $order = $this->_checkoutSession->getLastRealOrder();
            return $order->getId();

        } catch (ValidationException $e) {

            $this->_isError = true;
            $this->_errorMessage = ErrorCodes::SESSION_ORDER_ID_NOT_FOUND_STRING;
            $this->_errorCode = ErrorCodes::SESSION_ORDER_ID_NOT_FOUND_CODE;
            $this->addLog('Failed => ['.$this->_errorCode.'] => '. $this->_errorMessage, true);
            return null;
        }

    }

    private function getOrderDetail ($orderId) {
        try {
            $orderData = $this->_orderDetail;
            $paykunOrderDetail = null;
            if(is_object($orderData) && $orderData->getId()!=''){

                $billingData    = (array)$orderData->getBillingAddress()->getData();
                $shippingData   = (array)$orderData->getShippingAddress()->getData();

                /*
                 * In magento Region or provience is not mandatory field so assign city to region when region is not
                    provided
                */
                $shippingDetail = [
                    'country'           => $this->getCountryname($shippingData['country_id']),
                    'state'             => (isset($shippingData['region'])) ? $shippingData['region'] : $billingData['city'],
                    'city'              => $shippingData['city'],
                    'pinCode'           => $shippingData['postcode'],
                    'addressString'     => $shippingData['street'],
                ];

                $billingDetail = [
                    'country'           => $this->getCountryname($billingData['country_id']),
                    'state'             => (isset($billingData['region'])) ? $billingData['region'] : $billingData['city'],
                    'city'              => $billingData['city'],
                    'pinCode'           => $billingData['postcode'],
                    'addressString'     => $billingData['street'],
                ];

                $customerDetail = [
                    'name'          => ($this->_orderDetail->getCustomerIsGuest())
                        ? $shippingData['firstname'].' '.$shippingData['lastname']
                        : $orderData->getCustomerName(),
                    'email'         => $orderData->getCustomerEmail(),
                    'customerMono'  => $shippingData['telephone'],
                ];

                $paykunOrderDetail = [

                    'orderId'           => $this->getOrderIdForPaykun($orderId),
                    'purpose'           => $this->getItemPurpose($orderData->getAllItems()),
                    'amount'            => $orderData->getBaseGrandTotal(),
                    'successUrl'        => $this->_url->getUrl(self::SUCCESS_URL),
                    'failureUrl'        => $this->_url->getUrl(self::FAILED_URL),
                    'customerDetail'    => $customerDetail,
                    'shippingDetail'    => $shippingDetail,
                    'billingDetail'     => $billingDetail,

                ];

            }
            return $paykunOrderDetail;

        } catch (ValidationException $e) {

            $this->_isError = true;
            $this->_errorMessage = ErrorCodes::SESSION_ORDER_ID_NOT_FOUND_STRING;
            $this->_errorCode = ErrorCodes::SESSION_ORDER_ID_NOT_FOUND_CODE;
            $this->addLog('Failed => ['.$this->_errorCode.'] => '. $this->_errorMessage, true);
            return null;
        }

    }

    private function getItemPurpose($orderedItems) {

        $itemPurpose = "";
        $numItems = count($orderedItems);
        $currentCount = 0;

        foreach($orderedItems as $item){

            $extraStuff = ', ';

            if(++$currentCount === $numItems) {
                $extraStuff = '';
            }

            $item_detail = (array) $item->getData();
            $itemPurpose .= $item_detail['name'].$extraStuff;

        }
        return $itemPurpose;
    }

    private function getCountryname($countryCode){

        $country = $this->_countryFactory->create()->loadByCode($countryCode);
        return $country->getName();

    }

    private function debug($data, $isExit = true) {
        echo "<pre>";
        print_r($data);
        if($isExit === true) {
            exit;
        }
    }

}
