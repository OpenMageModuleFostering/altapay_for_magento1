<?php

if(!defined('ALTAPAY_API_ROOT'))
{
	define('ALTAPAY_API_ROOT',Mage::getModuleDir(null,'Altapay_Payment').'/Model/Api');
}
require_once(ALTAPAY_API_ROOT.'/AltapayMerchantAPI.class.php');

abstract class Altapay_Payment_Model_Method_Abstract extends Mage_Payment_Model_Method_Abstract {

	protected function getAltapayModel(Varien_Object $payment = null)
	{
		return new Altapay_Payment_Model_Altapay($this->getStoreId($payment));
	}

	protected function getStoreId(Varien_Object $payment = null)
	{
		if(is_null($payment))
		{
			return Altapay_Payment_Helper_Utilities::guessStoreIdBasedOnParameters();
		}
		else
		{
			return $payment->getOrder()->getStore()->getId();
		}
	}

	protected function getAltapayPaymentType($configPath, $storeId = null)
	{
		$type = Mage::getStoreConfig($configPath, $storeId);
		if($type == Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE){
			return Altapay_Payment_Model_Constants::ACTION_AUTHORIZE;
		}else{
			return Altapay_Payment_Model_Constants::ACTION_AUTHORIZE_CAPTURE;
		}
	}

	/**
	 * (non-PHPdoc)
	 * @see code/core/Mage/Payment/Model/Method/Mage_Payment_Model_Method_Abstract#capture()
	 */
	public function capture(Varien_Object $payment, $amount) {
		$session_authTransactionId = Mage::getSingleton('core/session')->getData('altapay_auth_transaction_id');
		Mage::getSingleton('core/session')->unsetData('altapay_auth_transaction_id');
		$session_requiresCapture = Mage::getSingleton('core/session')->getData('altapay_requires_capture');
		Mage::getSingleton('core/session')->unsetData('altapay_requires_capture');
		$paymentStatus = Mage::getSingleton('core/session')->getData('altapay_payment_status');
		Mage::getSingleton('core/session')->unsetData('altapay_payment_status');

		if ($session_requiresCapture == 'false')
		{
			/**
			 * Some ePayments (e.g. Sofort/DirectEbanking) do captures immediately,
			 * and we therefore do not need to do a separate capture.
			 * In order to refund them we need to set the transaction id, as we would
			 * normally do later in the capture process.
			 */
			if ($paymentStatus == 'bank_payment_finalized' || $paymentStatus == 'captured')
			{
				$payment->setIsTransactionClosed(false);
				$payment->setTransactionId($session_authTransactionId);
			}

			// payment has already been captured by Altapay
			return $this;
		}

		/* @var $payment Mage_Sales_Model_Order_Payment */
		$invoice = $this->_getInvoice($payment);

		$invoiceData = $invoice->__toArray();
		$salesTax = number_format($invoiceData['tax_amount'], 2,'.','');
		$amount = number_format($invoiceData['grand_total'], 2,'.',''); // To get the amount in the correct currency

		$orderLines = $this->_createOrderLinesFromInvoice($invoice);

		$authTrans = $payment->getAuthorizationTransaction();

		if (empty($authTrans))
		{
			/**
			 * We hit this case when our OnepageController->successAction method is run. During the
			 * execution the quote is stored, but while saving, this method is called and that means
			 * that we will never find the authorization transaction (because it has not been saved yet).
			 */
			$doCapture = true;
			$transaction_id = $session_authTransactionId;
		}
		else
		{
			$transaction_id = $authTrans->getTxnId();
			$authResponse = @unserialize($authTrans->getAdditionalInformation('altapay_response'));
			$doCapture = $authResponse === false || $authResponse->getPrimaryPayment()->mustBeCaptured();
		}

		// Find out if we need to capture this payment
		if($doCapture)
		{
			$response = $this->getAltapayModel($payment)->captureReservation($transaction_id, $amount, $orderLines, $salesTax);
			$payment->setTransactionAdditionalInfo('altapay_response', serialize($response));

			if($response->wasSuccessful())
			{
				$payment->setIsTransactionClosed(false);
				$payment->setTransactionId($response->getPrimaryPayment()->getLastReconciliationIdentifier()->getId());

				return $this;
			}
			else
			{
				Mage::throwException($response->getMerchantErrorMessage());
			}
		}
		else
		{
			$payment->setIsTransactionClosed(false);
			$payment->setTransactionId($authTrans->getTxnId().'-captured');

			return $this;
		}
	}

	protected function _createOrderLinesFromInvoice(Mage_Sales_Model_Order_Invoice $invoice)
	{
		$orderLines = array();
		foreach($invoice->getAllItems() as $item) /* @var $item Mage_Sales_Model_Order_Invoice_Item */
		{
			$data = $item->__toArray();

			$orderLines[] = array(
				'description'=>$data['name'],
				'itemId'=>$data['sku'],
				'quantity'=>$data['qty'],
				'taxAmount'=>number_format(bcsub($data['price_incl_tax'], $data['price'], 2), 2, '.', ''),
				'unitCode'=>'pcs', // TODO: Nice this up
				'unitPrice'=>round($data['price'], 2, PHP_ROUND_HALF_DOWN),
				'discount'=>0, // There is no such thing on each row, only a total on the invoice....
				'goodsType'=>'item',
			);
		}

		$shipping = $invoice->__toArray();
		if($shipping['shipping_amount'] > 0)
		{
			$orderLines[] = array(
				'description'=>$invoice->getOrder()->getData('shipping_description'),
				'itemId'=>$invoice->getOrder()->getData('shipping_method'),
				'quantity'=>1,
				'taxAmount'=>0,
				'unitCode'=>'pcs', // TODO: Nice this up
				'unitPrice'=>$invoice->getData('shipping_amount'),
				'discount'=>0,
				'goodsType'=>'shipment',
			);
		}
		return $orderLines;
	}


