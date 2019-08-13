<?php
class Altapay_Payment_Model_Resource_Subscription_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Initialize resources
     *
     */
    public function _construct()
    {
		$this->_init('altapaypayment/subscription');
    }

	public function addAttributeToSort($attribute, $dir='asc')
    {
        $this->addOrder($attribute, $dir);
        return $this;
    }

}