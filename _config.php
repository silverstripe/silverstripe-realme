<?php
// Only define RealMeAuthenticator module if at least one SS env var is set
if(defined('REALME_CERT_DIR')) {
    Authenticator::register_authenticator('RealMeAuthenticator');
}


// defines the base directory, used by RealMeLoginForm to include javascript and css via Requirements
define('REALME_MODULE_PATH', basename(dirname(__FILE__)));
