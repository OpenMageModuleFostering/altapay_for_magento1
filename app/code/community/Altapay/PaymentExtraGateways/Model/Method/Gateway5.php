<?php
class Altapay_PaymentExtraGateways_Model_Method_Gateway5 extends Altapay_Payment_Model_Method_Gateway {

	protected $_code = 'altapay_gateway5';
	protected $_formBlockType = 'altapaypaymentextragateways/form_gateway5';
	protected $_infoBlockType = 'altapaypaymentextragateways/info_gateway5';

	public function __construct($params) {
		parent::__construct($params);
	}

	public function getAltapayPaymentType($configPath, $storeId = null) 
	{	
		return parent::getAltapayPaymentType(Altapay_PaymentExtraGateways_Model_Constants::CONT_PATH_GATEWAY5_ACTION_TYPE,$storeId);	
	}
	
	protected function getAltapayTerminal()
	{
		return Mage::getStoreConfig(Altapay_PaymentExtraGateways_Model_Constants::CONF_PATH_GATEWAY5_TERMINAL);
	}
}