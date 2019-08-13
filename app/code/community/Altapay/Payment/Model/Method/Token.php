<?php
class Altapay_Payment_Model_Method_Token extends Altapay_Payment_Model_Method_Gateway {

	protected $_code = 'altapay_token';
	protected $_formBlockType = 'altapaypayment/form_token';
	protected $_infoBlockType = 'altapaypayment/info_token';
	
	
	public function getCheckoutRedirectUrl()
	{
		if(!$ccToken = Mage::app()->getRequest()->getParam('ccToken',false)) {
			return parent::getCheckoutRedirectUrl();
		} else {
			$token = Mage::getModel('altapaypayment/token')->load($ccToken);
			$ccToken = $token->getToken();
		}
		
		$onePage = $this->getOnepage();
		if(!$onePage->getQuote()->getReservedOrderId())
		{
			$onePage->getQuote()->reserveOrderId();
			$onePage->getQuote()->save();
		}
		
		$terminal       = $this->getAltapayTerminal();
		$orderid	    = $onePage->getQuote()->getReservedOrderId(); //'qoute_'.$onePage->getQuote()->getId();
		$totals         = $onePage->getQuote()->getTotals(); /** @var $totals Mage_Sales_Model_Quote_Address_Total[] */
		$grandTotal     = $totals['grand_total']->getValue();
		$currencyCode   = $onePage->getQuote()->getQuoteCurrencyCode();
		$amount	        = number_format($grandTotal, 2,'.','');
		$billingAddress = $onePage->getQuote()->getBillingAddress();
		$shippingAddress = $onePage->getQuote()->getShippingAddress();

		$customerInfo = array(
		    'billing_postal'=> $billingAddress->getData('postcode'),
		    'billing_country'=> $billingAddress->getData('country_id'),
		    'billing_address'=> $billingAddress->getData('street'),
		    'billing_city'=>$billingAddress->getData('city'),
		    'billing_region'=>$billingAddress->getData('region'),
			'billing_firstname'=> $billingAddress->getData('firstname'),
		    'billing_lastname'=> $billingAddress->getData('lastname'),
		    'email'=>$billingAddress->getData('email'),
		    'shipping_postal'=> $shippingAddress->getData('postcode'),
		    'shipping_country'=> $shippingAddress->getData('country_id'),
		    'shipping_address'=> $shippingAddress->getData('street'),
		    'shipping_city'=>$shippingAddress->getData('city'),
		    'shipping_region'=>$shippingAddress->getData('region'),
			'shipping_firstname'=> $shippingAddress->getData('firstname'),
		    'shipping_lastname'=> $shippingAddress->getData('lastname'),
			'customer_phone'=> $billingAddress->getData('telephone'),
		);

		/**
		 * Never use our paymentAndCapture type. Magento has it's own
		 * flow where payment and capture is performed in two steps.
		 */
		$paymentType = Altapay_Payment_Model_Constants::ACTION_AUTHORIZE;

		$requestConfig = $this->getAltapayRequestConfig($orderid);
		$transactionInfo = array(
				'qoute'=>$onePage->getQuote()->getId(),
		);

		$orderLines = $this->_createOrderLinesFromQuote($onePage->getQuote());
		$accountOffer = false;

		$response = $this->getAltapayModel()->createPaymentRequest(
		 	$terminal,
			$orderid,
			$amount,
			$currencyCode,
			$paymentType,
			$customerInfo,
			$_SERVER['HTTP_COOKIE'],
			Mage::app()->getLocale()->getLocale()->getLanguage(),
			$requestConfig,
			$transactionInfo,
			$orderLines,
			$accountOffer,
			$ccToken
		);
		
		if($response->wasSuccessful())
		{
			Mage::getSingleton('core/session')->setData('altapay_payment_request_url', $response->getRedirectURL());

			return $response->getRedirectURL();
		}
		else
		{
			throw new Exception($response->getErrorMessage());
		}
	}
	
	protected function getAltapayTerminal()
	{
		return Mage::getStoreConfig(Altapay_Payment_Model_Constants::CONF_PATH_TOKEN_TERMINAL);
	}
	
}