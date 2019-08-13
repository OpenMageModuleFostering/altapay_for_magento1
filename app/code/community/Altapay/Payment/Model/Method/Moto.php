<?php

class Altapay_Payment_Model_Method_Moto extends Altapay_Payment_Model_Method_Abstract {
	protected $_canAuthorize = true;
	protected $_canSaveCc = false;
	protected $_isGateway = false;
	protected $_canOrder = false;
	protected $_canCapture = true;
	protected $_canCapturePartial = true;
	protected $_canRefund = true;
	protected $_canRefundInvoicePartial = true;
	protected $_canVoid = true;
	protected $_canUseInternal = true;
	protected $_canUseCheckout = false;
	protected $_canUseForMultishipping = true;
	protected $_isInitializeNeeded = false;
	protected $_canFetchTransactionInfo = true;
	protected $_canReviewPayment = true;
	protected $_canCreateBillingAgreement = false;
	protected $_canManageRecurringProfiles = false;
	protected $_code = 'altapay_moto';
	protected $_formBlockType = 'altapaypayment/form_moto';
	protected $_infoBlockType = 'altapaypayment/info_moto';
	
	private function _getTerminalTitle(Varien_Object $payment = null) {
		return Mage::getStoreConfig(Altapay_Payment_Model_Constants::CONF_PATH_MOTO_TERMINAL, $this->getStoreId($payment));
	}

	public function authorize(Varien_Object $payment, $amount) {
		parent::authorize($payment, $amount);
		
		$order = $payment->getOrder();
		
		// Collect the Credit Card Values:
		$expiry_month = Mage::Helper('altapaypayment')->getExpiryMonth($payment->getCcExpMonth());
		$expiry_year = $payment->getCcExpYear();
		$shop_orderid = $order->getIncrementId();
		$currency = $order->getBaseCurrencyCode();
		$cardnum = $payment->getCcNumber();
		$cvc = $payment->getCcCid();
		$type = 'payment';
		
		$response = $this->getAltapayModel($payment)->reservationOfFixedAmountMOTO(
			  $this->_getTerminalTitle($payment)
			, $shop_orderid
			, $amount
			, $currency
			, $cardnum
			, $expiry_year
			, $expiry_month
			, $cvc
		);
		
		if($response->wasSuccessful())
		{
			$payment->setIsTransactionClosed(false);
			$payment->setTransactionId($response->getPrimaryPayment()->getId());
			$maskedPan = $response->getPrimaryPayment()->getMaskedPan();
			$payment->setData('cc_last4', substr('****'.$maskedPan, -4));
			$payment->setData('cc_number_enc', $maskedPan);
			
			return $this;
		}
		else
		{
			Mage::throwException($response->getMerchantErrorMessage());
		}
	}
	
	public function canUseForCurrency($currencyCode)
	{
		$terminals = new Altapay_Payment_Model_Source_Terminals();
		return $terminals->canUseForCurrency($this->_getTerminalTitle(), $currencyCode);
	}
}