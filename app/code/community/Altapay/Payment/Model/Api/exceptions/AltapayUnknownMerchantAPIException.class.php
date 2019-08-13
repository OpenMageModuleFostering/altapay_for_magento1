<?php

class AltapayUnknownMerchantAPIException extends AltapayMerchantAPIException
{
	/**
	 * @var Exception
	 */
	private $cause;
	
	public function __construct(Exception $cause = null)
	{
		parent::__construct("Unknown error".(!is_null($cause) ? ' caused by: '.$cause->getMessage() : ''));
		$this->cause = $cause;
	}
	
	public function getCause()
	{
		return $this->cause;
	}
}