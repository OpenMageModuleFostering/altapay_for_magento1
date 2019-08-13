<?php
/**
 * @author emanuel
 *
 */
class Altapay_Payment_Model_Token extends Mage_Core_Model_Abstract
{
	protected function _construct()
	{
		parent::_construct();
		$this->_init('altapaypayment/token');
	}
	
	public function getTokenByOrder($order) 
	{
		if($order->getPayment()->getMethod() == 'altapay_token') {
			$additional_information = $order->getPayment()->getAdditionalInformation();
			$raw_details_info = $additional_information['raw_details_info'];
			return $raw_details_info['credit_card_token'];
		}
		
		return false;
	}
	
	public function customerChooseToSaveCard($order,$customer) {
		
		if($order->getCustomerId() && $customer->getId()) {
		
			if($order->getPayment()->getMethod() == 'altapay_token') {
				$additional_information = $order->getPayment()->getAdditionalInformation();
				$raw_details_info = $additional_information['raw_details_info'];
	
				if(isset($raw_details_info['transaction_info']) && $transaction_info = $raw_details_info['transaction_info']) {
					if(isset($transaction_info['savecreditcard'])) {
						if($savecreditcard = (bool)$transaction_info['savecreditcard']) {
							$this->saveToken($order,$customer);
						}
						return $savecreditcard;
					}
				} 
			}
				
		}
		
		return false;
	}
	
	public function saveToken($order,$customer) {
		
		$additional_information = $order->getPayment()->getAdditionalInformation();
		$raw_details_info = $additional_information['raw_details_info'];
		
		$collection = Mage::getModel('altapaypayment/token')->getCollection()
						->addFieldToFilter('customer_id',$customer->getId())
						->addFieldToFilter('deleted',0);
						
		$primary = ($collection->getSize()) ? 0 : 1;
		
		$token = Mage::getModel('altapaypayment/token')
			->setCustomerId($customer->getId())
			->setToken($raw_details_info['credit_card_token'])
			->setMaskedPan($raw_details_info['masked_credit_card'])		
			->setCurrencyCode(Mage::helper('altapaypayment/currencyMapper')->getAlpha($raw_details_info['currency']))
			->setCustomName($raw_details_info['masked_credit_card'])
			->setPrimary($primary)
			->save();
		
	}
	
	public function getAllCustomerTokens()
	{
		if(Mage::getSingleton('customer/session')->isLoggedIn()) {
			$collection = Mage::getModel('altapaypayment/token')->getCollection()
				->addFieldToFilter('customer_id',Mage::getSingleton('customer/session')->getCustomer()->getId());
			return $collection;
		}
		return false;
	}
	
	public function getCollection($includeDeleted = false) 
	{	
		$collection = parent::getCollection();
		if(!$includeDeleted) {
			$collection->addFieldToFilter('deleted',0);	
		}
			
		return $collection;
	}
	
}