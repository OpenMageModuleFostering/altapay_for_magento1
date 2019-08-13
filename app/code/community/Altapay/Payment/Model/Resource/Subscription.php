<?php
/**
 * @author emanuel
 *
 */
class Altapay_Payment_Model_Resource_Subscription extends Mage_Core_Model_Resource_Db_Abstract
{
	protected function _construct()
	{
		$this->_init('altapaypayment/subscription', 'id');
	}
}