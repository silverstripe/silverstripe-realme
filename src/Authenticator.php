<?php

namespace SilverStripe\RealMe;

use Exception;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\RealMe\Authenticator\LoginHandler as RealMeLoginHandler;
use SilverStripe\Security\Authenticator as AuthenticatorInterface;
use SilverStripe\Security\Member;
use SilverStripe\Security\MemberAuthenticator\LogoutHandler;

/**
 * Class RealMeAuthenticator
 *
 *
 */
class Authenticator implements AuthenticatorInterface
{
    use Injectable;

    private static $dependencies = [
        'service' => '%$' . RealMeService::class,
    ];

    /**
     * @var RealMeService
     */
    protected $service;

    /**
     * Ensures that enough detail has been configured to allow this authenticator to function properly. Specifically,
     * this checks the following:
     * - Check certs are in place
     * - RealMeSetupTask has been created
     *
     * @return bool false if the authenticator shouldn't be registered
     */
    public function __construct()
    {
        $cache = Injector::inst()->get(CacheInterface::class . '.realMeAuthenticator');

        $cacheKey = 'RegisterCheck';
        if ($cache->get($cacheKey) !== false) {
            return true;
        }

        /** @var LoggerInterface $logger */
        $logger = Injector::inst()->get(LoggerInterface::class);

        $certDir = Environment::getEnv('REALME_CERT_DIR');
        $certFilename = Environment::getEnv('REALME_SIGNING_CERT_FILENAME');

        // check we have config constants present.
        if (!$certDir) {
            $logger->error('RealMe env config REALME_CERT_DIR not set');
            return false;
        };

        $path = rtrim($certDir ?? '', '/');
        if (!file_exists($path ?? '') || !is_readable($path ?? '')) {
            $logger->error('RealMe certificate directory (REALME_CERT_DIR) missing or not readable');
            return false;
        }

        // Check certificates (cert dir must exist at this point).
        $path = rtrim($certDir ?? '', '/') . "/" . $certFilename;
        if (!file_exists($path ?? '') || !is_readable($path ?? '')) {
            $logger->error(sprintf('RealMe %s missing: %s', $certFilename, $path));
            return false;
        }

        $cache->save('1', $cacheKey);
        return true;
    }

    /**
     * Returns the services supported by this authenticator
     *
     * The number should be a bitwise-OR of 1 or more of the following constants:
     * Authenticator::LOGIN, Authenticator::LOGOUT, Authenticator::CHANGE_PASSWORD,
     * Authenticator::RESET_PASSWORD, or Authenticator::CMS_LOGIN
     *
     * @return int
     */
    public function supportedServices()
    {
        return Authenticator::CMS_LOGIN | Authenticator::LOGIN;
    }

    /**
     * Return RequestHandler to manage the log-in process.
     *
     * The default URL of the RequestHandler should return the initial log-in form, any other
     * URL may be added for other steps & processing.
     *
     * URL-handling methods may return an array [ "Form" => (form-object) ] which can then
     * be merged into a default controller.
     *
     * @param string $link The base link to use for this RequestHandler
     * @return RealMeLoginHandler
     */
    public function getLoginHandler($link)
    {
        return RealMeLoginHandler::create($link, $this);
    }

    /**
     * Return the RequestHandler to manage the log-out process.
     *
     * The default URL of the RequestHandler should log the user out immediately and destroy the session.
     *
     * @param string $link The base link to use for this RequestHandler
     * @return LogoutHandler
     */
    public function getLogOutHandler($link)
    {
        // No-op
    }

    /**
     * Return RequestHandler to manage the change-password process.
     *
     * The default URL of the RequetHandler should return the initial change-password form,
     * any other URL may be added for other steps & processing.
     *
     * URL-handling methods may return an array [ "Form" => (form-object) ] which can then
     * be merged into a default controller.
     *
     * @param string $link The base link to use for this RequestHnadler
     */
    public function getChangePasswordHandler($link)
    {
        return null; // Cannot provide change password facilities for RealMe
    }

    /**
     * @param string $link
     * @return mixed
     */
    public function getLostPasswordHandler($link)
    {
        return null; // Cannot provide lost password facilities for RealMe
    }

    /**
     * Method to authenticate an user.
     *
     * @param array $data Raw data to authenticate the user.
     * @param HTTPRequest $request
     * @param ValidationResult $result A validationresult which is either valid or contains the error message(s)
     * @return Member The matched member, or null if the authentication fails
     */
    public function authenticate(array $data, HTTPRequest $request, ValidationResult &$result = null)
    {
        try {
            $this->service->enforceLogin($request);

            if ($this->service->getUserData()) {
                return $this->service->getUserData()->getMember();
            }
        } catch (Exception $e) {
            $msg = sprintf(
                'Error during RealMe authentication process. Code: %d, Message: %s',
                $e->getCode(),
                $e->getMessage()
            );

            Injector::inst()->get(LoggerInterface::class)->info($msg);
        }

        return null;
    }

    /**
     * Check if the passed password matches the stored one (if the member is not locked out).
     *
     * Note, we don't return early, to prevent differences in timings to give away if a member
     * password is invalid.
     *
     * Passwords are not part of this authenticator
     *
     * @param Member $member
     * @param string $password
     * @param ValidationResult $result
     * @return ValidationResult
     */
    public function checkPassword(Member $member, $password, ValidationResult &$result = null)
    {
        // No-op
    }

    /**
     * @return RealMeService
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * @param RealMeService $service
     * @return $this
     */
    public function setService($service)
    {
        $this->service = $service;

        return $this;
    }
}
