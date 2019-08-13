<?php

class Altapay_Payment_Block_Form_Token extends Mage_Payment_Block_Form_Cc
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('altapay/payment/form/token.phtml');
    }
    

}