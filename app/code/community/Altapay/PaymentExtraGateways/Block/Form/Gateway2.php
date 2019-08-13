<?php

class Altapay_PaymentExtraGateways_Block_Form_Gateway2 extends Mage_Payment_Block_Form_Cc
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('altapaypaymentextragateways/form/gateway2.phtml');
    }
    

}