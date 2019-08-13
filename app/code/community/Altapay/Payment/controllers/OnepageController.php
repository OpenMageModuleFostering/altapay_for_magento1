<?php

require_once 'Mage/Checkout/controllers/OnepageController.php';
require_once(dirname(__FILE__).'/../Model/Api/AltapayMerchantAPI.class.php');


class Altapay_Payment_OnepageController extends Mage_Checkout_OnepageController {

	public function failureAction() {

		$errorMessage = 'Internal error';
		$paymentScheme = 'N/A';

		try
		{
			if ($this->getRequest()->has('xml'))
			{
				$reservationResponse = new AltapayReservationResponse(new SimpleXmlElement($this->getRequest()->getParam('xml')));

				$errorMessage = $reservationResponse->getCardHolderErrorMessage();

				$payments = $reservationResponse->getPayments();
				if (!is_null($payments) && count($payments) > 0)
				{
					$paymentScheme = $payments[0]->getPaymentSchemeName();
				}
			}
		}
		catch (Exception $ex)
		{
			// do something?
		}
        Mage::getSingleton('core/session')->setData('altapay_error_message', $errorMessage);
		Mage::getSingleton('core/session')->setData('altapay_payment_scheme_name', $paymentScheme);

		$this->loadLayout();
		$this->renderLayout();
	}

	public function formAction() {
		$this->loadLayout();
		$this->getLayout()->getBlock('head')->setTitle($this->__('Checkout'));
		$this->renderLayout();
	}

	/**
	 * This method is called by Altapay, and the result will be displayed to the customer.
	 */
	public function successAction()
	{

		$reservationResponse = new AltapayReservationResponse(new SimpleXmlElement($this->getRequest()->getParam('xml')));

		/**
		 * Store the authorization transaction id in the session, in order
		 * for it to be available in Payment->capture (which is called by Magento
		 * when saving the quote)
		 */
		Mage::getSingleton('core/session')->setData('altapay_auth_transaction_id', $reservationResponse->getPrimaryPayment()->getId());
		Mage::getSingleton('core/session')->setData('altapay_requires_capture', $this->getRequest()->getParam('require_capture'));
		Mage::getSingleton('core/session')->setData('altapay_payment_status', $this->getRequest()->getParam('payment_status'));

        $reservationAmount = $this->extractPriceFromXML($reservationResponse->getXml());

		$amountIsMathing = false;
		if($reservationAmount == $this->getQuoteAmount() || $this->getQuoteAmount() == 0) {
			$amountIsMathing = true;
		}

        if ($reservationResponse->wasSuccessful() && $amountIsMathing) {
            if($this->isFraudcheckEnabled($reservationResponse)){
                switch ($reservationResponse->getPrimaryPayment()->getFraudRecommendation()) {
                    case 'Deny':
                        $this->handleTransactionRejection($reservationResponse);
                        break;
                    case 'Unknown':
                        //Intentional fall through
                    case 'Challenge': //There is to handle challange cases.
                        //Intentional fall through
                    case 'Accept':
                    default:
                        //Intentional fall through
                        $this->storeOrderAndPayment($reservationResponse, 'success');
                        break;
                }
            }
            else{
                $this->storeOrderAndPayment($reservationResponse, 'success');
            }
        }
        elseif ($reservationResponse->wasSuccessful() && !$amountIsMathing) {
            $this->handleTransactionRejection($reservationResponse);
        }
		else {
			$this->storeOrderAndPayment($reservationResponse, 'failed');
		}
	}

	/**
	 * This method is called by Altapay, and the result will be displayed to the customer.
	 */
	public function openAction()
	{
		$reservationResponse = new AltapayReservationResponse(new SimpleXmlElement($this->getRequest()->getParam('xml')));

		$this->storeOrderAndPayment($reservationResponse, 'open');
	}

