<?php

class Altapay_Payment_Block_Onepage_Success extends Mage_Checkout_Block_Onepage_Abstract {

	protected function _construct() {
		parent::_construct();
	}

	public function getCheckoutSuccessUrl()
	{
		return Mage::getUrl('checkout/onepage/success');
	}
}