<?php

class Altapay_PaymentExtraGateways_Block_Form_Gateway3 extends Mage_Payment_Block_Form_Cc
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('altapaypaymentextragateways/form/gateway3.phtml');
    }
    

}