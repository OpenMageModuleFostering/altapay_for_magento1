<?php

if(!defined('ALTAPAY_API_ROOT'))
{
	define('ALTAPAY_API_ROOT',dirname(__DIR__));
}

require_once(ALTAPAY_API_ROOT.'/http/AltapayHttpRequest.class.php');
require_once(ALTAPAY_API_ROOT.'/http/AltapayHttpResponse.class.php');

interface IAltapayHttpUtils
{
	/**
	 * @return AltapayHttpResponse
	 */
	public function requestURL(AltapayHttpRequest $request);
}