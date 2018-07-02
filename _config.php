<?php
use SilverStripe\Control\Director;

if (Director::isDev()) {
    error_reporting(E_ALL ^ E_DEPRECATED);
}

define('REALME_MODULE_PATH', basename(dirname(__FILE__)));
