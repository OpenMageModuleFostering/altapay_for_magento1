<?php
$installer = $this;

$installer->startSetup();

$installer->run("
	CREATE TABLE IF NOT EXISTS {$this->getTable('altapay_token')} (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `customer_id` int(11) NOT NULL,
	  `token` varchar(128) NOT NULL DEFAULT '',
	  `masked_pan` varchar(255) NOT NULL,
	  `currency_code` varchar(3) NOT NULL,
	  `custom_name` varchar(255) NOT NULL,
	  `primary` tinyint(2) NOT NULL DEFAULT '0',
	  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	  `deleted` tinyint(2) NOT NULL DEFAULT '0',
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

$installer->endSetup();