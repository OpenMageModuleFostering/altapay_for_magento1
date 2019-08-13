<?php

if(!defined('ALTAPAY_API_ROOT'))
{
	define('ALTAPAY_API_ROOT',dirname(__DIR__));
}

require_once(ALTAPAY_API_ROOT.'/response/AltapayPreauthRecurringResponse.class.php');

class AltapayCaptureRecurringResponse extends AltapayPreauthRecurringResponse
{
	public function __construct(SimpleXmlElement $xml)
	{
		parent::__construct($xml);
	}
	
	/**
	 * @return boolean
	 */
	public function wasSubscriptionReleased()
	{
		return $this->getSubscriptionPayment()->isReleased();
	}
}