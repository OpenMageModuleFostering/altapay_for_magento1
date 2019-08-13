<?php

class Altapay_Payment_Model_Constants {
	// configuration paths
	const CONF_PATH_API_INSTALLATION = 'altapay_general/api_installation';
	const CONF_PATH_API_USERNAME     = 'altapay_general/api_username';
	const CONF_PATH_API_PASSWORD     = 'altapay_general/api_password';
	
	const CONF_PATH_MOTO_TERMINAL    = 'payment/altapay_moto/terminal';
	
	const CONF_PATH_GATEWAY_TERMINAL    = 'payment/altapay_gateway/terminal';
	const CONT_PATH_GATEWAY_ACTION_TYPE = 'payment/altapay_gateway/payment_action';
	
	const CONF_PATH_TOKEN_TERMINAL    = 'payment/altapay_token/terminal';
	const CONT_PATH_TOKEN_ACTION_TYPE = 'payment/altapay_token/payment_action';

	const CONF_PATH_RECURRING_TERMINAL    = 'payment/altapay_recurring/terminal';
	const CONT_PATH_RECURRING_ACTION_TYPE = 'payment/altapay_recurring/payment_action';
	
	// payment types
	const ACTION_AUTHORIZE         = 'payment';
	const ACTION_AUTHORIZE_CAPTURE = 'paymentAndCapture';
	const ACTION_RECURRING         = 'recurring';
}