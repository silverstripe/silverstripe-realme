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
        if (true === (bool) $cache->load($cacheKey)) {
            return true;
        }

        // check we have config constants present.
        $configs = array('REALME_CERT_DIR', 'REALME_LOG_DIR', 'REALME_TEMP_DIR');
        foreach ($configs as $config) {
            if (false === defined($config)) {
                SS_Log::log(
                    sprintf('RealMe config not set: %s', $config),
                    SS_Log::ERR
                );
                return false;
            };

            $path = rtrim(constant($config), '/');
            if (false === file_exists($path) || false === is_readable($path)) {
                SS_Log::log(
                    sprintf('RealMe config dir missing or not readable: %s', $config),
                    SS_Log::ERR
                );
                return false;
            }
        }

        // Check certificates (cert dir must exist at this point).
        $certificates = array('REALME_SIGNING_CERT_FILENAME', 'REALME_MUTUAL_CERT_FILENAME');
        foreach ($certificates as $cert) {
            $path = rtrim(REALME_CERT_DIR, '/') . "/" . constant($cert);
            if (false === file_exists($path) || false === is_readable($path)) {
                SS_Log::log(
                    sprintf('RealMe %s missing: %s', $cert, $path),
                    SS_Log::ERR
                );
                return false;
            }
        }

        $cache->save('true', $cacheKey);
        return true;
    }

    public static function get_name()
    {
        return _t('RealMeAuthenticator.TITLE', 'RealMe Account');
    }
}
