<?php
$installer = $this;

$installer->startSetup();

$installer->run("
	CREATE TABLE IF NOT EXISTS {$this->getTable('altapay_subscriptions')} (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `subscription_id` varchar(36) NOT NULL,
	  `customer_id` int(11) NOT NULL,
	  `masked_pan` varchar(255) NOT NULL,
	  `card_token` varchar(255) NOT NULL,
	  `currency_code` varchar(3) NOT NULL,
	  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");



$installer->endSetup();