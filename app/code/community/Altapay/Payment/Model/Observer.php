<?php

class Altapay_Payment_Model_Observer
{
	private static $invoiceBeingPayedFor;
	
	/**
	 * @param Varien_Event_Observer $event
	 */
	public function salesOrderPaymentCapture($event)
	{
		self::$invoiceBeingPayedFor = $event->getInvoice();
	}
	
	/**
	 * Since Magento does not tell our payment "method" what invoices is being payed for
	 * we need to make this "hack". The payment it self knows the invoice but the information
	 * is not send into the "method".
	 * 
	 * @return Mage_Sales_Model_Order_Invoice
	 */
	public static function getInvoiceBeingPayedFor()
	{
		return self::$invoiceBeingPayedFor;
	}
}