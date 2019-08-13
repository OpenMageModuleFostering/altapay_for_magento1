<?php

if(!defined('ALTAPAY_API_ROOT'))
{
	define('ALTAPAY_API_ROOT',dirname(__DIR__));
}

require_once(ALTAPAY_API_ROOT.'/response/AltapayAbstractResponse.class.php');
require_once(ALTAPAY_API_ROOT.'/response/AltapayTerminal.class.php');

class AltapayGetTerminalsResponse extends AltapayAbstractResponse
{
	private $terminals = array();
	
	public function __construct(SimpleXmlElement $xml)
	{
		parent::__construct($xml);
		
		if($this->getErrorCode() === '0')
		{
			foreach($xml->Body->Terminals->Terminal as $terminalXml)
			{
				$terminal = new AltapayTerminal();
				$terminal->setTitle((string)$terminalXml->Title);
				$terminal->setCountry((string)$terminalXml->Country);
				foreach($terminalXml->Natures->Nature as $nature)
				{
					$terminal->addNature((string)$nature);
				}
				foreach($terminalXml->Currencies->Currency as $currency)
				{
					$terminal->addCurrency((string)$currency);
				}
				
				$this->terminals[] = $terminal;
			}
		}
	}
	
	public function getTerminals()
	{
		return $this->terminals;
	}
	
	public function wasSuccessful()
	{
		return $this->getErrorCode() === '0';
	}
}