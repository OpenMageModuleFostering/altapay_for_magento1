<?php

class Altapay_Payment_Helper_Utilities
{
	
	public static function guessStoreIdBasedOnParameters()
	{
		$storeCode = Mage::app()->getFrontController()->getRequest()->getParam('store', null);
		if(is_null($storeCode))
		{
			$storeCode = Mage::app()->getFrontController()->getRequest()->getParam('store_id', null);
		}
		
		return $storeCode;
	}
}