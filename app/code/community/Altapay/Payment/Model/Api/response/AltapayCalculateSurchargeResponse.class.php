<?php

if(!defined('ALTAPAY_API_ROOT'))
{
	define('ALTAPAY_API_ROOT',dirname(__DIR__));
}

require_once(ALTAPAY_API_ROOT.'/response/AltapayAbstractResponse.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayTerminal.class.php');

class AltapayCalculateSurchargeResponse extends AltapayAbstractResponse
{
	private $result;
	private $surchargeAmount = array();
	
	public function __construct(SimpleXmlElement $xml)
	{
		parent::__construct($xml);
		
		if($this->getErrorCode() === '0')
		{
			$this->result = (string)$xml->Body->Result;
			$this->surchargeAmount = (string)$xml->Body->SurchageAmount;
		}
	}
	
	public function getSurchargeAmount()
	{
		return $this->surchargeAmount;
	}
	
	public function wasSuccessful()
	{
		return $this->result === 'Success';
	}
}