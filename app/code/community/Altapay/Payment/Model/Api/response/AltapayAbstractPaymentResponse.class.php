<?php

if(!defined('ALTAPAY_API_ROOT'))
{
	define('ALTAPAY_API_ROOT',dirname(__DIR__));
}

require_once(ALTAPAY_API_ROOT.'/response/AltapayAbstractResponse.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayAPIPayment.class.php');

abstract class AltapayAbstractPaymentResponse extends AltapayAbstractResponse
{
	private $result;
	private $merchantErrorMessage, $cardHolderErrorMessage;
	protected $payments = array();
	
	public function __construct(SimpleXmlElement $xml)
	{
		parent::__construct($xml);
		$this->initFromXml($xml);
	}
	
	public function __wakeup()
	{
		$this->initFromXml(new SimpleXmlElement($this->xml));
	}
	
	private function initFromXml(SimpleXmlElement $xml)
	{
		$this->payments = array();
		if($this->getErrorCode() === '0')
		{
			$this->result = strval($xml->Body->Result);
			$this->merchantErrorMessage = (string)$xml->Body->MerchantErrorMessage;
			$this->cardHolderErrorMessage = (string)$xml->Body->CardHolderErrorMessage;
			
			$this->parseBody($xml->Body);

			if(isset($xml->Body->Transactions->Transaction))
			{
				foreach($xml->Body->Transactions->Transaction as $transactionXml)
				{
					$this->addPayment(new AltapayAPIPayment($transactionXml));
				}
			}
		}
	}
	
	private function addPayment(AltapayAPIPayment $payment)
	{
		$this->payments[] = $payment;
	}
	
	/**
	 * @return AltapayAPIPayment[]
	 */
	public function getPayments()
	{
		return $this->payments;
	}
	
	/**
	 * @return AltapayAPIPayment
	 */
	public function getPrimaryPayment()
	{
		return isset($this->payments[0]) ? $this->payments[0] : null;
	}
	
	public function wasSuccessful()
	{
		return $this->getErrorCode() === '0' && $this->result == 'Success';
	}
	
	public function wasDeclined()
	{
		return $this->getErrorCode() === '0' && $this->result == 'Failed';
	}
	
	public function wasErroneous()
	{
		return $this->getErrorCode() !== '0' || $this->result == 'Error';
	}
	
	public function getMerchantErrorMessage()
	{
		return $this->merchantErrorMessage;
	}
	
	public function getCardHolderErrorMessage()
	{
		return $this->cardHolderErrorMessage;
	}
	
	abstract protected function parseBody(SimpleXmlElement $body);
}