<?php

if(!defined('ALTAPAY_API_ROOT'))
{
	define('ALTAPAY_API_ROOT',__DIR__);
}

require_once(ALTAPAY_API_ROOT.'/IAltapayCommunicationLogger.class.php');
require_once(ALTAPAY_API_ROOT.'/request/AltapayAPITransactionsRequest.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayGetTerminalsResponse.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayGetPaymentResponse.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayLoginResponse.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayCreatePaymentRequestResponse.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayCaptureResponse.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayRefundResponse.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayReleaseResponse.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayReservationResponse.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayCaptureRecurringResponse.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayPreauthRecurringResponse.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayAPIPaymentNatureService.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayAPICustomerInfo.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayAPICountryOfOrigin.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayAPIAddress.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayAPIPaymentInfos.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayAPIFunding.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayAPIChargebackEvent.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayAPIChargebackEvents.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayCalculateSurchargeResponse.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayFundingListResponse.class.php');
require_once(ALTAPAY_API_ROOT.'/http/AltapayFOpenBasedHttpUtils.class.php');
require_once(ALTAPAY_API_ROOT.'/http/AltapayCurlBasedHttpUtils.class.php');
require_once(ALTAPAY_API_ROOT.'/exceptions/AltapayMerchantAPIException.class.php');
require_once(ALTAPAY_API_ROOT.'/exceptions/AltapayUnauthorizedAccessException.class.php');
require_once(ALTAPAY_API_ROOT.'/exceptions/AltapayRequestTimeoutException.class.php');
require_once(ALTAPAY_API_ROOT.'/exceptions/AltapayConnectionFailedException.class.php');
require_once(ALTAPAY_API_ROOT.'/exceptions/AltapayInvalidResponseException.class.php');
require_once(ALTAPAY_API_ROOT.'/exceptions/AltapayUnknownMerchantAPIException.class.php');
require_once(ALTAPAY_API_ROOT.'/exceptions/AltapayXmlException.class.php');
require_once(ALTAPAY_API_ROOT.'/ALTAPAY_VERSION.php');

class AltapayMerchantAPI
{
	private $baseURL, $username, $password;
	private $connected = false;
	/**
	 * @var IAltapayCommunicationLogger
	 */
	private $logger;
	private $httpUtil;

	public function __construct($baseURL, $username, $password, IAltapayCommunicationLogger $logger = null, IAltapayHttpUtils $httpUtil = null)
	{
		$this->connected = false;
		$this->baseURL = rtrim($baseURL, '/');
		$this->username = $username;
		$this->password = $password;
		$this->logger = $logger;
		
		if(is_null($httpUtil))
		{
			if(function_exists('curl_init'))
			{
				$httpUtil = new AltapayCurlBasedHttpUtils();
			}
			else if(ini_get('allow_url_fopen'))
			{
				$httpUtil = new AltapayFOpenBasedHttpUtils();
			}
			else
			{
				throw new Exception("Neither allow_url_fopen nor cURL is installed, we cannot communicate with Altapay's Payment Gateway without at least one of them.");
			}
		}
		$this->httpUtil = $httpUtil;
	}

	private function checkConnection()
	{
		if(!$this->connected)
		{
			throw new Exception("Not Connected, invoke login() before using any API calls");
		}
	}
	
	public function isConnected()
	{
		return $this->connected;
	}

	private function maskPan($pan)
	{
		if(strlen($pan) >= 10)
		{
			return  substr($pan, 0, 6).str_repeat('x', strlen($pan) - 10).substr($pan, -4);
		}
		else
		{
			return $pan;
		}
	}

