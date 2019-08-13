<?php
class Altapay_Payment_TokenController extends Mage_Core_Controller_Front_Action 
{
	
	public function preDispatch()
    {
        parent::preDispatch();
        $action = $this->getRequest()->getActionName();
        $loginUrl = Mage::helper('customer')->getLoginUrl();
 
        if (!Mage::getSingleton('customer/session')->authenticate($this, $loginUrl)) {
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            $this->_redirect('/');
        } elseif(!Mage::getStoreConfig('payment/altapay_token/active_customer_token_control')) {
	        $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            $this->_redirect('customer/account');
        }

    }

	public function viewAction() 
	{
		$this->loadLayout();
		$this->_initLayoutMessages('customer/session');
		$this->renderLayout();
	}

	public function saveAction() 
	{
		$redirect = "/";
		if ($this->_validateFormKey()) {
			if(Mage::getSingleton('customer/session')->isLoggedIn()) {

				if($incrementId = $this->getRequest()->getParam('order_id',false)) {
					$order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
					if($order->getId() && $order->getPayment()->getMethod() == 'altapay_token') {
						$customer = Mage::getSingleton('customer/session')->getCustomer();
						if($order->getCustomerId() == $customer->getId()) {
							$redirect = "customer/token/view";
							// ALT ER GODT
							
							Mage::getModel('altapaypayment/token')->saveToken($order,$customer);							
						}	
					} 
				} 
			} 
		}
		$this->_redirect($redirect);
	}
	
	public function updateCustomNameAction() 
	{
		$response = array('status' => 'error');
		$this->getResponse()->setHeader('Content-type', 'text/json; charset=UTF-8');
		$tokenId = $this->getRequest()->getParam('token_id',false);
		$customName = $this->getRequest()->getParam('custom_name',false);	

		if($tokenId && $customName) 
		{	
			if(Mage::getSingleton('customer/session')->isLoggedIn()) {
	
				$token = Mage::getModel('altapaypayment/token')->load($tokenId);
				if($token->getId()) {
					if(Mage::getSingleton('customer/session')->getCustomer()->getId() == $token->getCustomerId()) {
						if($token->getCustomName() != $customName) {
							$token->setCustomName($customName)->save();	
							$response = array('status' => 'updated');
						} else {
							$response = array('status' => 'ok');
						}
					}
				}
			}	
		}

		$this->getResponse()->setBody(json_encode($response));
	}

	public function updatePrimaryTokenAction() 
	{
		$response = array('status' => 'error');
		$this->getResponse()->setHeader('Content-type', 'text/json; charset=UTF-8');
		$tokenId = $this->getRequest()->getParam('token_id',false);

		if($tokenId) 
		{	
			if(Mage::getSingleton('customer/session')->isLoggedIn()) {
				
				$token = Mage::getModel('altapaypayment/token')->load($tokenId);
				if($token->getId()) {
					if(Mage::getSingleton('customer/session')->getCustomer()->getId() == $token->getCustomerId()) {
						try {
							$token->setPrimary(1)->save();
							
							$collection = Mage::getModel('altapaypayment/token')->getCollection()
								->addFieldToFilter('customer_id',$token->getCustomerId())
								->addFieldToFilter('id',array('neq' => $token->getId()))
								->addFieldToFilter('primary',1)
								;
							foreach($collection as $token) {
								$token->setPrimary(0)->save();
							}
							
							$response = array('status' => 'updated');
						} catch(Exception $e) {
							$response = array('status' => 'error');	
						}
					}
				}
			}	
		}
	
		$this->getResponse()->setBody(json_encode($response));
	}

	public function deleteTokenAction() 
	{
		$response = array('status' => 'error');
		$this->getResponse()->setHeader('Content-type', 'text/json; charset=UTF-8');
		$tokenId = $this->getRequest()->getParam('token_id',false);

		if($tokenId) 
		{	
			if(Mage::getSingleton('customer/session')->isLoggedIn()) {
	
				$token = Mage::getModel('altapaypayment/token')->load($tokenId);
				if($token->getId()) {
					if(Mage::getSingleton('customer/session')->getCustomer()->getId() == $token->getCustomerId()) {
						try {
							$token->setDeleted(1)->save();
							$response = array('status' => 'deleted');
						} catch(Exception $e) {
							$response = array('status' => 'error');	
						}
					}
				}
			}	
		}
	
		$this->getResponse()->setBody(json_encode($response));
	}

}
