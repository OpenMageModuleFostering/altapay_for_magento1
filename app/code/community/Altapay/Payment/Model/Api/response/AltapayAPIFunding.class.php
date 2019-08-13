<?php

class AltapayAPIFunding
{
	private $filename;
	private $contractIdentifier;
	private $shops = array();
	private $acquirer;
	private $fundingDate;
	private $amount;
	private $currency;
	private $createdDate;
	private $downloadLink;
	
	public function __construct(SimpleXmlElement $xml)
	{
		$this->filename = (string)$xml->Filename;
		$this->contractIdentifier = (string)$xml->ContractIdentifier;
		foreach($xml->Shops->Shop as $shop)
		{
			$this->shops[] = (string)$shop;
		}
		$this->acquirer = (string)$xml->Acquirer;
		$this->fundingDate = (string)$xml->FundingDate;
		list($this->amount, $this->currency) = explode(" ", (string)$xml->Amount, 2);
		$this->createdDate = (string)$xml->CreatedDate;
		$this->downloadLink = (string)$xml->DownloadLink;
	}
	
	public function getFundingDate()
	{
		return $this->fundingDate;
	}
	
	public function getAmount()
	{
		return $this->amount;
	}
	
	public function getCurrency()
	{
		return $this->currency;
	}
	
	public function getDownloadLink()
	{
		return $this->downloadLink;
	}

	public function getFilename()
	{
		return $this->filename;
	}

	public function getContractIdentifier()
	{
		return $this->contractIdentifier;
	}

	public function getShops()
	{
		return $this->shops;
	}

	public function getAcquirer()
	{
		return $this->acquirer;
	}

	public function getCreatedDate()
	{
		return $this->createdDate;
	}
}