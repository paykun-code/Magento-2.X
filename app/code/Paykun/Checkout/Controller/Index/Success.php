<?php
/**
 * Created by PhpStorm.
 * User: PayKun Two
 * Date: 10-10-2018
 * Time: 15:03
 */

namespace Paykun\Checkout\Controller\Index;


class Success extends \Magento\Framework\App\Action\Action
{
    protected $_pageFactory;
    protected $_checkoutSession;
    protected $_quoteManagement;
    protected $TXN_ID;
    protected $_transactionBuilder;
    protected $_logger;
    protected $_checkoutHelper;
    protected $_encryptor;
    protected $_messageManager;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        \Paykun\Checkout\Logger\Logger $logger,
        \Magento\Paypal\Helper\Checkout $checkoutHelper,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Framework\Message\ManagerInterface $messageManager
    )
    {
        $this->_pageFactory         = $pageFactory;
        $this->_checkoutSession     = $checkoutSession;
        $this->_quoteManagement     = $quoteManagement;
        $this->_transactionBuilder  = $transactionBuilder;
        $this->_logger              = $logger;
        $this->_checkoutHelper      = $checkoutHelper;
        $this->_encryptor           = $encryptor;
        $this->_messageManager      = $messageManager;
        return parent::__construct($context);
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

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     * Handle success transaction request
     */
    public function execute() {
        try {


            $this->TXN_ID = $this->getRequest()->getParam('payment-id');

            if(empty(trim($this->TXN_ID))) {

                $this->messageManager->addErrorMessage(__(' ==> . Transaction id is missing, please contact your online shopping store owner.'));

            }

            //$order = $this->_checkoutSession->getLastRealOrder();

            $liveOrSandboxMode = ($this->getConfig('is_live_or_sandbox')) ? true : false;
            $response = $this->_getcurlInfo($this->TXN_ID, $liveOrSandboxMode);


            $order_id = $response['data']['transaction']['custom_field_1'];

            $order = $this->getOrderById($order_id);

            if($response == null) {

                $errorMsg = 'Server communication failed.';

            } else if($response["status"] == false) {

                $errorMsg = $response["errors"]["errorMessage"];

            } else if ($order->getId()) {

                $this->addLog('Set payment captured for the Transaction  => '.$this->TXN_ID. ', For the order Id => '.$order->getId());

                if(isset($response['status']) && $response['status'] == "1" || $response['status'] == 1 ) {

                    $payment_status = $response['data']['transaction']['status'];

                    if($payment_status === "Success") {
                        //if(1) {
                        $resAmout = $response['data']['transaction']['order']['gross_amount'];

                        if(($order->getBaseGrandTotal()	== $resAmout)) {

                            //Change order status
                            $orderState = \Magento\Sales\Model\Order::STATE_PROCESSING;
                            $order->setState($orderState)->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING)->save();
                            $this->setTransactionId($order);

                        } else {

                            //fraud activity happen
                            $errorMesg = 'Paykun detected some fraud activity with the transaction id = '.$this->TXN_ID. ', For the order Id => '. $order->getId();
                            $this->addLog($errorMesg);
                            $this->restoreCart($errorMesg);
                        }


                    } else {

                        $errorMesg = 'Payment is not done. The server responded with payment not done status, if payment is done then please contact
                        your merchant. your transaction id is =>'.$this->TXN_ID. ', For the order Id => '. $order->getId();

                        $this->addLog($errorMesg);
                        $this->restoreCart($errorMesg);

                    }
                } else {

                    //Payment response failed
                    $errorMesg = 'Something went wrong. Please contact site owner. your transaction id is =>'.$this->TXN_ID. ', For the order Id => '. $order->getId();
                    $this->addLog($errorMesg);
                    $this->restoreCart($errorMesg);

                }

            } else {

                $liveOrSandboxMode = $this->getConfig('is_live_or_sandbox') ? true : false;
                if($liveOrSandboxMode == false) {
                    $this->messageManager->addErrorMessage('Order successfully placed using PayKun Sandbox environment.');
                    $this->addLog('Order successfully placed using PayKun Sandbox environment.');

                } else {
                    $this->messageManager->addErrorMessage('An error occurred on the server, please try to place the order again.');
                    $this->addLog('Order Not found due to session expired for the transaction Id=>'.$this->TXN_ID);
                }


            }

            $this->addLog('Order success for the transaction Id=>'.$this->TXN_ID. ', For the order Id => '. $order->getId());
            $this->_redirect('checkout/onepage/success');

        } catch (\Magento\Framework\Exception\PaymentException $ex) {

            $this->messageManager->addErrorMessage($ex->getMessage(). __(' ==> . Something went wrong please contact your online shopping store owner.'));
            $this->addLog('Something went wrong for the transaction id =>'.$this->TXN_ID);
        }

    }

    protected function getOrderById($order_id) {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $order = $objectManager->create('\Magento\Sales\Model\Order')
            ->load($order_id);

        return $order;

    }

    private function restoreCart($errorMsg) {

        $this->_checkoutHelper->cancelCurrentOrder($errorMsg);

        if ($this->_checkoutSession->restoreQuote()) {
            $this->addLog("Cart restored successfully.");
            //Redirect to payment step
            $this->_redirect('checkout', ['_fragment' => 'payment']);

        } else {

            $this->_messageManager->addErrorMessage("PAYKUN Couldn't restored your cart please try again.");
            $this->addLog("PAYKUN Couldn't restored your cart please try again.");
            $this->_redirect('checkout', ['_fragment' => 'payment']);

        }

    }

    /**
     * @param $orderObject
     * Set the transaction id received from the paykun to the order
     */
    private function setTransactionId($orderObject) {

        $payment = $orderObject->getPayment($this->TXN_ID);

        $payment->setLastTransId(htmlentities($this->TXN_ID));
        $payment->setTransactionId($this->TXN_ID);
        $payment->setIsTransactionClosed(true);
        //setAdditionalInformation
        $payment->setTransactionAdditionalInfo( "Paykun Txn Id => ", htmlentities($this->TXN_ID) );

        $formatedPrice = $orderObject->getBaseCurrency()->formatTxt(
            $orderObject->getBaseGrandTotal()
        );

        $message = __('The Captured amount is %1.', $formatedPrice);

        $transaction = $this->addTransaction($orderObject, $payment);

        $payment->addTransactionCommentsToOrder( $transaction, $message);
        $payment->setParentTransactionId(null);

        $payment->save();
        $orderObject->save();

        $this->addLog('Transaction added successfully for the transaction id => '.$this->TXN_ID. ', 
        For the order Id => '.$orderObject->getId());
    }

    /**
     * @param $order
     * @param $payment
     * @return \Magento\Sales\Api\Data\TransactionInterface
     * This function will add transaction associated with the order from session
     * you can see this transaction by Admin => Sales => Orders => click on the order you want to check => And click on the transaction tab
     * If you see transaction type capture then payment is done for this order
     */
    private function addTransaction($order, $payment) {

        $trans = $this->_transactionBuilder;
        $transaction = $trans->setPayment($payment)
            ->setOrder($order)
            ->setTransactionId($this->TXN_ID)
            ->setAdditionalInformation(
                [
                    \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $payment->getAdditionalInformation(),
                    'Transaction_Id' => $this->TXN_ID,
                    'Amount'    => $order->getBaseGrandTotal(),
                    'Status'    => 'Paid',
                    'OrderId'  => $order->getId()
                ]
            )
            ->setFailSafe(true)
            //build method creates the transaction and returns the object
            ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);

        $transaction->save();
        return $transaction;
    }

    private function _getcurlInfo($iTransactionId, $mode)
    {

        try {

            if(!$iTransactionId) return null;
            $cUrl = 'https://api.paykun.com/v1/merchant/transaction/' . $iTransactionId . '/';
            if($mode == false) {
                $cUrl = 'https://sandbox.paykun.com/api/v1/merchant/transaction/' . $iTransactionId . '/';;
            }

            $merchantId = $this->getConfig("merchant_gateway_key");
            $accessToken = $this->getConfig("merchant_access_key", true);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $cUrl);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("MerchantId:$merchantId", "AccessToken:$accessToken"));

            $response = curl_exec($ch);
            $error_number = curl_errno($ch);
            $error_message = curl_error($ch);

            $res = json_decode($response, true);
            curl_close($ch);

            return ($error_message) ? null : $res;

        } catch (\Exception $e) {
            $this->addLog($e->getMessage());
            return null;
            //throw $e;

        }
    }

    private function getConfig($name, $isDecrypt = null) {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $conf = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payment/paykun_gateway/'.$name);
        if($isDecrypt != null) {
            $conf = $this->_encryptor->decrypt($conf);
        }

        return $conf;

    }
}
function debug($data, $isExit = true) {
    echo "<pre>";
    print_r($data);
    if($isExit === true) {
        exit;
    }
}