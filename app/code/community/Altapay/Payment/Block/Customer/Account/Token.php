<?php
class Altapay_Payment_Block_Customer_Account_Token extends Mage_Core_Block_Template
{
	public function getTokens()
	{
		$tokens = false;
		if(Mage::getSingleton('customer/session')->isLoggedIn()) {
			$tokens = Mage::getModel('altapaypayment/token')->getCollection()
						->addFieldToFilter('customer_id',Mage::getSingleton('customer/session')->getCustomer()->getId());
		}
		
		return $tokens;
	}
	
	
}