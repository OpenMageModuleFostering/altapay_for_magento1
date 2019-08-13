<?php

class Altapay_Payment_Helper_Logger implements IAltapayCommunicationLogger
{
	
	public function logRequest($message)
	{
		$logId = md5($message.microtime());
		
		Mage::log('[Request: '.$logId.']'.$message, Zend_Log::INFO, 'altapay.log', true);
		
		return $logId;
	}
	
	public function logResponse($logId, $message)
	{
		Mage::log('[Response:'.$logId.']'.$message, Zend_Log::INFO, 'altapay.log', true);
	}
}