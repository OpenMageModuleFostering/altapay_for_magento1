<?php

if(!defined('ALTAPAY_API_ROOT'))
{
	define('ALTAPAY_API_ROOT',dirname(__DIR__));
}

require_once(ALTAPAY_API_ROOT.'/response/AltapayAbstractResponse.class.php');

class AltapayCreatePaymentRequestResponse extends AltapayAbstractResponse
{
	private $redirectURL, $result;
	
	public function __construct(SimpleXmlElement $xml)
	{
		parent::__construct($xml);
		
		if($this->getErrorCode() === '0')
		{
			$this->result = (string)$xml->Body->Result;
			$this->redirectURL = (string)$xml->Body->Url;
		}
	}
	
	public function getRedirectURL()
	{
		return $this->redirectURL;
	}
	
	public function wasSuccessful()
	{
		return $this->getErrorCode() === '0' && $this->result == 'Success';
	}
}