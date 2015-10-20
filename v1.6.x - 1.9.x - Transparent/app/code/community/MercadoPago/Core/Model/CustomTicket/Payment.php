<?php

/**
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL).
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category       Payment Gateway
 * @package        MercadoPago
 * @author         Gabriel Matsuoka (gabriel.matsuoka@gmail.com)
 * @copyright      Copyright (c) MercadoPago [http://www.mercadopago.com]
 * @license        http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MercadoPago_Core_Model_CustomTicket_Payment
    extends Mage_Payment_Model_Method_Abstract
{
    //configura o lugar do arquivo para listar meios de pagamento
    protected $_formBlockType = 'mercadopago/customticket_form';
    protected $_infoBlockType = 'mercadopago/customticket_info';

    protected $_code = 'mercadopago_customticket';

    protected $_canSaveCc = false;
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canVoid = true;
    protected $_canCancelInvoice = true;
    protected $_isInitializeNeeded = true;
    protected $_canFetchTransactionInfo = true;
    protected $_canCreateBillingAgreement = true;
    protected $_canReviewPayment = true;

    /**
     * @param string $paymentAction
     * @param object $stateObject
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function initialize($paymentAction, $stateObject)
    {
        $response = $this->preparePostPayment();

        if ($response !== false) {
            $this->getInfoInstance()->setAdditionalInformation('activation_uri', $response['response']['transaction_details']['external_resource_url']);
            return true;
        }

        return false;
    }

    public function assignData($data)
    {

        // route /checkout/onepage/savePayment
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        //get array info
        $info_form = $data->getData();
        $info_form = $info_form['mercadopago_customticket'];

        Mage::helper('mercadopago')->log("info form", 'mercadopago-custom.log', $info_form);

        $info = $this->getInfoInstance();
        $info->setAdditionalInformation('payment_method', $info_form['payment_method_ticket']);

        if (isset($info_form['coupon_code'])) {
            $info->setAdditionalInformation('coupon_code', $info_form['coupon_code']);
        }

        return $this;
    }

    public function preparePostPayment()
    {
        Mage::helper('mercadopago')->log("Ticket -> init prepare post payment", 'mercadopago-custom.log');
        $core = Mage::getModel('mercadopago/core');
        $quote = $this->_getQuote();
        $order_id = $quote->getReservedOrderId();
        $order = $this->_getOrder($order_id);

        //pega payment dentro da order para pegar as informacoes adicionadas pela funcao assignData()
        $payment = $order->getPayment();

        $payment_info = array();

        /* verifica se o pagamento possui coupon_code */
        if ($payment->getAdditionalInformation("coupon_code") != "") {
            $payment_info['coupon_code'] = $payment->getAdditionalInformation("coupon_code");
        }

        /* cria a preferencia padrão */
        $preference = $core->makeDefaultPreferencePaymentV1($payment_info);

        /* adiciona informações sobre pagamento com ticket */
        $preference['payment_method_id'] = $payment->getAdditionalInformation("payment_method");

        Mage::helper('mercadopago')->log("Ticket -> PREFERENCE to POST /v1/payments", 'mercadopago-custom.log', $preference);

        /* POST /v1/payments */

        return $core->postPaymentV1($preference);
    }

    public function getOrderPlaceRedirectUrl()
    {
        // requisicao vem da pagina de finalizacao de pedido
        return Mage::getUrl('mercadopago/success', array('_secure' => true));
    }


    public function getSuccessBlockType()
    {
        return $this->_successBlockType;
    }

    /**
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get admin checkout session namespace
     *
     * @return Mage_Adminhtml_Model_Session_Quote
     */
    protected function _getAdminCheckout()
    {
        return Mage::getSingleton('adminhtml/session_quote');
    }

    /**
     * Retrieves Quote
     *
     * @param integer $quoteId
     *
     * @return Mage_Sales_Model_Quote
     */
    protected function _getQuote($quoteId = null)
    {
        if (!empty($quoteId)) {
            return Mage::getModel('sales/quote')->load($quoteId);
        } else {
            if (Mage::app()->getStore()->isAdmin()) {
                return $this->_getAdminCheckout()->getQuote();
            } else {
                return $this->_getCheckout()->getQuote();
            }
        }
    }

    /**
     * Retrieves Order
     *
     * @param integer $incrementId
     *
     * @return Mage_Sales_Model_Order
     */
    protected function _getOrder($incrementId)
    {
        return Mage::getModel('sales/order')->loadByIncrementId($incrementId);
    }
}