	private function callAPIMethod($method, array $args = array())
	{
		$absoluteUrl = $this->baseURL."/merchant/API/".$method;

		if(!is_null($this->logger))
		{
			$loggedArgs = $args;
			if(isset($loggedArgs['cardnum']))
			{
				$loggedArgs['cardnum'] = $this->maskPan($loggedArgs['cardnum']);
			}
			if(isset($loggedArgs['cvc']))
			{
				$loggedArgs['cvc'] = str_repeat('x', strlen($loggedArgs['cvc']));
			}
			$logId = $this->logger->logRequest($absoluteUrl.'?'.http_build_query($loggedArgs));
		}

		$request = new AltapayHttpRequest();
		$request->setUrl($absoluteUrl);
		$request->setParameters($args);
		$request->setUser($this->username);
		$request->setPass($this->password);
		$request->setMethod('POST');
		$request->addHeader('x-altapay-client-version: '.ALTAPAY_VERSION);

		$response = $this->httpUtil->requestURL($request);
		
		if(!is_null($this->logger))
		{
			$this->logger->logResponse($logId, print_r($response, true));
		}

		if($response->getConnectionResult() == AltapayHttpResponse::CONNECTION_OKAY)
		{
			if($response->getHttpCode() == 200)
			{
				if(stripos($response->getContentType(), "text/xml") !== false)
				{
					try
					{
						return new SimpleXMLElement($response->getContent());
					}
					catch(Exception $e)
					{
						if($e->getMessage() == 'String could not be parsed as XML')
						{
							throw new AltapayInvalidResponseException("Unparsable XML Content in response");
						}
						throw new AltapayUnknownMerchantAPIException($e);
					}
				}
				elseif (stripos($response->getContentType(), "text/csv") !== false)
				{
					return $response->getContent();
				}
				else
				{
					throw new AltapayInvalidResponseException("Non XML ContentType (was: ".$response->getContentType().")");
				}
			}
			else if($response->getHttpCode() == 401)
			{
				throw new AltapayUnauthorizedAccessException($absoluteUrl, $this->username);
			}
			else
			{
				throw new AltapayInvalidResponseException("Non HTTP 200 Response: ".$response->getHttpCode());
			}
		}
		else if($response->getConnectionResult() == AltapayHttpResponse::CONNECTION_REFUSED)
		{
			throw new AltapayConnectionFailedException($absoluteUrl, 'Connection refused');
		}
		else if($response->getConnectionResult() == AltapayHttpResponse::CONNECTION_TIMEOUT)
		{
			throw new AltapayConnectionFailedException($absoluteUrl, 'Connection timed out');
		}
		else if($response->getConnectionResult() == AltapayHttpResponse::CONNECTION_READ_TIMEOUT)
		{
			throw new AltapayRequestTimeoutException($absoluteUrl);
		}
		else
		{
			throw new AltapayUnknownMerchantAPIException();
		}
	}

	/**
	 * @return AltapayFundingListResponse
	 * @throws AltapayMerchantAPIException
	 */
	public function getFundingList($page=0)
	{
		$this->checkConnection();

		return new AltapayFundingListResponse($this->callAPIMethod('fundingList', array('page'=>$page)));
	}
	
	/**
	 * @return string|boolean
	 * @throws AltapayMerchantAPIException
	 */
	public function downloadFundingCSV(AltapayAPIFunding $funding)
	{
		$this->checkConnection();

		$request = new AltapayHttpRequest();
		$request->setUrl($funding->getDownloadLink());
		$request->setUser($this->username);
		$request->setPass($this->password);
		$request->setMethod('GET');
		
		$response = $this->httpUtil->requestURL($request);
		
		if($response->getHttpCode() == 200)
		{
			return $response->getContent();
		}
		
		return false;
	}

	/**
	 * @return string|boolean
	 * @throws AltapayMerchantAPIException
	 */
	public function downloadFundingCSVByLink($downloadLink)
	{
		$this->checkConnection();

		$request = new AltapayHttpRequest();

		$request->setUrl($downloadLink);
		$request->setUser($this->username);
		$request->setPass($this->password);
		$request->setMethod('GET');

		$response = $this->httpUtil->requestURL($request);

		if($response->getHttpCode() == 200)
		{
			return $response->getContent();
		}

		return false;
	}
	
	private function reservationInternal(
			  $apiMethod
			, $terminal
			, $shop_orderid
			, $amount
			, $currency
			, $cc_num
			, $cc_expiry_year
			, $cc_expiry_month
			, $credit_card_token
			, $cvc
			, $type
			, $payment_source
			, array $customerInfo
			, array $transaction_info)
	{
		$this->checkConnection();
	
		$args = array(
				'terminal'=>$terminal,
				'shop_orderid'=>$shop_orderid,
				'amount'=>$amount,
				'currency'=>$currency,
				'cvc'=>$cvc,
				'type'=>$type,
				'payment_source'=>$payment_source
		);
		if(!is_null($credit_card_token))
		{
			$args['credit_card_token'] = $credit_card_token;
		}
		else
		{
			$args['cardnum'] = $cc_num;
			$args['emonth'] = $cc_expiry_month;
			$args['eyear'] = $cc_expiry_year;
		}

        if(!is_null($customerInfo) && is_array($customerInfo))
        {
            $this->addCustomerInfo($customerInfo, $args);
        }

        // Not needed when everyone has been upgraded to 20150428
        // ====================================================================
		foreach(array('billing_city', 'billing_region', 'billing_postal', 'billing_country', 'email', 'customer_phone', 'bank_name', 'bank_phone', 'billing_firstname', 'billing_lastname', 'billing_address') as $custField)
		{
			if(isset($customerInfo[$custField]))
			{
				$args[$custField] = $customerInfo[$custField];
			}
		}
        // ====================================================================
		if(count($transaction_info) > 0)
		{
			$args['transaction_info'] = $transaction_info;
		}
	
		return new AltapayReservationResponse(
				$this->callAPIMethod(
						$apiMethod,
						$args
				)
		);
	}
	

