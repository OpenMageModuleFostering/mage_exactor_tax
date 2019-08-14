<?php
/**
 * User: LOGICIFY\corvis
 * Date: 4/20/12
 * Time: 10:36 AM
 */

ExactorLoggingFactory::getInstance()->setup('MagentoLogger', IExactorLogger::TRACE);
ExactorConnectionFactory::getInstance()->setup('Magento','v20120618');
ExactorProcessingServiceFactory::getInstance()->setup(new MagentoExactorCallback());

