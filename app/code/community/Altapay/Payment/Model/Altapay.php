<?php

if(!defined('ALTAPAY_API_ROOT'))
{
	define('ALTAPAY_API_ROOT',Mage::getModuleDir(null,'Altapay_Payment').'/Model/Api');
	if(!is_dir(ALTAPAY_API_ROOT))
	{
		throw new Exception("Checkout Altapay Client API: svn co svn+ssh://svn.earth.altapay.com/home/subversion/AltapayClientAPI/trunk/altapay_php_api/lib ".ALTAPAY_API_ROOT);
	}
}

require_once(ALTAPAY_API_ROOT.'/AltapayMerchantAPI.class.php');

class Altapay_Payment_Model_Altapay {

	/**
	 * @var AltapayMerchantAPI
	 */
	private $_merchantApi;
	private $_merchantApiLogger;
	private $_cachedTerminalList = null;
	private $_error_ = array();
	protected $_varien_response = null;
	private $_canSavePayment = false;

	/* PUBLIC METHODS */

	private $storeId;
	
	public function __construct($storeId=null) 
	{
		$this->storeId = $storeId;
	}
	
	private function setupApi()
	{
		if(is_null($this->_merchantApi))
		{
			Mage::log('Create Altapay Merchant API for store: '.$this->storeId, Zend_Log::INFO, 'altapay.log', true);

			$this->_merchantApiLogger = new Altapay_Payment_Helper_Logger();
			$this->_merchantApi = new AltapayMerchantAPI(
				Mage::getStoreConfig(Altapay_Payment_Model_Constants::CONF_PATH_API_INSTALLATION,$this->storeId),
				Mage::getStoreConfig(Altapay_Payment_Model_Constants::CONF_PATH_API_USERNAME,$this->storeId),
				Mage::helper('core')->decrypt(Mage::getStoreConfig(Altapay_Payment_Model_Constants::CONF_PATH_API_PASSWORD,$this->storeId)),
				$this->_merchantApiLogger
			);
		}
	}

	public function getActionType($type) {
		if ($type == Altapay_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE) {
			return Altapay_Payment_Model_Constants::ACTION_AUTHORIZE_CAPTURE;
		} else {
			return Altapay_Payment_Model_Constants::ACTION_AUTHORIZE;
		}
	}
	
	public function getURI() {
		return $this->_uri;
	}

	public function getURIMode() {
		return $this->_mode;
	}

	public function setUriMode($mode) {
		$this->_mode = $mode;
	}

	public function setMethod($altapay_payment_method) {
		$this->_method = $altapay_payment_method;
	}

	public function getMethod() {
		return $this->_method;
	}
	 
	public function getInstallation() {
		return $this->_installation;
	}
	 
	public function getUserName() {
		return $this->_user_name;
	}

	public function getPassword() {
		return $this->_password;
	}

	public function getErrorMessage() {
		if (isset($this->_error_['message'])) {
			return $this->_error_['message'];
		} else {
			return false;
		}
	}

	public function getErrorType() {
		if (isset($this->_error_['type'])) {
			return $this->_error_['type'];
		} else {
			return false;
		}
	}

	public function getAltapayMerchantAPI() {
		$this->setupApi();
		if (!$this->_merchantApi->isConnected()) {
			$this->_merchantApi->login();
		}
		return $this->_merchantApi;
	}
	 
	public function authenticate() {
		return $this->getAltapayMerchantAPI()->login();
	}
	 
	public function createPaymentRequest(
		$terminal,
		$orderid,
		$amount,
		$currencyCode,
		$paymentType,
		array $customerInfo=array(),
		$cookie=null,
		$language=null,
		array $config = array(),
		array $transactionInfo = array(),
		array $orderLines = array(),
		$accountOffer = false,
		$ccToken = null
		)
	{
		return $this->getAltapayMerchantAPI()->createPaymentRequest(
			  $terminal
			, $orderid
			, $amount
			, $currencyCode
			, $paymentType
			, $customerInfo
			, $cookie
			, $language
			, $config
			, $transactionInfo
			, $orderLines
			, $accountOffer
			, $ccToken
			);
	}

	/**
	 * This will capture using the Altapay API.
	 * TODO: Support order_lines for invoices
	 * 
	 * @return AltapayCaptureResponse
	 */
	public function captureReservation($paymentId, $amount, $orderLines, $salesTax)
	{
		return $this->getAltapayMerchantAPI()->captureReservation($paymentId, $amount, $orderLines, $salesTax);
	}
	
	/**
	 * This will capture using the Altapay API.
	 * TODO: Support order_lines for invoices
	 * 
	 * @return AltapayReleaseResponse
	 */
	public function releaseReservation($paymentId)
	{
		return $this->getAltapayMerchantAPI()->releaseReservation($paymentId);
	}
	

	/**
	 * This will capture using the Altapay API.
	 *
	 * @return AltapayRefundResponse
	 */
	public function refundCapturedReservation($paymentId, $amount, array $orderLines) {
		return $this->getAltapayMerchantAPI()->refundCapturedReservation($paymentId, $amount, $orderLines);
	}

	/**
	 * @return AltapayGetTerminalsResponse
	 */
	public function getTerminals() {
		if(is_null($this->_cachedTerminalList))
		{
			$this->_cachedTerminalList = $this->getAltapayMerchantAPI()->getTerminals();
		}
		return $this->_cachedTerminalList;
	}

	/**
	 * @return AltapayReservationResponse
	 */
	public function reservationOfFixedAmountMOTO(
		  $terminal
		, $shop_orderid
		, $amount
		, $currency
		, $cc_num
		, $cc_expiry_year
		, $cc_expiry_month
		, $cvc) {
		return $this->getAltapayMerchantAPI()->reservationOfFixedAmount(
			  $terminal
			, $shop_orderid
			, $amount
			, $currency
			, $cc_num
			, $cc_expiry_year
			, $cc_expiry_month
			, $cvc
			, 'moto');
	}
	
	/**
	 * This will make a recurring capture based on the subscription obtained via the "Recurring"-method.
	 *
	 * @return AltapayCaptureRecurringResponse
	 */
	public function captureRecurring($subscriptionId, $amount)
	{
		return $this->getAltapayMerchantAPI()->captureRecurring($subscriptionId, $amount);
	}
	
	/**
	 * This will make a recurring capture based on the subscription obtained via the "Recurring"-method.
	 *
	 * @return AltapayPreauthRecurringResponse
	 */
	public function recurringReservation($subscriptionId, $amount = null)
	{
		return $this->getAltapayMerchantAPI()->preauthRecurring($subscriptionId, $amount);
	}
	
	public function reserveSubscriptionCharge($subscriptionId, $amount = null)
	{
		return $this->getAltapayMerchantAPI()->reserveSubscriptionCharge($subscriptionId, $amount);
	}
	
}