<?php
/**
 * @author emanuel
 *
 */
class Altapay_Payment_Model_Subscription extends Mage_Core_Model_Abstract
{
	protected function _construct()
	{
		parent::_construct();
		$this->_init('altapaypayment/subscription');
	}
}