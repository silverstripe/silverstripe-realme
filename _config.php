<?php
Authenticator::register_authenticator('RealMeAuthenticator');

// defines the base directory, used by RealMeLoginForm to include javascript and css via Requirements
define('REALME_MODULE_PATH', basename(dirname(__FILE__)));