	/**
	 * This method is called by Altapay's gateway without the user being there to see the result.
	 * For this reason we print out some things which will be visible in the logs in Altapay.
	 */
	public function notificationAction()
	{
		$reservationResponse = new AltapayReservationResponse(new SimpleXmlElement($this->getRequest()->getParam('xml')));

		// Find the order
		$orderId = $reservationResponse->getPrimaryPayment()->getShopOrderId();

		//Mage_Checkout_Model_Session::getQuote();

		$order = $this->_loadOrder($orderId);

		$status = $this->getRequest()->getParam('status');
		if(!is_null($order))
		{
			print("OrderState: ".$order->getState()."\n");
		}
		else
		{
			$merchantErrorMessage = $this->getRequest()->getPost('merchant_error_message', '');

			if ($merchantErrorMessage == 'Declined')
			{
				// we are ok with not finding the payment
				return;
			}

			$quote = Mage::getModel('sales/quote')->load($reservationResponse->getPrimaryPayment()->getShopOrderId(), 'reserved_order_id');

			$this->getOnePage()->setQuote($quote);

			if($status == 'success' || $status == 'succeeded')
			{
				// create an order etc.
				$this->successAction();
				$order = $this->_loadOrder($orderId);
			}
			else
			{
				print("Could not find order: ".$orderId);
				throw new Exception("Could not find order: ".$orderId);
			}
		}

		// Handle the actual notification
		if($status == 'success' || $status == 'succeeded')
		{
			if($order->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT)
			{
				if(bccomp($reservationResponse->getPrimaryPayment()->getReservedAmount(), $order->getTotalDue(), 2) == 0)
				{
					// The notification is a payment-notification
					$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, 'The payment is no longer "open" at Altapay, we can start processing the order', false);

					if ($this->getRequest()->getParam('payment_status') == 'bank_payment_finalized')
					{
						$this->saveNotificationTransactionForEPayments($order, $reservationResponse); // Only for ePayments
					}

				}
				else
				{
					print('The payment is most likely still "open" because the reserved amount and the amount due do not match: '.$reservationResponse->getPrimaryPayment()->getReservedAmount().' and '.$order->getTotalDue()."\n");
					$order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true, 'The payment is most likely still "open" because the reserved amount and the amount due do not match: '.$reservationResponse->getPrimaryPayment()->getReservedAmount().' and '.$order->getTotalDue(), false);
				}
				$order->save();
			}
			else if($order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING)
			{
				print("The order is already processing, hmm..\n");
			}
			else
			{
				print("Status was success/succeeded, but the order state was unexpected:".$order->getState()."\n");
			}
		}
		else if($status == 'failed')
		{
			print("status is failed\n");

			/**
			 * Cancel the order if it is
			 * pending
			 * _or_
			 * is a paypal payment (as those guys can make all sorts of crazy jumps in state)
			 *
			 * Keeping it narrow for the time being, as we do not know the consequences of changing
			 * the state on the order.
			 */
			if($order->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT || $reservationResponse->getPrimaryPayment()->getPaymentSchemeName() == 'PayPal')
			{
				print("cancelling order\n");
				// Cancel the order (as the payment was declined)
				$order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'The payment was declined', false);
				$order->save();
			}
		}
		else
		{
			print("An unknown notification was sent: ".$status);
			throw new Exception("An unknown notification was sent: ".$status);
		}

	}

	/**
	 * This method is called when the user chooses to use an already existing subscription.
	 *
	 * We should:
	 *  - Find the subscription and verify that it belongs to the user.
	 *  - Attempt to make a recurring preauth/capture to cover the order.
	 */
	public function recurringPaymentAction()
	{
		$params = $this->getRequest()->getParams();

		if(!isset($params['subscription_id']) || $params['subscription_id'] == 'new')
		{
			Mage::getSingleton('checkout/session')->setErrorMessage("No subscription provided");
			$this->_redirect('checkout/onepage/failure');
		}
		else
		{
			$subscription = Mage::getModel('altapaypayment/subscription')->load($params['subscription_id']);
			if($subscription->getCustomerId() != $this->getCustomer()->getId())
			{
				Mage::getSingleton('checkout/session')->setErrorMessage("This subscription does not belong to you");
				$this->_redirect('checkout/onepage/failure');
			}

			if($subscription->getCurrencyCode() != $this->getOnepage()->getQuote()->getQuoteCurrencyCode())
			{
				Mage::getSingleton('checkout/session')->setErrorMessage("This subscription is in the wrong currency");
				$this->_redirect('checkout/onepage/failure');
			}
			else
			{
				$this->processRecurringPayment($subscription);
			}
		}
	}

	/**
	 * This method is called when we have successfully made a subscription.
	 *
	 * We should:
	 *  - Store the subscription for later use
	 *  - Attempt to make a recurring preauth/capture to cover the order.
	 */
	public function recurringSuccessAction()
	{
		// Store the subscription
		$subscription = $this->storeSubscription($this->getRequest()->getParam('xml'));

		$this->processRecurringPayment($subscription);
	}

	private function storeOrderAndPayment($reservationResponse, $successType='success')
	{
		
		if(!$this->getOnepage()->getQuote()){
			Mage::log($_SERVER['REMOTE_ADDR'].': no quote',null,'altapay.log',true);
			// Redirect to success page
			$this->_redirect('checkout/onepage/success');
	
			// Render Meta-Redirect success page (could be skipped)
			$this->loadLayout();
			$this->_initLayoutMessages('checkout/session');
			$this->renderLayout();
			return;
		}
				
		if(!$this->getOnepage()->getQuote()->getIsActive()){
			Mage::log($_SERVER['REMOTE_ADDR'].': quote not active',null,'altapay.log',true);
			// Redirect to success page
			$this->_redirect('checkout/onepage/success');
	
			// Render Meta-Redirect success page (could be skipped)
			$this->loadLayout();
			$this->_initLayoutMessages('checkout/session');
			$this->renderLayout();
			return;
		}
		
		$checkoutSessionId = $this->getOnepage()->getCheckout()->getSessionId();
		
		try{
			// Clear the basket and save the order (including some info about how the payment went)
			$this->getOnepage()->getQuote()->collectTotals();
			$this->getOnepage()->getQuote()->getPayment()->setAdditionalInformation('successType', $successType);
			$orderId = $this->getOnepage()->saveOrder()->getLastOrderId();
			$this->getOnepage()->getQuote()->save();
		}
		catch(Exception $e){
			Mage::log($_SERVER['REMOTE_ADDR'].': exception: '.$e->getMessage(),null,'altapay.log',true);
			// Redirect to success page
			$this->_redirect('checkout/onepage/success');
	
			// Render Meta-Redirect success page (could be skipped)
			$this->loadLayout();
			$this->_initLayoutMessages('checkout/session');
			$this->renderLayout();
			return;
		}

		// Store the information from Altapay
		{
			$order = $this->_loadOrder($orderId);

			$payment = $order->getPayment();
			$payment->setTransactionId($reservationResponse->getPrimaryPayment()->getId());
			if($reservationResponse->getPrimaryPayment()->getPaymentNature() == 'CreditCard')
			{
				$payment->setData('cc_last4', substr('****'.$reservationResponse->getPrimaryPayment()->getMaskedPan(), -4));
				$payment->setData('cc_number_enc', $reservationResponse->getPrimaryPayment()->getMaskedPan());
				$payment->setData('cc_trans_id', $reservationResponse->getPrimaryPayment()->getId());
			}
			$payment->setAdditionalData(serialize($reservationResponse));
			$payment->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $this->getRequest()->getParams());

			$payment->save();

			$transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);
			$transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $this->getRequest()->getParams());
			$transaction->setAdditionalInformation('altapay_response', serialize($reservationResponse));
			$transaction->setTxnId($reservationResponse->getPrimaryPayment()->getId());
			$transaction->setIsClosed(false);
			$transaction->save();

			if($successType == 'open')
			{
				$order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true, 'The payment is "open" at Altapay, we must wait for a notification before it will be processing', false);
				$order->save();
			}
			elseif ($successType == 'failed')
			{
				// Cancel the order (as the payment was declined)
				$order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'The payment was declined', false);
				$order->save();
			}
		}
		
		if($checkoutSessionId != $this->getOnepage()->getCheckout()->getSessionId()) {
			$this->_redirect('altapaypayment/onepage/customerCreated',array('success' => $this->getOnepage()->getCheckout()->getSessionId()));
		} else {
			// Redirect to success page
			$this->_redirect('checkout/onepage/success');
		}
		// Render Meta-Redirect success page (could be skipped)
		$this->loadLayout();
		$this->_initLayoutMessages('checkout/session');
		$this->renderLayout();
	}
	
	public function customerCreatedAction() {
		
		$session = Mage::getSingleton('checkout/session');
		$sessionId = $this->getRequest()->getParam('success',false);
		if($session->getSessionId() != $sessionId) {			
			$session->getCookie()->set($session->getSessionName(),$sessionId);
		}
		$this->_redirect('checkout/onepage/success');
	}
	

	private function processRecurringPayment($subscription)
	{
		$totals         = $this->getOnePage()->getQuote()->getTotals();
		$grandTotal     = $totals['grand_total']->getValue();
		$amount	        = number_format($grandTotal, 2,'.','');

		$storeId = $this->getOnePage()->getQuote()->getStoreId();
		$altapayModel = new Altapay_Payment_Model_Altapay($storeId);
		$reservationResponse = $altapayModel->recurringReservation($subscription->getSubscriptionId(), $amount);
		
		if($reservationResponse->wasSuccessful())
		{
			$this->storeOrderAndPayment($reservationResponse, 'success');
		}
		else
		{
			Mage::getSingleton('checkout/session')->setErrorMessage($reservationResponse->getCardHolderErrorMessage());
			$this->_redirect('checkout/onepage/failure');
		}
	}

	private function storeSubscription($xml)
	{
		$subscriptionResponse = new AltapayReservationResponse(new SimpleXmlElement($xml));

		$currencyMapper = new Altapay_Payment_Helper_CurrencyMapper();

		/* @var Mage_Customer_Model_Customer $customer */
		$customer = $this->getOnepage()->getCustomerSession()->getCustomer();
		$subscription = Mage::getModel('altapaypayment/subscription');
		$subscription->setSubscriptionId($subscriptionResponse->getPrimaryPayment()->getId());
		$subscription->setCustomerId($customer->getId());
		$subscription->setMaskedPan($subscriptionResponse->getPrimaryPayment()->getMaskedPan());
		$subscription->setCardToken($subscriptionResponse->getPrimaryPayment()->getCreditCardToken());
		$subscription->setCurrencyCode($currencyMapper->getAlpha($subscriptionResponse->getPrimaryPayment()->getCurrency()));
		$subscription->save();

		return $subscription;
	}

	/**
	 * @return Mage_Sales_Model_Order
	 **/
	private function _loadOrder($orderId)
	{
		$order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
		if($order->getIncrementId() == $orderId)
		{
			return $order;
		}
		return null;
	}

	/**
	 * @return Mage_Customer_Model_Customer
	 */
	private function getCustomer()
	{
		return $this->getOnepage()->getCustomerSession()->getCustomer();
	}

    private function getQuoteAmount()
    {
        $totals         = $this->getOnePage()->getQuote()->getTotals();
        $grandTotal     = $totals['grand_total']->getValue();
        $amount	        = number_format($grandTotal, 2,'.','');
        return $amount;
    }

    private function extractPriceFromXML($xml)
    {
        $simpleXML = new SimpleXmlElement($xml);
        $total = $simpleXML->Body->Transactions[0]->Transaction->ReservedAmount;
        $surcharge = $simpleXML->Body->Transactions[0]->Transaction->SurchargeAmount;
        return bcsub($total, $surcharge, 2);
    }

    private function releasePayment($paymentID)
    {
        try{
            $altapayAPI = $this->getAltapayAPI();
            $altapayAPI->releaseReservation($paymentID);
        }
        catch(Exception $ex){
            //We tried
        }
    }

    private function refundPayment($paymentID)
    {
        try{
            $altapayAPI = $this->getAltapayAPI();
            $altapayAPI->refundCapturedReservation($paymentID);
        }
        catch(Exception $ex){
            //We tried
        }
    }

    private function getAltapayAPI()
    {
        $baseURL = Mage::getStoreConfig(Altapay_Payment_Model_Constants::CONF_PATH_API_INSTALLATION);
        $username = Mage::getStoreConfig(Altapay_Payment_Model_Constants::CONF_PATH_API_USERNAME);
        $password = Mage::getStoreConfig(Altapay_Payment_Model_Constants::CONF_PATH_API_PASSWORD);
        return new AltapayMerchantAPI($baseURL, $username, $password);
    }
    private function isReservation($paymentStatus){
        if($paymentStatus == 'preauth'){
            return true;
        }
        elseif($paymentStatus == 'invoice_initialized'){
            return true;
        }
        return false;
    }

    /**
     * @param AltapayReservationResponse $reservationResponse
     */
    private function isFraudcheckEnabled($reservationResponse)
    {
        try{
            if($reservationResponse->getPrimaryPayment()->getFraudRecommendation() != null) {
                return true;
            }
        }
        catch(Exception $ex){
            //I would assume that there is no fraud detection available.
        }
        return false;
    }

    /**
     * @param AltapayReservationResponse $reservationResponse
     */
    private function handleTransactionRejection($reservationResponse)
    {
        if($this->isReservation($this->getRequest()->getParam('payment_status')))
        {
            $this->releasePayment($reservationResponse->getPrimaryPayment()->getId());
        }
        else{
            $this->refundPayment($reservationResponse->getPrimaryPayment()->getId());
        }
        $this->_redirect('altapaypayment/onepage/failure?orderID=' . $reservationResponse->getPrimaryPayment()->getShopOrderId());
    }

	/**
	 * @param $order
	 * @param $reservationResponse
	 * @throws Exception
	 */
	private function saveNotificationTransactionForEPayments($order, $reservationResponse)
	{
		/* @var $payment Mage_Sales_Model_Order_Payment */
		$payment = $order->getPayment();

		$authTrans = $payment->getAuthorizationTransaction();

		// Updates the existing authorization transaction. This will allow the capture of the payment.
		$authTrans->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $this->getRequest()->getParams());
		$authTrans->setAdditionalInformation('altapay_response', serialize($reservationResponse));

		$authTrans->save();
	}
}
