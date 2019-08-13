<?php

class Altapay_Payment_Block_Form_Recurring extends Mage_Payment_Block_Form_Cc
{

	protected function _construct()
	{
		parent::_construct();
		$this->setTemplate('altapay/payment/form/recurring.phtml');
	}

	protected function getSubscriptions()
	{
		$options = array();
		
		$customer = $this->getOnepage()->getCustomerSession()->getCustomer();
		if($customer->getId())
		{
			$subscriptions = Mage::getModel('altapaypayment/subscription')->getCollection()->addFieldToFilter('customer_id',$customer->getId());

			foreach($subscriptions as $subscription)
			{
				if($subscription->getCurrencyCode() == $this->getOnepage()->getQuote()->getQuoteCurrencyCode())
				{
					$options[$subscription->getId()] = $subscription->getMaskedPan();
				}
			}
		}
		$options['new'] = 'Use a new card';
		return $options;
	}

	
	/**
	 * Get one page checkout model
	 *
	 * @return Mage_Checkout_Model_Type_Onepage
	 */
	public function getOnepage()
	{
		return Mage::getSingleton('checkout/type_onepage');
	}
}