	/**
	 * This will dig out the invoice that we are paying for right now As Magento does not tell us we have
	 * to investigate the invoices and make a qualified guess (as this is the best we can do).
	 *
	 * @return Mage_Sales_Model_Order_Invoice
	 */
	private function _getInvoice(Mage_Sales_Model_Order_Payment $payment)
	{
		$invoice = Altapay_Payment_Model_Observer::getInvoiceBeingPayedFor();
		if(is_null($invoice))
		{
			Mage::throwException("Could not find the invoice for which the payment is being made");
		}
		return $invoice;
	}

	/**
	 * (non-PHPdoc)
	 * @see code/core/Mage/Payment/Model/Method/Mage_Payment_Model_Method_Abstract#refund()
	 */
	public function refund(Varien_Object $payment, $amount) {
		/* @var $payment Mage_Sales_Model_Order_Payment */

		/**
		 * @var $creditmemo Mage_Sales_Model_Order_Creditmemo
		 */
		$creditmemo = $payment->getCreditmemo();

		$creditmemoData = $creditmemo->__toArray();
		$amount = number_format($creditmemoData['grand_total'], 2,'.',''); // To get the amount in the correct currency

		/**
		 * @var $items Mage_Sales_Model_Order_Creditmemo_Item[]
		 */
		$items = $creditmemo->getAllItems();

		$orderLines = array();
		foreach ($items as $item)
		{
			$data = $item->__toArray();

			$orderLines[] = array(
				'description'=>$data['name'],
				'itemId'=>$data['sku'],
				'quantity'=>$data['qty'],
				'unitPrice'=>round($data['price'], 2, PHP_ROUND_HALF_DOWN),
			);
		}

		/**
		 * @var $collection Mage_Sales_Model_Mysql4_Order_Payment_Transaction_Collection
		 *
		 * $payment->getAuthorizationTransaction() returns the capture transaction
		 * and if we use the transaction id from that, the refund fails in magento
		 */
		$collection = Mage::getModel('sales/order_payment_transaction')->getCollection()
			->setOrderFilter($payment->getOrder())
			->addPaymentIdFilter($payment->getId())
			->addTxnTypeFilter(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);

		if (count($collection) < 1)
		{
			throw Mage::exception('Altapay - refund', 'I was unable to find any authorization transactions for order '.$payment->getOrder()->getId());
		}

		if (count($collection) > 1)
		{
			throw Mage::exception('Altapay - refund', 'I found multiple authorization transactions for order '.$payment->getOrder()->getId().'. I do not know which one to use.');
		}

		$authTrans = $collection->getFirstItem();

		$transaction_id = $authTrans->getTxnId();

		$response = $this->getAltapayModel($payment)->refundCapturedReservation($transaction_id, $amount, $orderLines);
		$payment->setTransactionAdditionalInfo('altapay_response', serialize($response));

		if($response->wasSuccessful())
		{
			$payment->setShouldCloseParentTransaction(false);
			$payment->setTransactionId($response->getPrimaryPayment()->getLastReconciliationIdentifier()->getId());

			return $this;
		}
		else
		{
			Mage::throwException($response->getMerchantErrorMessage());
		}
	}

	/**
	 * This will dig out the creditmemo that we are refunding for right now As Magento does not tell us we have
	 * to investigate the credit memos and make a qualified guess (as this is the best we can do).
	 *
	 * @return Mage_Sales_Model_Order_Creditmemo
	 */
	private function _getCreditmemo(Mage_Sales_Model_Order_Payment $payment)
	{
		$creditmemo = Altapay_Payment_Model_Observer::getCreditmemoBeingRefunded();
		if(is_null($creditmemo))
		{
			Mage::throwException("Could not find the creditmemo for which the refund is being made");
		}
		return $creditmemo;
	}


	/**
	 * Void is in regards to the payment on the order invoice - to void the authorization, for instance - so that the
	 * funds aren't subsequently captured. Payments have to be refunded after capture and cannot be voided.
	 *
	 * (non-PHPdoc)
	 * @see code/core/Mage/Payment/Model/Method/Mage_Payment_Model_Method_Abstract#void()
	 * @see http://magento.stackexchange.com/questions/7271/whats-the-difference-between-voiding-and-canceling-an-order
	 */
	public function void(Varien_Object $payment)
	{
		$authTrans = $payment->getAuthorizationTransaction();
		$transaction_id = $authTrans->getTxnId();

		$response = $this->getAltapayModel($payment)->releaseReservation($transaction_id);

		$payment->setTransactionAdditionalInfo('altapay_response', serialize($response));

		$payment->setIsTransactionClosed(false);
		$payment->setTransactionId($transaction_id.'-void');

		if($response->wasSuccessful())
		{
			return $this;
		}
		else
		{
			Mage::throwException($response->getMerchantErrorMessage());
		}
	}
}