<?php

class Altapay_Payment_Block_Info_Recurring extends Mage_Payment_Block_Info
{
	protected function _construct()
    {
        parent::_construct();
		$this->setTemplate('altapay/payment/info/recurring.phtml');
    }    
    
}