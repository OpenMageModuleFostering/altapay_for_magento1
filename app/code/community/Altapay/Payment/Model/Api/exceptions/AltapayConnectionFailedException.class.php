<?php

class AltapayConnectionFailedException extends AltapayMerchantAPIException
{
	public function __construct($url, $reason)
	{
		parent::__construct("Connection to ".$url." failed (reason: ".$reason.")", 23483431);
	}
	
}