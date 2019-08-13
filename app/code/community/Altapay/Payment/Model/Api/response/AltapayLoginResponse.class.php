<?php

if(!defined('ALTAPAY_API_ROOT'))
{
	define('ALTAPAY_API_ROOT',dirname(__DIR__));
}

require_once(ALTAPAY_API_ROOT.'/response/AltapayAbstractResponse.class.php');

class AltapayLoginResponse extends AltapayAbstractResponse
{
	private $result;
	
	public function __construct(SimpleXmlElement $xml)
	{
		parent::__construct($xml);
		if($this->getErrorCode() === '0')
		{
			$this->result = (string)$xml->Body->Result;
		}
	}
	
	public function wasSuccessful()
	{
		return $this->getErrorCode() === '0' && $this->result == 'OK';
	}	
}