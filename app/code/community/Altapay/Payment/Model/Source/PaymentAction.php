<?php
/**
 *
 * Payment Action Dropdown source
 *
 * @author Emanuel Holm Greisen <eg@altapay.com>
 */
class Altapay_Payment_Model_Source_PaymentAction
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE,
                'label' => Mage::helper('altapaypayment')->__('Authorize Only')
            ),
            array(
                'value' => Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE,
                'label' => Mage::helper('altapaypayment')->__('Authorize and Capture')
            ),
        );
    }
}
