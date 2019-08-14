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

/* Initializing factories */
ExactorLoggingFactory::getInstance()->setup('MagentoLogger', IExactorLogger::DEBUG);
ExactorConnectionFactory::getInstance()->setup('Magento','20140226');
ExactorProcessingServiceFactory::getInstance()->setup(new MagentoExactorCallback());

/* Initializing configuration object */
$config = ExactorPluginConfig::getInstance();
$config->pushFeatureConfigString('');

/* Pushing parameters */
$config->set(EXACTOR_CONFIG_EXEMPT_DISCOUNTS, array());