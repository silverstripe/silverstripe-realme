<?php

// Bootstrap file designed to be included inside of all requests
// Provides hooks between _ss_environment.php and other saml2 specific configurations

// Load _ss_environment.php settings
// Replace https://github.com/madmatt/simplesamlphp/commit/43a910d49106ea60dee9f771a6347649917098cf
require_once dirname(__DIR__) . '/framework/core/Constants.php';

// Detect forwarded protocol (HTTPS detection)
// Replaces https://github.com/madmatt/simplesamlphp/commit/e0d5ca8da8a10a611e45651b122486d5eb7da1eb
if(defined('TRUSTED_PROXY') && TRUSTED_PROXY) {
    if (
        // Convention for (non-standard) proxy signaling a HTTPS forward, see https://en.wikipedia.org/wiki/List_of_HTTP_header_fields
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https') ||
        // Less conventional proxy header
        (isset($_SERVER['HTTP_X_FORWARDED_PROTOCOL']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTOCOL']) == 'https') ||
        // Microsoft proxy convention: https://support.microsoft.com/en-us/kb/307347
        (isset($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) == 'on')
    ) {
        $_SERVER['HTTPS'] = 'on';

        // Ensure HTTPS port is set
        if (isset($_SERVER['HTTP_X_FORWARDED_PORT'])) {
            $_SERVER['SERVER_PORT'] = (string)$_SERVER['HTTP_X_FORWARDED_PORT'];
        } else {
            $_SERVER['SERVER_PORT'] = '443';
        }
    }
}
