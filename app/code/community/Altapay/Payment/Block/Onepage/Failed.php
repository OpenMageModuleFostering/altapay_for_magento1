<?php

class Altapay_Payment_Block_Onepage_Failed extends Mage_Checkout_Block_Onepage_Abstract {

	protected function _construct() {
		parent::_construct();
	}

	public function getSessionAltapayPaymentRedirectUrl()
	{
		return Mage::getSingleton('core/session')->getData('altapay_payment_request_url');
	}

	public function getErrorMessage()
	{
		return Mage::getSingleton('core/session')->getData('altapay_error_message');
	}

	public function getPaymentSchemeName()
	{
		return Mage::getSingleton('core/session')->getData('altapay_payment_scheme_name');
	}
}