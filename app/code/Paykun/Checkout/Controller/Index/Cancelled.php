<?php
/**
 * Created by PhpStorm.
 * User: PayKun Two
 * Date: 10-10-2018
 * Time: 15:49
 */

namespace Paykun\Checkout\Controller\Index;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResponseInterface;
use Magento\Sales\Api\PaymentFailuresInterface;
use Paykun\Checkout\Controller\Errors\ValidationException;

class Cancelled extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var \Magento\Paypal\Model\PayflowlinkFactory
     */
    protected $_payflowModelFactory;

    /**
     * @var \Magento\Paypal\Helper\Checkout
     */
    protected $_checkoutHelper;

    /**
     * @var PaymentFailuresInterface
     */
    private $paymentFailures;

    protected $_messageManager;

    protected $TXN_ID;
    protected $_encryptor;
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Paypal\Model\PayflowlinkFactory $payflowModelFactory,
        \Magento\Paypal\Helper\Checkout $checkoutHelper,
        PaymentFailuresInterface $paymentFailures = null,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Paykun\Checkout\Logger\Logger $logger,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor
    )
    {
        $this->_checkoutSession     = $checkoutSession;
        $this->_orderFactory        = $orderFactory;
        $this->_payflowModelFactory = $payflowModelFactory;
        $this->_checkoutHelper      = $checkoutHelper;
        $this->paymentFailures      = $paymentFailures ? : ObjectManager::getInstance()->get(PaymentFailuresInterface::class);
        $this->_messageManager      = $messageManager;
        $this->_quoteManagement     = $quoteManagement;
        $this->_transactionBuilder  = $transactionBuilder;
        $this->_logger              = $logger;
        $this->_encryptor           = $encryptor;
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

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        $errorMsg = null;
        try {

            $liveOrSandboxMode = $this->getConfig('is_live_or_sandbox') ? true : false;
            if(!$liveOrSandboxMode) {

                $this->messageManager->addWarningMessage('Order cancelled! (PayKun Sandbox mode).');
                $this->addLog('Order cancelled! (PayKun Sandbox mode).');

            } else {

                $this->setTransactionId();
                $errorMsg = trim(strip_tags('Payment cancelled. '.'**Paykun Transaction Id => '. $this->TXN_ID." **"));

                $response = $this->_getcurlInfo($this->TXN_ID);
                if($response == null) {

                    $errorMsg = 'Server communication failed.';

                } else if($response["status"] == false) {

                    $errorMsg = $response["errors"]["errorMessage"];

                } else {

                    $order_id = $response['data']['transaction']['custom_field_1'];
                    $order = $this->getOrderById($order_id);

                    //$order = $this->_checkoutSession->getLastRealOrder();

                    if ($order->getId()) {

                        $this->addLog($errorMsg. ', For the order Id => '.$order->getId());
                        $this->paymentFailures->handle($order->getQuoteId(), $errorMsg);

                    } else {
                        $this->addLog('Order not found for the transaction id => '.$this->TXN_ID);
                    }
                    $this->setTransactionIdToOrder();

                }
            }

            $this->restoreCart($errorMsg);

        } catch (\Exception $ex) {

            $this->setTransactionIdToOrder();
            $this->restoreCart($errorMsg);
            $this->_messageManager->addErrorMessage($ex->getMessage());
            $this->addLog($ex.' => '.$this->TXN_ID);
            $this->_redirect('checkout', ['_fragment' => 'payment']);

        }

    }

    protected function getOrderById($order_id) {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $order = $objectManager->create('\Magento\Sales\Model\Order')
            ->load($order_id);

        return $order;

    }

    private function setTransactionId() {

        $this->TXN_ID = $this->getRequest()->getParam('payment-id');

        if(empty(trim($this->TXN_ID))) {

            $this->messageManager->addErrorMessage('Transaction Id is missing from PAYKUN');
            $this->addLog("Transaction Id is missing from PAYKUN");

        }

    }

    private function restoreCart($errorMsg) {

        $this->_checkoutHelper->cancelCurrentOrder($errorMsg);

        if ($this->_checkoutSession->restoreQuote()) {

            $this->addLog("Cart restored successfully.");
            //Redirect to payment step
            $this->_redirect('checkout', ['_fragment' => 'payment']);
            //$order->registerCancellation($comment)->save();
        } else {

            $this->_messageManager->addErrorMessage("PAYKUN Couldn't restored your cart please try again.");
            $this->addLog("PAYKUN Couldn't restored your cart please try again.");
            $this->_redirect('checkout', ['_fragment' => 'payment']);

        }

    }

    private function setTransactionIdToOrder() {
        try {

            $quote = $this->_checkoutSession->getQuote();
            if($quote) {

                $order = $this->_quoteManagement->submit($quote);

                if ($order) {

                    $payment = $order->getPayment($this->TXN_ID);
                    $payment->setTransactionId(htmlentities($this->TXN_ID));
                    $payment
                        ->setTransactionAdditionalInfo(
                            "Paykun Cancelled Txn Id => ",
                            htmlentities($this->TXN_ID)
                        )->setIsTransactionClosed(1);

                    $this->addTransaction($order, $payment, $this->TXN_ID);
                    $this->addLog("Paykun Cancelled Txn Id => ".$this->TXN_ID);
                } else {

                    $this->addLog("Session has been expired.");
                    $this->messageManager->addErrorMessage('Your session has been expired.');

                }

            } else {

                $this->messageManager->addErrorMessage('Your session has been expired.');
                $this->addLog("Session has been expired.");

            }

        } catch (\Magento\Framework\Exception\PaymentException $ex) {

            $this->messageManager->addErrorMessage($ex->getMessage(). __(' ==> . Something went wrong please contact your online shopping store owner.'));
            $this->addLog("Something went wrong.");

        }
    }

    /**
     * @param $order
     * @param $payment
     * @return \Magento\Sales\Api\Data\TransactionInterface
     * This function will add transaction associated with the order from session
     * you can see this transaction by Admin => Sales => Orders => click on the order you want to check => And click on the transaction tab
     * If you see transaction type capture then payment is done for this order
     */
    private function addTransaction($order, $payment, $transactionId) {

        $trans = $this->_transactionBuilder;
        $transaction = $trans->setPayment($payment)
            ->setOrder($order)
            ->setTransactionId($this->TXN_ID)
            ->setAdditionalInformation(
                [
                    \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $payment->getAdditionalInformation(),
                    'PaykunId TXN Id'   => $transactionId,
                    'Context'           => 'Token payment',
                    'Amount'            => $order->getBaseGrandTotal(),
                    'Status'            => "Failed",
                ]
            )
            ->setFailSafe(true)
            //build method creates the transaction and returns the object
            ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);
        $transaction->save();
        $this->addLog("Transaction added successfully with transaction Id => $this->TXN_ID ");
        return $transaction;
    }

    private function getConfig($name, $isDecrypt = null) {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $conf = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payment/paykun_gateway/'.$name);
        if($isDecrypt != null) {
            $conf = $this->_encryptor->decrypt($conf);
        }

        return $conf;

    }

    private function _getcurlInfo($iTransactionId) {

        try {

            if(!$iTransactionId) return null;

            $cUrl        = 'https://api.paykun.com/v1/merchant/transaction/' . $iTransactionId . '/';
            $merchantId  = $this->getConfig("merchant_gateway_key");
            $accessToken = $this->getConfig("merchant_access_key", true);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $cUrl);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("MerchantId:$merchantId", "AccessToken:$accessToken"));

            $response       = curl_exec($ch);
            $error_number   = curl_errno($ch);
            $error_message  = curl_error($ch);

            $res = json_decode($response, true);
            curl_close($ch);

            return ($error_message) ? null : $res;

        } catch (\Exception $e) {

            $this->addLog($e->getMessage());
            return null;
            //throw $e;

        }

    }
}

function debug($data, $isExit = false) {
    echo "<pre>";
    print_r($data);
    if($isExit === true) {
        exit;
    }
}