	/**
	 * @return AltapayReservationResponse
	 * @throws AltapayMerchantAPIException
	 */
	public function reservationOfFixedAmount(
		  $terminal
		, $shop_orderid
		, $amount
		, $currency
		, $cc_num
		, $cc_expiry_year
		, $cc_expiry_month
		, $cvc
		, $payment_source
		, array $customerInfo = array()
		, array $transactionInfo = array())
	{
		return $this->reservationInternal(
				'reservationOfFixedAmountMOTO'
				, $terminal
				, $shop_orderid
				, $amount
				, $currency
				, $cc_num
				, $cc_expiry_year
				, $cc_expiry_month
				, null // $credit_card_token
				, $cvc
				, 'payment'
				, $payment_source
				, $customerInfo
				, $transactionInfo);
	}

	/**
	 * @return AltapayReservationResponse
	 * @throws AltapayMerchantAPIException
	 */
	public function reservationOfFixedAmountMOTOWithToken(
		$terminal
		, $shop_orderid
		, $amount
		, $currency
		, $credit_card_token
		, $cvc = null
		, $payment_source = 'moto'
		, array $customerInfo = array()
		, array $transactionInfo = array())
	{
		return $this->reservationInternal(
				'reservationOfFixedAmountMOTO'
				, $terminal
				, $shop_orderid
				, $amount
				, $currency
				, null
				, null
				, null
				, $credit_card_token
				, $cvc
				, 'payment'
				, $payment_source
				, $customerInfo
				, $transactionInfo);
	}

	/**
	 * @return AltapayReservationResponse
	 * @throws AltapayMerchantAPIException
	 */
	public function setupSubscription(
		$terminal
		, $shop_orderid
		, $amount
		, $currency
		, $cc_num
		, $cc_expiry_year
		, $cc_expiry_month
		, $cvc
		, $payment_source
		, array $customerInfo = array()
		, array $transactionInfo = array())
	{
		return $this->reservationInternal(
				'setupSubscription'
				, $terminal
				, $shop_orderid
				, $amount
				, $currency
				, $cc_num
				, $cc_expiry_year
				, $cc_expiry_month
				, null // $credit_card_token
				, $cvc
				, 'subscription'
				, $payment_source
				, $customerInfo
				, $transactionInfo);		
	}

	/**
	 * @return AltapayReservationResponse
	 * @throws AltapayMerchantAPIException
	 */
	public function setupSubscriptionWithToken(
		$terminal
		, $shop_orderid
		, $amount
		, $currency
		, $credit_card_token
		, $cvc = null
		, $payment_source = 'moto'
		, array $customerInfo = array()
		, array $transactionInfo = array())
	{
		return $this->reservationInternal(
			'setupSubscription'
			, $terminal
			, $shop_orderid
			, $amount
			, $currency
			, null
			, null
			, null
			, $credit_card_token
			, $cvc
			, 'subscription'
			, $payment_source
			, $customerInfo
			, $transactionInfo);
	}
	
	/**
	 * @return AltapayReservationResponse
	 * @throws AltapayMerchantAPIException
	 */
	public function verifyCard(
		$terminal
		, $shop_orderid
		, $currency
		, $cc_num
		, $cc_expiry_year
		, $cc_expiry_month
		, $cvc
		, $payment_source
		, array $customerInfo = array()
		, array $transactionInfo = array())
	{
		return $this->reservationInternal(
				'reservationOfFixedAmountMOTO'
				, $terminal
				, $shop_orderid
				, 1.00
				, $currency
				, $cc_num
				, $cc_expiry_year
				, $cc_expiry_month
				, null // $credit_card_token
				, $cvc
				, 'verifyCard'
				, $payment_source
				, $customerInfo
				, $transactionInfo);		
	}

