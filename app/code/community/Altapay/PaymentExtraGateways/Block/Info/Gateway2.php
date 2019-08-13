<?php

class Altapay_PaymentExtraGateways_Block_Info_Gateway2 extends Mage_Payment_Block_Info_Cc
{
	protected function _construct()
    {
        parent::_construct();
		$this->setTemplate('altapaypaymentextragateways/info/gateway2.phtml');
    }

    public function toPdf()
    {
        $this->setTemplate('altapaypaymentextragateways/pdf/gateway2.phtml');
		return $this->toHtml();
    }
    
    
}