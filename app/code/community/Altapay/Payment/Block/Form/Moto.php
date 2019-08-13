<?php

class Altapay_Payment_Block_Form_Moto extends Mage_Payment_Block_Form_Cc
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('altapay/payment/form/moto.phtml');
    }
    
	/**
     * Verification (CCV) has to display if either been set to use in admin OR
     * request action is set to preauth (note: documentation is wrong as it states it is optional)
     *
     * @return boolean
     */
    public function hasVerification()
    {
        if(parent::hasVerification()){
        	return true;
        } else {
        	//check payment action
        	$method = $this->getMethod();
        	$request_action = $method->getConfigPaymentAction();
        	if($request_action == Altapay_Payment_Model_Method_Abstract::ACTION_AUTHORIZE) {
        		return true;
        	}
        }
        return false;
    }

}