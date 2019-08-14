<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category   Exactor
 * @package    Exactor_Exactordetails
 * @copyright  Copyright (c) 2008 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

  /* @var $installer Mage_Core_Model_Resource_Setup */
  $installer = $this;

  $installer->startSetup();

  $installer->run("
					CREATE TABLE IF NOT EXISTS {$this->getTable('exactor_account')} (
															   `ID` int(10) NOT NULL AUTO_INCREMENT,
															   `StoreViewID` int(20) DEFAULT NULL,
															   `MerchantID` varchar(50) NOT NULL,
															   `UserID` varchar(50) NOT NULL DEFAULT '',
															   `Password` varchar(50) DEFAULT NULL,
															   `FullName` varchar(250) NOT NULL DEFAULT '',
															   `Street1` varchar(250) NOT NULL DEFAULT '',
															   `Street2` varchar(250) DEFAULT NULL,
															   `City` varchar(150) NOT NULL DEFAULT '',
															   `StateOrProvince` varchar(50) NOT NULL DEFAULT '',
															   `PostalCode` varchar(50) NOT NULL DEFAULT '',
															   `Country` varchar(50) NOT NULL DEFAULT '',
															   `ShippingCharges` varchar(2) DEFAULT NULL,
															   `SourceofSKU` varchar(2) DEFAULT NULL,
															   `AttributeName` varchar(50) DEFAULT NULL,
															   `CommitOption` varchar(2) DEFAULT NULL,
															   `EntityExemptions` varchar(2) DEFAULT NULL,
															   PRIMARY KEY (`ID`)
															) ENGINE=InnoDB DEFAULT CHARSET=utf8;

					CREATE TABLE IF NOT EXISTS {$this->getTable('exactor_transaction_order')} (
															 `ID` int(20) NOT NULL AUTO_INCREMENT,
															 `TransactionID` varchar(100) DEFAULT NULL,
															 `OrderID` varchar(100) DEFAULT NULL,
															 `UserID` varchar(50) DEFAULT NULL,
															 `MerchantID` varchar(50) DEFAULT NULL,
															 `DateOfOrder` date DEFAULT NULL,
															 `Commited` tinyint(1) DEFAULT 1,
															 PRIMARY KEY (`ID`)
																	    ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
				");

	$installer->endSetup();
