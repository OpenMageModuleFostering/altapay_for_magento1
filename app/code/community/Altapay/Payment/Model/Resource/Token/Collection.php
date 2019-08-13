<?php
class Altapay_Payment_Model_Resource_Token_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    public function _construct()
    {
		$this->_init('altapaypayment/token');
    }

}