	/**
	 * @return AltapayReservationResponse
	 * @throws AltapayMerchantAPIException
	 */
	public function verifyCardWithToken(
		$terminal
		, $shop_orderid
		, $currency
		, $credit_card_token
		, $cvc = null
		, $payment_source = 'moto'
		, array $customerInfo = array()
		, array $transactionInfo = array())
	{
		return $this->reservationInternal(
			'reservationOfFixedAmountMOTO'
			, $terminal
			, $shop_orderid
			, 1.00
			, $currency
			, null
			, null
			, null
			, $credit_card_token
			, $cvc
			, 'verifyCard'
			, $payment_source
			, $customerInfo
			, $transactionInfo);
	}
	
	
	/**
	 * @return AltapayCaptureResponse
	 * @throws AltapayMerchantAPIException
	 */
	public function captureReservation($paymentId, $amount=null, array $orderLines=array(), $salesTax=null, $reconciliationIdentifier=null, $invoiceNumber=null)
	{
		$this->checkConnection();

		return new AltapayCaptureResponse(
			$this->callAPIMethod(
				'captureReservation',
				array(
					'transaction_id'=>$paymentId,
					'amount'=>$amount,
					'orderLines'=>$orderLines,
					'sales_tax'=>$salesTax,
					'reconciliation_identifier'=>$reconciliationIdentifier,
					'invoice_number'=>$invoiceNumber
				)
			)
		);
	}

	/**
	 * @return AltapayRefundResponse
	 * @throws AltapayMerchantAPIException
	 */
	public function refundCapturedReservation($paymentId, $amount=null, $orderLines=null, $reconciliationIdentifier=null, $allowOverRefund=null, $invoiceNumber=null)
	{
		$this->checkConnection();

		return new AltapayRefundResponse(
			$this->callAPIMethod(
				'refundCapturedReservation',
				array(
					'transaction_id'=>$paymentId, 
					'amount'=>$amount,
					'orderLines'=>$orderLines,
					'reconciliation_identifier'=>$reconciliationIdentifier,
					'allow_over_refund'=>$allowOverRefund,
					'invoice_number'=>$invoiceNumber
				)
			)
		);
	}

	/**
	 * @return AltapayReleaseResponse
	 * @throws AltapayMerchantAPIException
	 */
	public function releaseReservation($paymentId, $amount=null)
	{
		$this->checkConnection();

		return new AltapayReleaseResponse(
			$this->callAPIMethod(
				'releaseReservation',
				array(
					'transaction_id'=>$paymentId
				)
			)
		);
	}

	/**
	 * @return AltapayGetPaymentResponse
	 * @throws AltapayMerchantAPIException
	 */
	public function getPayment($paymentId)
	{
		$this->checkConnection();

		return new AltapayGetPaymentResponse($this->callAPIMethod(
			'payments',
			array(
				'transaction'=>$paymentId
			)
		));
	}
	
	/**
	 * @return AltapayGetTerminalsResponse
	 * @throws AltapayMerchantAPIException
	 */
	public function getTerminals()
	{
		$this->checkConnection();

		return new AltapayGetTerminalsResponse($this->callAPIMethod('getTerminals'));
	}

	/**
	 * @return AltapayLoginResponse
	 * @throws AltapayMerchantAPIException
	 */
	public function login()
	{
		$this->connected = false;
		
		$response = new AltapayLoginResponse($this->callAPIMethod('login'));
		
		if($response->getErrorCode() === '0')
		{
			$this->connected = true;
		}
		
		return $response;
	}
	
	/**
	 * @return AltapayCreatePaymentRequestResponse
	 * @throws AltapayMerchantAPIException
	 */
	public function createPaymentRequest($terminal,
			$orderid,
			$amount,
			$currencyCode,
			$paymentType,
			$customerInfo = null,
			$cookie = null,
			$language = null,
			array $config = array(),
			array $transaction_info = array(),
			array $orderLines = array(),
			$accountOffer = false,
			$ccToken = null
		)
	{
		$args = array(
			'terminal'=>$terminal,
			'shop_orderid'=>$orderid,
			'amount'=>$amount,
			'currency'=>$currencyCode,
			'type'=>$paymentType
		);
		
		if(!is_null($customerInfo) && is_array($customerInfo))
		{
            $this->addCustomerInfo($customerInfo, $args);
		}
		
		if(!is_null($cookie))
		{
			$args['cookie'] = $cookie;
		}  
		if(!is_null($language))
		{
			$args['language'] = $language;
		}
		if(count($transaction_info) > 0)
		{
			$args['transaction_info'] = $transaction_info;
		}
		if(count($orderLines) > 0)
		{
			$args['orderLines'] = $orderLines;
		}
		if(in_array($accountOffer, array("required", "disabled")))
		{
			$args['account_offer'] = $accountOffer;
		}
        if(!is_null($ccToken))
        {
            $args['ccToken'] = $ccToken;
        }

		$args['config'] = $config;
	
			
		return new AltapayCreatePaymentRequestResponse($this->callAPIMethod('createPaymentRequest', $args));
	}
	
