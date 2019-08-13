<?php

/**
 * Drop down to show the terminals available for checkout.
 *
 * @category   Altapay
 * @package    Altapay_Payment
 * @author     Emanuel Holm Greisen <eg@altapay.com>
 */
class Altapay_Payment_Model_Source_Terminals
	extends Varien_Object {

	/**
	 * @var AltapayGetTerminalsResponse
	 */
	private $_response;
	private $_error;
	 
	public function getTerminalCurrencies($terminalTitle = false){

		if($terminalTitle){
			return $this->_currencies[$terminalTitle];
		}else{
			return $this->_currencies;
		}
	}
	 
	public function __construct() {
		
	}
	
	private function init()
	{
		if(is_null($this->_response))
		{
			$altapay_api = new Altapay_Payment_Model_Altapay(Altapay_Payment_Helper_Utilities::guessStoreIdBasedOnParameters());
			try
			{
				$this->_response = $altapay_api->getTerminals();
			}
			catch(Exception $e)
			{
				$this->_error = $e->getMessage();
			}
		}
	}
	 
	public function toOptionArray() {
		$this->init();
		$terminals = array();
		if(!is_null($this->_response))
		{
			if($this->_response->wasSuccessful())
			{
				foreach($this->_response->getTerminals() as $terminal) /* @var $terminal AltapayTerminal */
				{
					$terminals[] = array(
		       				'value' => $terminal->getTitle(),
		       				'label' => $terminal->getTitle(),
					);
				}
				
			}
			else
			{
				$terminals[] = array(
		       				'value' => '',
		       				'label' => 'Could not get list of terminal: '.$this->_response->getErrorMessage(),
					);
			}
		}
		else if(!is_null($this->_error))
		{
			$terminals[] = array(
				'value' => '',
				'label' => $this->_error,
			);
		}
		return $terminals;
	}

	public function canUseForCurrency($terminalTitle, $currency)
	{
		$this->init();
		if($this->_response != null)
		{
			foreach($this->_response->getTerminals() as $terminal) /* @var $terminal AltapayTerminal */
			{
				if($terminal->getTitle() == $terminalTitle)
				{
					return $terminal->hasCurrency($currency);
				}
			}
		}
		return false;
	}
}