<?php
class Altapay_Payment_Model_Method_Gateway extends Altapay_Payment_Model_Method_Abstract {

	protected $_canAuthorize = true;
	protected $_canSaveCc = true;
	protected $_isGateway = true;
	protected $_canOrder = false;
	protected $_canCapture = true;
	protected $_canCapturePartial = true;
	protected $_canRefund = true;
	protected $_canRefundInvoicePartial = true;
	protected $_canVoid = true;
	protected $_canUseInternal = false; // Required to take MO/TO transaction
	protected $_canUseCheckout = true;
	protected $_canUseForMultishipping = true;
	protected $_isInitializeNeeded = false;
	protected $_canFetchTransactionInfo = true;
	protected $_canReviewPayment = true;
	protected $_canCreateBillingAgreement = false;
	protected $_canManageRecurringProfiles = false;
	protected $_code = 'altapay_gateway';
	protected $_formBlockType = 'altapaypayment/form_gateway';
	protected $_infoBlockType = 'altapaypayment/info_gateway';

	public function __construct($params) {
		parent::__construct($params);
	}

	public function authorize(Varien_Object $payment, $amount) {
		if($payment->getAdditionalInformation('successType') == 'open')
		{
			$payment->setIsTransactionPending(true);
			$payment->setIsCustomerNotified(false);
		}
		return parent::authorize($payment, $amount);
	}
	
	public function getCheckoutRedirectUrl()
	{
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
		//$paymentType = Altapay_Payment_Model_Constants::ACTION_AUTHORIZE;
		
		$paymentType = $this->getAltapayPaymentType('altapay/altapay_gateway/payment_action',$onePage->getQuote()->getStoreId());

		$requestConfig = $this->getAltapayRequestConfig($orderid);
		$transactionInfo = array(
				'qoute'=>$onePage->getQuote()->getId(),
		);

		$orderLines = $this->_createOrderLinesFromQuote($onePage->getQuote());

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
			$orderLines
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

	protected function _createOrderLinesFromQuote(Mage_Sales_Model_Quote $quote)
	{
		$orderLines = array();
		foreach ($this->getQuoteItemsRelevantForAltapayOrderLines($quote) as $item)
		{
			$data = $item->__toArray();

			$orderLines[] = array(
				'description'=>$data['name'],
				'itemId'=>$data['sku'],
				'quantity'=>$data['qty'],
				'taxAmount'=>$data['tax_amount'],
				'unitCode'=>'pcs', // TODO: Nice this up
				'unitPrice'=>round($data['calculation_price'], 2, PHP_ROUND_HALF_DOWN),
				'discount'=>round($data['discount_percent'], 2, PHP_ROUND_HALF_DOWN),
				'goodsType'=>'item',
			);
		}
		$totals = $quote->getTotals();
		if(($quote->getShippingAddress()->getShippingMethod() != "") && (isset($totals['shipping'])))
		{
			$orderLines[] = array(
				'description'=>$quote->getShippingAddress()->getShippingDescription(),
				'itemId'=>$quote->getShippingAddress()->getShippingMethod(),
				'quantity'=>1,
				'taxAmount'=>0,
				'unitCode'=>'pcs', // TODO: Nice this up
				'unitPrice'=>$totals['shipping']->getData('value'),
				'discount'=>0,
				'goodsType'=>'shipment',
			);
		}
		return $orderLines;
	}

	/**
	 * @returns Mage_Sales_Model_Order_Invoice_Item[]
	 */
	private function getQuoteItemsRelevantForAltapayOrderLines(Mage_Sales_Model_Quote $quote)
	{
		$items = array();

		foreach($quote->getAllItems() as $item) /* @var $item Mage_Sales_Model_Order_Invoice_Item */
		{
			$data = $item->__toArray();

			if (!empty($data['parent_item_id']) && $this->doesQuoteHaveItemWithId($quote, $data['parent_item_id']))
			{
				/**
				 * Configurable products will be represented
				 * as multiple items in the quote.
				 * 1) product with data as a combination of the configurable product
				 *    and the selected underlying simple product
				 * 2) the simple product, which has almost no data at all
				 *
				 * Number 2 is not interesting to us unless the parent item
				 * is not in the quote
				 */
				continue;
			}

			$items[] = $item;
		}

		return $items;
	}

	private function doesQuoteHaveItemWithId(Mage_Sales_Model_Quote $quote, $itemId)
	{
		foreach($quote->getAllItems() as $item) /* @var $item Mage_Sales_Model_Order_Invoice_Item */
		{
			if ($item->getId() == $itemId)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @return Mage_Checkout_Block_Onepage_Abstract
	 */
	protected function getOnepage()
	{
		return Mage::getSingleton('checkout/type_onepage');
	}
	
	protected function getAltapayRequestConfig($orderId)
	{
		return array(
				  'callback_form' => Mage::getUrl('altapaypayment/onepage/form').'?orderID=' . $orderId
				, 'callback_ok' => Mage::getUrl('altapaypayment/onepage/success').'?orderID=' . $orderId
				, 'callback_fail' => Mage::getUrl('altapaypayment/onepage/failure').'?orderID=' . $orderId
				, 'callback_redirect' => ''
				, 'callback_open' => Mage::getUrl('altapaypayment/onepage/open').'?orderID=' . $orderId
				, 'callback_notification' => Mage::getUrl('altapaypayment/onepage/notification').'?orderID=' . $orderId
		);
	}
	
	protected function getAltapayTerminal()
	{
		return Mage::getStoreConfig(Altapay_Payment_Model_Constants::CONF_PATH_GATEWAY_TERMINAL);
	}
}