<?php

class Altapay_Payment_Block_Info_Gateway extends Mage_Payment_Block_Info_Cc
{
	protected function _construct()
    {
        parent::_construct();
		$this->setTemplate('altapay/payment/info/gateway.phtml');
    }

    public function toPdf()
    {
        $this->setTemplate('altapay/payment/pdf/gateway.phtml');
		return $this->toHtml();
    }
    
    
}