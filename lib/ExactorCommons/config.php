<?php
/**
 * User: LOGICIFY\corvis
 * Date: 4/20/12
 * Time: 10:36 AM
 */
define('EXACTOR_BUILD_CUSTOM_ITEM_KEY', false);
/* Available config options */
define('EXACTOR_CONFIG_EXEMPT_DISCOUNTS', 'exempt-descounts');
define('EXACTOR_CONFIG_FEATURE_EXEMPT_DISCOUNTS', 'discount-exemptions');

define('EXACTOR_CONFIG_TRN_FILTER', 'trn-filter');
define('EXACTOR_CONFIG_FEATURE_TRN_FILTER', 'trn-filter');

define('EXACTOR_CONFIG_FEATURE_DISABLE_ESTIMATES', 'disable-estimates');

/*
 * Enable this option to always override tax in quotes instead of overriding only if quote checksum changes.
 * Is useful in case if user installed other plug-ins overriding tax.
 * WARNING: this option increases RAM memory consuming.
 * */
define('EXACTOR_CONFIG_ALWAYS_OVERRIDE_TAX', 'always-override-tax');

/* Initializing factories */
ExactorLoggingFactory::getInstance()->setup('MagentoLogger', IExactorLogger::DEBUG);
ExactorConnectionFactory::getInstance()->setup('Magento','20141215');
ExactorProcessingServiceFactory::getInstance()->setup(new MagentoExactorCallback());

/* Initializing configuration object */
$config = ExactorPluginConfig::getInstance();
$config->pushFeatureConfigString('');

/* Pushing parameters */
$config->set(EXACTOR_CONFIG_EXEMPT_DISCOUNTS, array());
//$config->set(EXACTOR_CONFIG_ALWAYS_OVERRIDE_TAX, true);