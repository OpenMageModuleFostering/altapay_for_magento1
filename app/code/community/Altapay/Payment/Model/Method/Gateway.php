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
		    'billing_region'=>$this->getStateCode($billingAddress->getData('region')),
			'billing_firstname'=> $billingAddress->getData('firstname'),
		    'billing_lastname'=> $billingAddress->getData('lastname'),
		    'email'=>$billingAddress->getData('email'),
		    'shipping_postal'=> $shippingAddress->getData('postcode'),
		    'shipping_country'=> $shippingAddress->getData('country_id'),
		    'shipping_address'=> $shippingAddress->getData('street'),
		    'shipping_city'=>$shippingAddress->getData('city'),
		    'shipping_region'=>$this->getStateCode($billingAddress->getData('region')),
			'shipping_firstname'=> $shippingAddress->getData('firstname'),
		    'shipping_lastname'=> $shippingAddress->getData('lastname'),
			'customer_phone'=> $billingAddress->getData('telephone'),
		);

		/**
		 * Never use our paymentAndCapture type. Magento has it's own
		 * flow where payment and capture is performed in two steps.
		 */
		//$paymentType = Altapay_Payment_Model_Constants::ACTION_AUTHORIZE;
		
		$paymentType = $this->getAltapayPaymentType(Altapay_Payment_Model_Constants::CONT_PATH_GATEWAY_ACTION_TYPE,$onePage->getQuote()->getStoreId());

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

	private function getStateCode($state){ //only focusing on US and Mexico states.
		$stateCode = "";
		switch(trim(strtolower($state))){
			case "alabama":
				$stateCode = "AL";
			break;
			case "alaska":
				$stateCode = "AK";
			break;
			case "arizona":
				$stateCode = "AZ";
			break;
			case "arkansas":
				$stateCode = "AR";
			break;
			case "california":
				$stateCode = "AR";
			break;
			case "colorado":
				$stateCode = "CO";
			break;
			case "connecticut":
				$stateCode = "CT";
			break;
			case "delaware":
				$stateCode = "DE";
			break;
			case "districtofcolumbia":
				$stateCode = "DC";
			break;
			case "florida":
				$stateCode = "FL";
			break;
			case "georgia":
				$stateCode = "GA";
			break;
			case "hawaii":
				$stateCode = "HI";
			break;
			case "idaho":
				$stateCode = "ID";
			break;
			case "illinois":
				$stateCode = "IL";
			break;
			case "indiana":
				$stateCode = "IN";
			break;
			case "iowa":
				$stateCode = "IA";
			break;
			case "kansas":
				$stateCode = "KS";
			break;
			case "kentucky":
				$stateCode = "KY";
			break;
			case "louisiana":
				$stateCode = "LA";
			break;
			case "maine":
				$stateCode = "ME";
			break;
			case "maryland":
				$stateCode = "MD";
			break;
			case "massachusetts":
				$stateCode = "MA";
			break;
			case "michigan":
				$stateCode = "MI";
			break;
			case "minnesota":
				$stateCode = "MN";
			break;
			case "mississippi":
				$stateCode = "MS";
			break;
			case "missouri":
				$stateCode = "MO";
			break;
			case "montana":
				$stateCode = "MT";
			break;
			case "nebraska":
				$stateCode = "NE";
			break;
			case "nevada":
				$stateCode = "NV";
			break;
			case "newhampshire":
				$stateCode = "NH";
			break;
			case "newjersey":
				$stateCode = "NJ";
			break;
			case "newmexico":
				$stateCode = "NM";
			break;
			case "newyork":
				$stateCode = "NY";
			break;
			case "northcarolina":
				$stateCode = "NC";
			break;
			case "northdakota":
				$stateCode = "ND";
			break;
			case "ohio":
				$stateCode = "OH";
			break;
			case "oklahoma":
				$stateCode = "OK";
			break;
			case "oregon":
				$stateCode = "OR";
			break;
			case "pennsylvania":
				$stateCode = "PA";
			break;
			case "puertorico":
				$stateCode = "PR";
			break;
			case "rhodeisland":
				$stateCode = "RI";
			break;
			case "southcarolina":
				$stateCode = "SC";
			break;
			case "southdakota":
				$stateCode = "SD";
			break;
			case "tennessee":
				$stateCode = "TN";
			break;
			case "texas":
				$stateCode = "TX";
			break;
			case "utah":
				$stateCode = "UT";
			break;
			case "vermont":
				$stateCode = "VT";
			break;
			case "virginia":
				$stateCode = "VA";
			break;
			case "washington":
				$stateCode = "WA";
			break;
			case "westvirginia":
				$stateCode = "WV";
			break;
			case "wisconsin":
				$stateCode = "WI";
			break;
			case "wyoming":
				$stateCode = "WY";
			break;
			case "armedforcesamericas":
				$stateCode = "AA";
			break;
			case "armedforceseurope":
				$stateCode = "AE";
			break;
			case "asrmedforcespacific":
				$stateCode = "AP";
			break;
			case "americansamoa":
				$stateCode = "AS";
			break;
			case "federatedstatesofmicronesia":
				$stateCode = "FM";
			break;
			case "guam":
				$stateCode = "GU";
			break;
			case "marshallislands":
				$stateCode = "MH";
			break;
			case "northernmarianaislands":
				$stateCode = "MP";
			break;
			case "palau":
				$stateCode = "PW";
			break;
			case "virginislands":
				$stateCode = "VI";
			break;
			//==========================================================================================================
			case "aguascalientes":
				$stateCode = "AGS";
			break;
			case "bajacalifornia":
				$stateCode = "BC";
			break;
			case "bajacaliforniasur":
				$stateCode = "BCS";
			break;
			case "campeche":
				$stateCode = "CAMP";
			break;
			case "chiapas":
				$stateCode = "CHIS";
			break;
			case "chihuahua":
				$stateCode = "CHIH";
			break;
			case "coahuila":
				$stateCode = "COAH";
			break;
			case "colima":
				$stateCode = "COL";
			break;
			case "distritofederal":
				$stateCode = "DF";
			break;
			case "durango":
				$stateCode = "DGO";
			break;
			case "estadodeméxico":
				$stateCode = "MEX";
			break;
			case "guanajuato":
				$stateCode = "GTO";
			break;
			case "guerrero":
				$stateCode = "GRO";
			break;
			case "hidalgo":
				$stateCode = "HGO";
			break;
			case "jalisco":
				$stateCode = "JAL";
			break;
			case "michoacán":
				$stateCode = "MICH";
			break;
			case "morelos":
				$stateCode = "MOR";
			break;
			case "nayarit":
				$stateCode = "NAY";
			break;
			case "nuevoleón":
				$stateCode = "NL";
			break;
			case "oaxaca":
				$stateCode = "OAX";
			break;
			case "puebla":
				$stateCode = "PUE";
			break;
			case "querétaro":
				$stateCode = "QRO";
			break;
			case "quintanaroo":
				$stateCode = "Q ROO";
			break;
			case "sanluispotosí":
				$stateCode = "SLP";
			break;
			case "sinaloa":
				$stateCode = "SIN";
			break;
			case "sonora":
				$stateCode = "SON";
			break;
			case "tabasco":
				$stateCode = "TAB";
			break;
			case "tamaulipas":
				$stateCode = "TAMPS";
			break;
			case "tlaxcala":
				$stateCode = "TLAX";
			break;
			case "veracruz":
				$stateCode = "VER";
			break;
			case "yucatán":
				$stateCode = "YUC";
			break;
			case "zacatecas":
				$stateCode = "ZAC";
			break;
			default:
				$stateCode = $state;
		}

		return $stateCode;
	}
}