<?php
class Altapay_PaymentExtraGateways_Model_Method_Gateway4 extends Altapay_Payment_Model_Method_Gateway {

	protected $_code = 'altapay_gateway4';
	protected $_formBlockType = 'altapaypaymentextragateways/form_gateway4';
	protected $_infoBlockType = 'altapaypaymentextragateways/info_gateway4';

	public function __construct($params) {
		parent::__construct($params);
	}

	public function getAltapayPaymentType($configPath, $storeId = null) 
	{	
		return parent::getAltapayPaymentType(Altapay_PaymentExtraGateways_Model_Constants::CONT_PATH_GATEWAY4_ACTION_TYPE,$storeId);	
	}
	
	protected function getAltapayTerminal()
	{
		return Mage::getStoreConfig(Altapay_PaymentExtraGateways_Model_Constants::CONF_PATH_GATEWAY4_TERMINAL);
	}
}