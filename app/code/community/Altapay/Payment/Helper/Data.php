<?php

class Altapay_Payment_Helper_Data extends Mage_Core_Helper_Abstract {

	public function getExpiryMonth($expirymonth) {
		return substr("00" . $expirymonth, -2);
	}
	
}