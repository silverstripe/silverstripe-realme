<?php

/**
 * Class RealMeAuthenticator
 *
 *
 */
class RealMeAuthenticator extends Authenticator
{
    public static function get_login_form(Controller $controller)
    {
        return RealMeLoginForm::create($controller, 'LoginForm');
    }

    /**
     * Ensures that enough detail has been configured to allow this authenticator to function properly. Specifically,
     * this checks the following:
     * - Check certs are in place
     * - RealMeSetupTask has been created
     *
     * @return bool false if the authenticator shouldn't be registered
     */
    protected static function on_register()
    {
        $cache = SS_Cache::factory('RealMeAuthenticator');

        $cacheKey = 'RegisterCheck';
        if ($cache->test($cacheKey)) {
            return true;
        }

        // check we have config constants present.
        if (!defined('REALME_CERT_DIR')) {
            SS_Log::log('RealMe env config REALME_CERT_DIR not set', SS_Log::ERR);
            return false;
        };

        $path = rtrim(constant('REALME_CERT_DIR'), '/');
        if (!file_exists($path) || !is_readable($path)) {
            SS_Log::log('RealMe certificate dir (REALME_CERT_DIR) missing or not readable', SS_Log::ERR);
            return false;
        }

        // Check certificates (cert dir must exist at this point).
        $path = rtrim(REALME_CERT_DIR, '/') . "/" . constant('REALME_SIGNING_CERT_FILENAME');
        if (!file_exists($path) || !is_readable($path)) {
            SS_Log::log(sprintf('RealMe %s missing: %s', constant('REALME_SIGNING_CERT_FILENAME'), $path), SS_Log::ERR);
            return false;
        }

        $cache->save('1', $cacheKey);
        return true;
    }

    public static function get_name()
    {
        return _t('RealMeAuthenticator.TITLE', 'RealMe Account');
    }
}
