<?php

class Altapay_Payment_Block_Info_Token extends Mage_Payment_Block_Info_Cc
{
	protected function _construct()
    {
        parent::_construct();
		$this->setTemplate('altapay/payment/info/token.phtml');
    }

    public function toPdf()
    {
        $this->setTemplate('altapay/payment/pdf/token.phtml');
		return $this->toHtml();
    }
    
    
}