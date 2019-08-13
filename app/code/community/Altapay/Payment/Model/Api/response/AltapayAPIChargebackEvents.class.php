<?php

class AltapayAPIChargebackEvents
{
	private $chargebackEvents = array();

	public function __construct(SimpleXmlElement $xml)
	{
		if(isset($xml->ChargebackEvent))
		{
			foreach($xml->ChargebackEvent as $chargebackEvent)
			{
				$this->chargebackEvents[] = new AltapayAPIChargebackEvent($chargebackEvent);
			}
		}
	}

	/**
	 * @return AltapayAPIChargebackEvent
	 */
	public function getNewest()
	{
		$newest = null; /* @var $newest AltapayAPIChargebackEvent */
		foreach($this->chargebackEvents as $chargebackEvent) /* @var $chargebackEvent AltapayAPIChargebackEvent */
		{
			if(is_null($newest) || $newest->getDate()->getTimestamp() < $chargebackEvent->getDate()->getTimestamp())
			{
				$newest = $chargebackEvent;
			}
		}

		return $newest;
	}
}