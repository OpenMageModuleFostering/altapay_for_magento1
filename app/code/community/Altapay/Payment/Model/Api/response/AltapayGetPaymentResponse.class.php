<?php

if(!defined('ALTAPAY_API_ROOT'))
{
	define('ALTAPAY_API_ROOT',dirname(__DIR__));
}

require_once(ALTAPAY_API_ROOT.'/response/AltapayAbstractPaymentResponse.class.php');

class AltapayGetPaymentResponse extends AltapayAbstractPaymentResponse
{
	public function __construct(SimpleXmlElement $xml)
	{
		parent::__construct($xml);
	}
	
	protected function parseBody(SimpleXmlElement $body)
	{
		
	}

	public function wasSuccessful()
	{
		return $this->getErrorCode() === '0' && !is_null($this->getPrimaryPayment());
	}


}