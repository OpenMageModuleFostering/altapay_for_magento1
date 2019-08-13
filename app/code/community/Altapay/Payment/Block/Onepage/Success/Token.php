<?php
class Altapay_Payment_Block_Onepage_Success_Token extends Mage_Checkout_Block_Onepage_Success
{
	private $_order;
	private $_customer;	
	private $_payment;
	
	public function showBlock() {
		if(Mage::getSingleton('customer/session')->isLoggedIn()) {
			$order = $this->getOrder();
			$customer = $this->getCustomer();
			
			if($order->getCustomerId() == $customer->getId()) {
				$payment = $this->getPayment();
				if($payment->getMethod() == 'altapay_token') {	
					if($token = Mage::getModel('altapaypayment/token')->getTokenByOrder($order)) {
						
						$collection = Mage::getModel('altapaypayment/token')->getCollection()
										->addFieldToFilter('customer_id',$customer->getId())
										->addFieldToFilter('token',$token)
										;
						if(!$collection->getSize()) {
							return true;	
						}
					}
				}
			}
		}
		
		return false;
	}
	
	public function getOrder() {
		if($this->_order) {
			return $this->_order;
		}
		$this->_order = Mage::getModel('sales/order')->loadByIncrementId($this->getOrderId());
		return $this->_order;
	}

	public function getCustomer() {
		if($this->_customer) {
			return $this->_customer;
		}
		$this->_customer = Mage::getSingleton('customer/session')->getCustomer();
		return $this->_customer;
	}
	
	public function customerChooseToSaveToken() {
		if(Mage::getSingleton('customer/session')->isLoggedIn()) {
			$order = $this->getOrder();
			$customer = $this->getCustomer();
			if($order->getCustomerId() == $customer->getId()) {
				return Mage::getModel('altapaypayment/token')->customerChooseToSaveCard($order,$customer);
			}
		}
		return false;
	}
	
	public function getPayment() {
		if($this->_payment) {
			return $this->_payment;
		}
				
		$this->_payment = $this->getOrder()->getPayment();
		return $this->_payment;
	}

}