	/**
	 * @return AltapayCaptureRecurringResponse
	 * @deprecated - use chargeSubscription instead.
	 * @throws AltapayMerchantAPIException
	 */
	public function captureRecurring($subscriptionId, $amount=null)
	{
		return $this->chargeSubscription($subscriptionId, $amount);
	}	
		
	/**
	 * @return AltapayCaptureRecurringResponse
	 * @throws AltapayMerchantAPIException
	 */
	public function chargeSubscription($subscriptionId, $amount=null)
	{
		$this->checkConnection();

		return new AltapayCaptureRecurringResponse(
			$this->callAPIMethod(
				'chargeSubscription',
				array(
					'transaction_id'=>$subscriptionId, 
					'amount'=>$amount,
				)
			)
		);
	}
	
	/**
	 * @return AltapayPreauthRecurringResponse
	 * @deprecated - use reserveSubscriptionCharge instead
	 * @throws AltapayMerchantAPIException
	 */
	public function preauthRecurring($subscriptionId, $amount=null)
	{
		return $this->reserveSubscriptionCharge($subscriptionId, $amount);
	}
	
	
	/**
	 * @return AltapayPreauthRecurringResponse
	 * @throws AltapayMerchantAPIException
	 */
	public function reserveSubscriptionCharge($subscriptionId, $amount=null)
	{
		$this->checkConnection();

		return new AltapayPreauthRecurringResponse(
			$this->callAPIMethod(
				'reserveSubscriptionCharge',
				array(
					'transaction_id'=>$subscriptionId, 
					'amount'=>$amount,
				)
			)
		);
	}

	/**
	 * @return AltapayCalculateSurchargeResponse
	 * @throws AltapayMerchantAPIException
	 */
	public function calculateSurcharge($terminal, $cardToken, $amount, $currency)
	{
		$this->checkConnection();
	
		return new AltapayCalculateSurchargeResponse(
				$this->callAPIMethod(
						'calculateSurcharge',
						array(
								'terminal'=>$terminal,
								'credit_card_token'=>$cardToken,
								'amount'=>$amount,
								'currency'=>$currency,
						)
				)
		);
	}
	
	/**
	 * @return AltapayCalculateSurchargeResponse
	 * @throws AltapayMerchantAPIException
	 */
	public function calculateSurchargeForSubscription($subscriptionId, $amount)
	{
		$this->checkConnection();
	
		return new AltapayCalculateSurchargeResponse(
				$this->callAPIMethod(
						'calculateSurcharge',
						array(
								'payment_id'=>$subscriptionId,
								'amount'=>$amount,
						)
				)
		);
	}

	/**
	 * @return string|boolean
	 * @throws AltapayMerchantAPIException
	 */
	public function getCustomReport($args)
	{
		$this->checkConnection();
		$response = $this->callAPIMethod('getCustomReport', $args);
		return $response;
	}

	/**
	 * @return string|boolean
	 * @throws AltapayMerchantAPIException
	 */
	public function getTransactions(AltapayAPITransactionsRequest $transactionsRequest)
	{
		$this->checkConnection();
		return $this->callAPIMethod('transactions', $transactionsRequest->asArray());
	}

    /**
     * @param $customerInfo
     * @param $args
     * @throws AltapayMerchantAPIException
     */
    private function addCustomerInfo($customerInfo, &$args)
    {
        $errors = array();

        foreach ($customerInfo as $customerInfoKey => $customerInfoValue) {
            if (is_array($customerInfo[$customerInfoKey])) {
                $errors[] = "customer_info[$customerInfoKey] is not expected to be an array";
            }
        }
        if (count($errors) > 0) {
            throw new AltapayMerchantAPIException("Failed to create customer_info variable: \n" . print_r($errors, true));
        }
        $args['customer_info'] = $customerInfo;
    }
}
