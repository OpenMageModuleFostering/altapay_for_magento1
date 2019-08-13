<?php

class AltapayAPICustomerInfo
{
	/*
					<UserAgent>Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:13.0)
						Gecko/20100101 Firefox/13.0.1</UserAgent>
					<IpAddress>81.7.175.18</IpAddress>
					<Email></Email>
					<Username></Username>
					<CustomerPhone></CustomerPhone>
					<OrganisationNumber></OrganisationNumber>
					<CountryOfOrigin>
						<Country></Country><Source>NotSet</Source>
					</CountryOfOrigin>
	*/
	private $userAgent;
	private $ipAddress;
	private $email;
	private $username;
	private $phone;
	private $organisationNumber;
	
	/**
	 * @var AltapayAPIAddress
	 */
	private $billingAddress,$shippingAddress,$registeredAddress;

	private $countryOfOrigin;

	public function __construct(SimpleXmlElement $xml)
	{
		$this->userAgent = (string)$xml->UserAgent;
		$this->ipAddress = (string)$xml->IpAddress;
		$this->email = (string)$xml->Email;
		$this->username = (string)$xml->Username;
		$this->phone = (string)$xml->CustomerPhone;
		$this->organisationNumber = (string)$xml->OrganisationNumber;

		if(isset($xml->CountryOfOrigin))
		{
			$this->countryOfOrigin = new AltapayAPICountryOfOrigin($xml->CountryOfOrigin);
		}
		if(isset($xml->BillingAddress))
		{
			$this->billingAddress = new AltapayAPIAddress($xml->BillingAddress);
		}
		if(isset($xml->ShippingAddress))
		{
			$this->shippingAddress = new AltapayAPIAddress($xml->ShippingAddress);
		}
		if(isset($xml->RegisteredAddress))
		{
			$this->registeredAddress = new AltapayAPIAddress($xml->RegisteredAddress);
		}
	}
	
	/**
	 * @return AltapayAPIAddress
	 */
	public function getBillingAddress()
	{
		return $this->billingAddress;
	}

	/**
	 * @return AltapayAPIAddress
	 */
	public function getShippingAddress()
	{
		return $this->shippingAddress;
	}
	
	/**
	 * @return AltapayAPIAddress
	 */
	public function getRegisteredAddress()
	{
		return $this->registeredAddress;
	}

	/**
	 * @return AltapayAPICountryOfOrigin
	 */
	public function getCountryOfOrigin()
	{
		return $this->countryOfOrigin;
	}
	
	public function getUserAgent()
	{
		return $this->userAgent;
	}
	
	public function getIpAddress()
	{
		return $this->ipAddress;
	}
	
	public function getEmail()
	{
		return $this->email;
	}
	
	public function getUsername()
	{
		return $this->username;
	}
	
	public function getPhone()
	{
		return $this->phone;
	}
	
	public function getOrganisationNumber()
	{
		return $this->organisationNumber;
	}
}