<?php

class Altapay_Payment_Block_Info_Moto extends Mage_Payment_Block_Info_Cc
{
	protected function _construct()
    {
        parent::_construct();
		$this->setTemplate('altapay/payment/info/moto.phtml');
    }

    public function toPdf()
    {
        $this->setTemplate('altapay/payment/pdf/moto.phtml');
		return $this->toHtml();
    }
}