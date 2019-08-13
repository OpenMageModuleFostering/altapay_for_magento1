<?php

class Altapay_Payment_Model_Method_Recurring extends Altapay_Payment_Model_Method_Gateway {

	protected $_canAuthorize = true;
	protected $_canSaveCc = true;
	protected $_isGateway = true;
	protected $_canOrder = false;
	protected $_canCapture = true;
	protected $_canCapturePartial = true;
	protected $_canRefund = true;
	protected $_canRefundInvoicePartial = true;
	protected $_canVoid = true;
	protected $_canUseInternal = false; // Required to take MO/TO transaction
	protected $_canUseCheckout = true;
	protected $_canUseForMultishipping = true;
	protected $_isInitializeNeeded = false;
	protected $_canFetchTransactionInfo = true;
	protected $_canReviewPayment = true;
	protected $_canCreateBillingAgreement = false;
	protected $_canManageRecurringProfiles = false;
	protected $_code = 'altapay_recurring';
	protected $_formBlockType = 'altapaypayment/form_recurring';
	protected $_infoBlockType = 'altapaypayment/info_recurring';

	protected function getAltapayPaymentType($configPath, $storeId = null)
	{
		return Altapay_Payment_Model_Constants::ACTION_RECURRING;
	}
	
	public function isAvailable($quote = null)
	{
		return parent::isAvailable($quote) && !in_array($quote->getCheckoutMethod(),array(
			//Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER
			Mage_Checkout_Model_Type_Onepage::METHOD_GUEST
			));
		//return parent::isAvailable($quote) && !is_null($this->getCustomer()) && !is_null($this->getCustomer()->getId());
	}
	
	/**
	 * Either we will setup a new subscription - using the same mechanism as the "Gateway" method.
	 * Or we will redirect to our controller to take the payment from an existing subscription.
	 */
	public function getCheckoutRedirectUrl()
	{
		$params = Mage::app()->getFrontController()->getRequest()->getParams();
		
		if(!isset($params['subscription_id']) || $params['subscription_id'] == 'new')
		{
			return parent::getCheckoutRedirectUrl();
		}
		else
		{
			// Get the subscription
			$subscription = Mage::getModel('altapaypayment/subscription')->load($params['subscription_id']);
			if($subscription->getCustomerId() != $this->getCustomer()->getId())
			{
				Mage::throwException("This subscription does not belong to you");
			}
			
			return Mage::getUrl('altapaypayment/onepage/recurringPayment?subscription_id='.$subscription->getId());
		}
	}
	
	/**
	 * @return Mage_Customer_Model_Customer
	 */
	private function getCustomer()
	{
		return $this->getOnepage()->getCustomerSession()->getCustomer();
	}
	
	protected function getAltapayRequestConfig($orderId)
	{
		return array(
				'callback_form' => Mage::getUrl('altapaypayment/onepage/form')
				, 'callback_ok' => Mage::getUrl('altapaypayment/onepage/recurringSuccess')
				, 'callback_fail' => Mage::getUrl('altapaypayment/onepage/failure')
				, 'callback_redirect' => ''
				, 'callback_open' => ''
				, 'callback_notification' => ''
		);
	}
	
	protected function getAltapayTerminal()
	{
		return Mage::getStoreConfig(Altapay_Payment_Model_Constants::CONF_PATH_RECURRING_TERMINAL);
	}
	
}