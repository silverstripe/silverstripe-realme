<?php

namespace SilverStripe\RealMe;

use DOMDocument;
use DOMNodeList;
use Exception as BaseException;
use InvalidArgumentException;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;
use OneLogin\Saml2\Response;
use OneLogin\Saml2\Utils;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\RealMe\Exception as RealMeException;
use SilverStripe\RealMe\Model\FederatedIdentity;
use SilverStripe\RealMe\Model\User;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\View\TemplateGlobalProvider;

class RealMeService implements TemplateGlobalProvider
{
    use Configurable, Injectable;

    /**
     * Current RealMe supported environments.
     */
    const ENV_MTS = 'mts';
    const ENV_ITE = 'ite';
    const ENV_PROD = 'prod';

    /**
     * SAML binding types
     */
    const TYPE_LOGIN = 'login';
    const TYPE_ASSERT = 'assert';

    /**
     * the valid AuthN context values for each supported RealMe environment.
     */
    const AUTHN_LOW_STRENGTH = 'urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:LowStrength';
    const AUTHN_MOD_STRENTH = 'urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:ModStrength';
    const AUTHN_MOD_MOBILE_SMS =
        'urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:ModStrength::OTP:Mobile:SMS';
    const AUTHN_MOD_TOKEN_SID =
        'urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:ModStrength::OTP:Token:SID';

    /**
     * Realme SAML2 error status constants
     */
    const ERR_TIMEOUT                = 'urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:status:Timeout';
    const ERR_INTERNAL_ERROR         = 'urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:status:InternalError';

    /**
     * SAML2 Error constants used for business logic and switching error messages
     */
    const ERR_AUTHN_FAILED           = 'urn:oasis:names:tc:SAML:2.0:status:AuthnFailed';
    const ERR_UNKNOWN_PRINCIPAL      = 'urn:oasis:names:tc:SAML:2.0:status:UnknownPrincipal';
    const ERR_NO_AVAILABLE_IDP       = 'urn:oasis:names:tc:SAML:2.0:status:NoAvailableIDP';
    const ERR_NO_PASSIVE             = 'urn:oasis:names:tc:SAML:2.0:status:NoPassive';
    const ERR_NO_AUTHN_CONTEXT       = 'urn:oasis:names:tc:SAML:2.0:status:NoAuthnContext';
    const ERR_REQUEST_UNSUPPORTED    = 'urn:oasis:names:tc:SAML:2.0:status:RequestUnsupported';
    const ERR_REQUEST_DENIED         = 'urn:oasis:names:tc:SAML:2.0:status:RequestDenied';
    const ERR_UNSUPPORTED_BINDING    = 'urn:oasis:names:tc:SAML:2.0:status:UnsupportedBinding';

    /**
     * @var bool true to sync RealMe data and create/update local {@link Member} objects upon successful authentication
     * @config
     */
    private static $sync_with_local_member_database = false;

    /**
     * @var User|null User data returned by RealMe. Provided by {@link self::ensureLogin()}.
     *
     * Data within this ArrayData is as follows:
     * - NameID:       ArrayData   Includes the UserFlt and associated formatting information
     * - UserFlt:      string      RealMe pseudonymous username / identity
     * - Attributes:   ArrayData   User attributes returned by RealMe
     * - Expire:       SS_Datetime The expiry date & time of this authentication session
     * - SessionIndex: string      Unique identifier used to identify a user with both IdP and SP for given user.
     */
    private static $user_data = null;

    /**
     * @config
     * @var string The RealMe environment to connect to and authenticate against. This should be set by Config, and
     * generally be different per SilverStripe environment (e.g. developer environments would generally use 'mts',
     * UAT/staging sites might use 'ite', and production sites would use 'prod'.
     *
     * Valid options:
     * - mts
     * - ite
     * - prod
     */
    private static $realme_env = 'mts';

    /**
     * @var array The RealMe environments that can be configured for use with this module.
     */
    private static $allowed_realme_environments = array(self::ENV_MTS, self::ENV_ITE, self::ENV_PROD);

    /**
     * @config
     * @var string The RealMe integration type to use when connecting to RealMe. After successful authentication:
     * - 'login' provides a unique FLT (Federated Login Token) back
     * - 'assert' provides a unique FIT (Federated Identity Token) and a {@link RealMeFederatedIdentity} object back
     */
    private static $integration_type = 'login';

    private static $allowed_realme_integration_types = array(self::TYPE_LOGIN, self::TYPE_ASSERT);

    /**
     * @config
     * @var array Stores the entity ID value for each supported RealMe environment. This needs to be setup prior to
     * running the `RealMeSetupTask` build task. For more information, see the module documentation. An entity ID takes
     * the form of a URL, e.g. https://www.agency.govt.nz/privacy-realm-name/application-name
     */
    private static $sp_entity_ids = array(
        self::ENV_MTS => null,
        self::ENV_ITE => null,
        self::ENV_PROD => null
    );

    /**
     * @config
     * @var array Stores the default identity provider (IdP) entity IDs. These can be customised if you're using an
     * intermediary IdP instead of connecting to RealMe directly.
     */
    private static $idp_entity_ids = array(
        self::ENV_MTS => array(
            self::TYPE_LOGIN  => 'https://mts.realme.govt.nz/saml2',
            self::TYPE_ASSERT => 'https://mts.realme.govt.nz/realmemts/realmeidp',
        ),
        self::ENV_ITE => array(
            self::TYPE_LOGIN  => 'https://www.ite.logon.realme.govt.nz/saml2',
            self::TYPE_ASSERT => 'https://www.ite.account.realme.govt.nz/saml2/assertion',
        ),
        self::ENV_PROD => array(
            self::TYPE_LOGIN  => 'https://www.logon.realme.govt.nz/saml2',
            self::TYPE_ASSERT => 'https://www.account.realme.govt.nz/saml2/assertion',
        )
    );

    private static $idp_sso_service_urls = array(
        self::ENV_MTS => array(
            self::TYPE_LOGIN  => 'https://mts.realme.govt.nz/logon-mts/mtsEntryPoint',
            self::TYPE_ASSERT => 'https://mts.realme.govt.nz/realme-mts/validate/realme-mts-idp.xhtml'
        ),
        self::ENV_ITE => array(
            self::TYPE_LOGIN  => 'https://www.ite.logon.realme.govt.nz/sso/logon/metaAlias/logon/logonidp',
            self::TYPE_ASSERT => 'https://www.ite.assert.realme.govt.nz/sso/SSORedirect/metaAlias/assertion/realmeidp'
        ),
        self::ENV_PROD => array(
            self::TYPE_LOGIN  => 'https://www.logon.realme.govt.nz/sso/logon/metaAlias/logon/logonidp',
            self::TYPE_ASSERT => 'https://www.assert.realme.govt.nz/sso/SSORedirect/metaAlias/assertion/realmeidp'
        )
    );

    /**
     * @var array A list of certificate filenames for different RealMe environments and integration types. These files
     * must be located in the directory specified by the REALME_CERT_DIR environment variable. These filenames are the
     * same as the files that can be found in the RealMe Shared Workspace, within the 'Integration Bundle' ZIP files for
     * the different environments (MTS, ITE and Production), so you just need to extract the specific certificate file
     * that you need and make sure it's in place on the server in the REALME_CERT_DIR.
     */
    private static $idp_x509_cert_filenames = array(
        self::ENV_MTS => array(
            self::TYPE_LOGIN  => 'mts_login_saml_idp.cer',
            self::TYPE_ASSERT => 'mts_assert_saml_idp.cer'
        ),
        self::ENV_ITE => array(
            self::TYPE_LOGIN  => 'ite.signing.logon.realme.govt.nz.cer',
            self::TYPE_ASSERT => 'ite.signing.account.realme.govt.nz.cer'
        ),
        self::ENV_PROD => array(
            self::TYPE_LOGIN  => 'signing.logon.realme.govt.nz.cer',
            self::TYPE_ASSERT => 'signing.account.realme.govt.nz.cer'
        )
    );

    /**
     * @config
     * @var array Stores the AuthN context values for each supported RealMe environment. This needs to be setup prior to
     * running the `RealMeSetupTask` build task. For more information, see the module documentation. An AuthN context
     * can be one of the following:
     *
     * Username and password only:
     * - urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:LowStrength
     *
     * Username, password, and any moderate strength second level of authenticator (RSA token, Google Auth, SMS)
     * - urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:ModStrength
     *
     * The following two are less often used, and shouldn't be used unless there's a specific need.
     *
     * Username, password, and only SMS 2FA token
     * - urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:ModStrength::OTP:Mobile:SMS
     *
     * Username, password, and only RSA 2FA token
     * - urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:ModStrength::OTP:Token:SID
     */
    private static $authn_contexts = array(
        self::ENV_MTS => null,
        self::ENV_ITE => null,
        self::ENV_PROD => null
    );

    /**
     * @config $allowed_authn_context_list
     * @var $allowed_authn_context_list array
     *
     * A list of the valid authn context values supported for realme.
     */
    private static $allowed_authn_context_list = array(
        self::AUTHN_LOW_STRENGTH,
        self::AUTHN_MOD_STRENTH,
        self::AUTHN_MOD_MOBILE_SMS,
        self::AUTHN_MOD_TOKEN_SID
    );

    /**
     * @config
     * @var array Domain names for metadata files. Used in @link RealMeSetupTask when outputting metadata XML
     */
    private static $metadata_assertion_service_domains = array(
        self::ENV_MTS => null,
        self::ENV_ITE => null,
        self::ENV_PROD => null
    );

    /**
     * @config
     * @var array A list of error messages to display if RealMe returns error statuses, instead of the default
     * translations (found in realme/lang/en.yml for example).
     */
    private static $realme_error_message_overrides = array(
        self::ERR_AUTHN_FAILED => null,
        self::ERR_TIMEOUT => null,
        self::ERR_INTERNAL_ERROR => null,
        self::ERR_NO_AVAILABLE_IDP => null,
        self::ERR_REQUEST_UNSUPPORTED => null,
        self::ERR_NO_PASSIVE => null,
        self::ERR_REQUEST_DENIED => null,
        self::ERR_UNSUPPORTED_BINDING => null,
        self::ERR_UNKNOWN_PRINCIPAL => null,
        self::ERR_NO_AUTHN_CONTEXT => null
    );

    /**
     * @config
     * @var string|null The organisation name to be used in metadata XML that is submitted to RealMe
     */
    private static $metadata_organisation_name = null;

    /**
     * @config
     * @var string|null The organisation display name to be used in metadata XML that is submitted to RealMe
     */
    private static $metadata_organisation_display_name = null;

    /**
     * @config
     * @var string|null The organisation URL to be used in metadata XML that is submitted to RealMe
     */
    private static $metadata_organisation_url = null;

    /**
     * @config
     * @var string|null The support contact's company name to be used in metadata XML that is submitted to RealMe
     */
    private static $metadata_contact_support_company = null;

    /**
     * @config
     * @var string|null The support contact's first name(s) to be used in metadata XML that is submitted to RealMe
     */
    private static $metadata_contact_support_firstnames = null;

    /**
     * @config
     * @var string|null The support contact's surname to be used in metadata XML that is submitted to RealMe
     */
    private static $metadata_contact_support_surname = null;

    /**
     * @var Auth|null Set by {@link getAuth()}, which creates an instance of Auth to check
     * authentication against
     */
    private $auth = null;

    /**
     * @var string|null The last error message during login enforcement
     */
    private $lastError = null;

    /**
     * @return array
     */
    public static function get_template_global_variables()
    {
        return array(
            'RealMeUser' => array(
                'method' => 'current_realme_user'
            )
        );
    }

    /**
     * @return HTTPRequest|null
     */
    protected static function getRequest()
    {
        if (!Injector::inst()->has(HTTPRequest::class)) {
            return null;
        };

        return Injector::inst()->get(HTTPRequest::class);
    }

    /**
     * Return the user data which was saved to session from the first RealMe
     * auth.
     * Note: Does not check authenticity or expiry of this data
     *
     * @param HTTPRequest $request
     * @return User
     */
    public static function user_data()
    {
        if (!is_null(static::$user_data)) {
            return static::$user_data;
        }

        $request = self::getRequest();

        if (!$request) {
            return null;
        }

        $sessionData = $request->getSession()->get('RealMe.SessionData');

        // Exit point
        if (is_null($sessionData)) {
            return null;
        }

        // Unserialise stored data
        $user = unserialize($sessionData);

        if ($user == false || !$user instanceof User) {
            return null;
        }

        static::$user_data = $user;
        return static::$user_data;
    }

    public function getUserData()
    {
        return static::user_data();
    }

    /**
     * Calls available user data and checks for validity
     *
     * @return User
     */
    public static function current_realme_user()
    {
        $user = self::user_data();
        if ($user && !$user->isValid()) {
            return null;
        }

        return $user;
    }

    /**
     * A helpful static method that follows SilverStripe naming for Member::currentUser();
     *
     * @return User
     */
    public static function currentRealMeUser()
    {
        return self::current_realme_user();
    }

    /**
     * Enforce login via RealMe. This can be used in controllers to force users to be authenticated via RealMe (not
     * necessarily logged in as a {@link Member}), in the form of:
     * <code>
     * Session::set('RealMeBackURL', '/path/to/the/controller/method');
     * if($service->enforceLogin()) {
     *     // User has a valid RealMe account, $service->getAuthData() will return you their details
     * } else {
     *     // Something went wrong processing their details, show an error
     * }
     * </code>
     *
     * In cases where people are *not* authenticated with RealMe, this method will redirect them directly to RealMe.
     *
     * However, generally you want this to be an explicit process, so you should look at instead using the standard
     * {@link RealMeAuthenticator}.
     *
     * A return value of bool false indicates that there was a failure during the authentication process (perhaps a
     * communication issue, or a failure to decode the response correctly. You should handle this like you would any
     * other unexpected authentication error. You can use {@link getLastError()} to see if a human-readable error
     * message exists for display to the user.
     *
     * @param HTTPRequest $request
     * @param string $backUrl
     * @return bool|null true if the user is correctly authenticated, false if there was an error with login
     * @throws Error
     */
    public function enforceLogin(HTTPRequest $request, $backUrl = null)
    {
        // First, check to see if we have an existing authenticated session
        if ($this->isAuthenticated()) {
            return true;
        }

        $session = $request->getSession();

        if ($backUrl) {
            $session->set('RealMeBackURL', $this->validSiteURL($backUrl));
        }

        // If not, attempt to retrieve authentication data from OneLogin (in case this is called during SAML assertion)
        try {
            if (!$session->get("RealMeErrorBackURL") && Controller::has_curr()) {
                $session->set("RealMeErrorBackURL", Controller::curr()->Link("Login"));
            }

            $auth = $this->getAuth();
            $auth->processResponse();

            // if there were any errors from the SAML request, process and translate them.
            $errors = $auth->getErrors();
            if (is_array($errors) && !empty($errors)) {
                $this->processSamlErrors($errors);
                return false;
            }

            $authData = $this->getAuthData();

            // If no data is found, then force login
            if (is_null($authData)) {
                throw new RealMeException('No SAML data, enforcing login', RealMeException::NOT_AUTHENTICATED);
            }

            // call a success method as we've successfully logged in (if it exists)
            Member::singleton()->extend('onRealMeLoginSuccess', $authData);
        } catch (BaseException $e) {
            Member::singleton()->extend("onRealMeLoginFailure", $e);

            // No auth data or failed to decrypt, enforce login again
            $this->getAuth()->login(Director::absoluteBaseURL());
            die;
        }

        return $auth->isAuthenticated();
    }

    /**
     * If there was an error returned from the saml response, process the errors
     *
     * @param $errors
     */
    private function processSamlErrors(array $errors)
    {
        $translatedMessage = null;

        // The error message returned by onelogin/php-saml is the top-level error, but we want the actual error
        $request = Controller::curr()->getRequest();
        if ($request->isPOST() && $request->postVar("SAMLResponse")) {
            $response = new Response($this->getAuth()->getSettings(), $request->postVar("SAMLResponse"));
            $internalError = Utils::query(
                $response->document,
                "/samlp:Response/samlp:Status/samlp:StatusCode/samlp:StatusCode/@Value"
            );

            if ($internalError instanceof DOMNodeList && $internalError->length > 0) {
                $internalErrorCode = $internalError->item(0)->textContent;
                $translatedMessage = $this->findErrorMessageForCode($internalErrorCode);
            }
        }

        // If we found a message to display, then let's redirect to the form and display it
        if ($translatedMessage) {
            $this->lastError = $translatedMessage;
        }

        Injector::inst()->get(LoggerInterface::class)->info(sprintf(
            'onelogin/php-saml error messages: %s (%s)',
            join(', ', $errors),
            $this->getAuth()->getLastErrorReason()
        ));
    }

    /**
     * Checks data stored in Session to see if the user is authenticated.
     * @return bool true if the user is authenticated via RealMe and we can trust ->getUserData()
     */
    public function isAuthenticated()
    {
        $user = $this->getUserData();
        return $user instanceof User && $user->isAuthenticated();
    }

    /**
     * Returns a {@link RealMeUser} object if one can be built from the RealMe session data.
     *
     * @throws Error Passes on the SAML error if it's not indicating a lack of SAML response data
     * @throws RealMeException If identity information exists but couldn't be decoded, or doesn't exist
     * @return User|null
     */
    public function getAuthData()
    {
        // returns null if the current auth is invalid or timed out.
        try {
            // Process response and capture details
            $auth = $this->getAuth();

            if (!$auth->isAuthenticated()) {
                throw new RealMeException(
                    'OneLogin SAML library did not successfully authenticate, but did not return a specific error',
                    RealMeException::NOT_AUTHENTICATED
                );
            }

            $spNameId = $auth->getNameId();
            if (!is_string($spNameId)) {
                throw new RealMeException('Invalid/Missing NameID in SAML response', RealMeException::MISSING_NAMEID);
            }

            $sessionIndex = $auth->getSessionIndex();
            if (!is_string($sessionIndex)) {
                throw new RealMeException(
                    'Invalid/Missing SessionIndex value in SAML response',
                    RealMeException::MISSING_SESSION_INDEX
                );
            }

            $attributes = $auth->getAttributes();
            if (!is_array($attributes)) {
                throw new RealMeException(
                    'Invalid/Missing attributes array in SAML response',
                    RealMeException::MISSING_ATTRIBUTES
                );
            }

            $federatedIdentity = $this->retrieveFederatedIdentity($auth);

            // We will have either a FLT or FIT, depending on integration type
            if ($this->config()->integration_type == self::TYPE_ASSERT) {
                $userTag = $this->retrieveFederatedIdentityTag($auth);
            } else {
                $userTag = $this->retrieveFederatedLogonTag($auth);
            }

            return User::create([
                'SPNameID' => $spNameId,
                'UserFederatedTag' => $userTag,
                'SessionIndex' => $sessionIndex,
                'Attributes' => $attributes,
                'FederatedIdentity' => $federatedIdentity,
            ]);
        } catch (Error $e) {
            // If the Exception code indicates there wasn't a response, we ignore it as it simply means the visitor
            // isn't authenticated yet. Otherwise, we re-throw the Exception
            if ($e->getCode() === Error::SAML_RESPONSE_NOT_FOUND) {
                return null;
            } else {
                throw $e;
            }
        }
    }

    /**
     * Clear the RealMe credentials from Session, called during Security->logout() overrides
     *
     * @param HTTPRequest $request
     * @return void
     */
    public function clearLogin(HTTPRequest $request)
    {
        $this->config()->__set('user_data', null);
        $session = $request->getSession();

        $session->set("RealMeBackURL", null);
        $session->set("RealMeErrorBackURL", null);
        $session->set("RealMe.SessionData", null);
        $session->set("RealMe.OriginalResponse", null);
        $session->set("RealMe.LastErrorMessage", null);
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * @return string A BackURL as specified originally when accessing /Security/login, for use after authentication
     */
    public function getBackURL(HTTPRequest $request)
    {
        $url = null;
        $session = $request->getSession();

        if ($session->get('RealMeBackURL')) {
            $url = $session->get('RealMeBackURL');
            $session->clear('RealMeBackURL'); // Ensure we don't redirect back to the same error twice
        }

        return $this->validSiteURL($url);
    }

    public function getErrorBackURL(HTTPRequest $request)
    {
        $url = null;
        $session = $request->getSession();

        if ($session->get('RealMeErrorBackURL')) {
            $url = $session->get('RealMeErrorBackURL');
            $session->clear('RealMeErrorBackURL'); // Ensure we don't redirect back to the same error twice
        }

        return $this->validSiteURL($url);
    }

    private function validSiteURL($url = null)
    {
        if (isset($url) && Director::is_site_url($url)) {
            $url = Director::absoluteURL($url);
        } else {
            // Spoofing attack or no back URL set, redirect to homepage instead of spoofing url
            $url = Director::absoluteBaseURL();
        }

        return $url;
    }

    /**
     * @param String $subdir A sub-directory where certificates may be stored for
     * a specific case
     * @return string|null Either the directory where certificates are stored,
     * or null if undefined
     */
    public function getCertDir($subdir = null)
    {

        // Trim prepended seprator to avoid absolute path
        $path = ltrim(ltrim($subdir, '/'), '\\');

        if ($certDir = Environment::getEnv('REALME_CERT_DIR')) {
            $path = $certDir . '/' . $path; // Duplicate slashes will be handled by realpath()
        }

        return realpath($path);
    }

    /**
     * Returns the appropriate AuthN Context, given the environment passed in. The AuthNContext may be different per
     * environment, and should be one of the strings as defined in the static {@link self::$authn_contexts} at the top
     * of this class.
     *
     * @param string $env The environment to return the AuthNContext for. Must be one of the RealMe environment names
     * @return string|null Returns the AuthNContext for the given $env, or null if no context exists
     */
    public function getAuthnContextForEnvironment($env)
    {
        return $this->getConfigurationVarByEnv('authn_contexts', $env);
    }

    /**
     * Returns the full path to the SAML signing certificate file, used by SimpleSAMLphp to sign all messages sent to
     * RealMe.
     *
     * @return string|null Either the full path to the SAML signing certificate file, or null if it doesn't exist
     */
    public function getSigningCertPath()
    {
        return $this->getCertPath('SIGNING');
    }

    public function getIdPCertPath()
    {
        $cfg = $this->config();
        $name = $this->getConfigurationVarByEnv('idp_x509_cert_filenames', $cfg->realme_env, $cfg->integration_type);

        return $this->getCertDir($name);
    }

    public function getSPCertContent($contentType = 'certificate')
    {
        return $this->getCertificateContents($this->getSigningCertPath(), $contentType);
    }

    public function getIdPCertContent()
    {
        return $this->getCertificateContents($this->getIdPCertPath());
    }

    /**
     * Returns the content of the SAML signing certificate. This is used by getAuth() and by RealMeSetupTask to produce
     * metadata XML files.
     *
     * @param string $certPath The filesystem path to where the certificate is stored on the filesystem
     * @param string $contentType Either 'certificate' or 'key', depending on which part of the file to return
     * @return string|null The content of the signing certificate
     */
    public function getCertificateContents($certPath, $contentType = 'certificate')
    {
        $text = null;

        if (!is_null($certPath)) {
            $certificateContents = file_get_contents($certPath);

            // If the file does not contain any header information and the content type is certificate, just return it
            if ($contentType == 'certificate' && !preg_match('/-----BEGIN/', $certificateContents)) {
                $text = $certificateContents;
            } else {
                // Otherwise, inspect the file and match based on the full contents
                if ($contentType == 'certificate') {
                    $pattern = '/-----BEGIN CERTIFICATE-----[\r\n]*([^-]*)[\r\n]*-----END CERTIFICATE-----/';
                } elseif ($contentType == 'key') {
                    $pattern = '/-----BEGIN [A-Z ]*PRIVATE KEY-----[\r\n]*([^-]*)[\r\n]*'
                        . '-----END [A-Z ]*PRIVATE KEY-----/';
                } else {
                    throw new InvalidArgumentException('Argument contentType must be either "certificate" or "key"');
                }

                // This is a PEM key, and we need to extract just the certificate, stripping out the private key etc.
                // So we search for everything between '-----BEGIN CERTIFICATE-----' and '-----END CERTIFICATE-----'
                preg_match(
                    $pattern,
                    $certificateContents,
                    $matches
                );

                if (isset($matches) && is_array($matches) && isset($matches[1])) {
                    $text = trim($matches[1]);
                }
            }
        }

        return $text;
    }

    /**
     * @param string $env The environment to return the entity ID for. Must be one of the RealMe environment names
     * @return string|null Either the assertion consumer service location, or null if information doesn't exist
     */
    public function getAssertionConsumerServiceUrlForEnvironment($env)
    {
        if (in_array($env, $this->getAllowedRealMeEnvironments()) === false) {
            return null;
        }

        $domain = $this->getMetadataAssertionServiceDomainForEnvironment($env);
        if (filter_var($domain, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        // Returns https://domain.govt.nz/Security/login/RealMe/acs
        return Controller::join_links($domain, Security::config()->get('login_url'), 'RealMe/acs');
    }

    /**
     * @return string|null The organisation name to be used in metadata XML output, or null if none exists
     */
    public function getMetadataOrganisationName()
    {
        $orgName = $this->config()->metadata_organisation_name;
        return (strlen($orgName) > 0) ? $orgName : null;
    }

    /**
     * @return string|null The organisation display name to be used in metadata XML output, or null if none exists
     */
    public function getMetadataOrganisationDisplayName()
    {
        $displayName = $this->config()->metadata_organisation_display_name;
        return (strlen($displayName) > 0) ? $displayName : null;
    }

    /**
     * @return string|null The organisation website URL to be used in metadata XML output, or null if none exists
     */
    public function getMetadataOrganisationUrl()
    {
        $url = $this->config()->metadata_organisation_url;
        return (strlen($url) > 0) ? $url: null;
    }

    /**
     * @return string[] The support contact details to be used in metadata XML output, with null values if they don't
     *                  exist
     */
    public function getMetadataContactSupport()
    {
        $company = $this->config()->metadata_contact_support_company;
        $firstNames = $this->config()->metadata_contact_support_firstnames;
        $surname = $this->config()->metadata_contact_support_surname;

        return array(
            'company' => (strlen($company) > 0) ? $company : null,
            'firstNames' => (strlen($firstNames) > 0) ? $firstNames : null,
            'surname' => (strlen($surname) > 0) ? $surname : null
        );
    }

    /**
     * The list of RealMe environments that can be used. By default, we allow mts, ite and production.
     * @return array
     */
    public function getAllowedRealMeEnvironments()
    {
        return $this->config()->allowed_realme_environments;
    }

    /**
     * The list of valid realme AuthNContexts
     * @return array
     */
    public function getAllowedAuthNContextList()
    {
        return $this->config()->allowed_authn_context_list;
    }

    /**
     * Returns the appropriate entity ID for RealMe, given the environment passed in. The entity ID may be different per
     * environment, and should be a full URL, including privacy realm and application name. For example, this may be:
     * https://www.agency.govt.nz/privacy-realm-name/application-name
     *
     * @return string|null Returns the entity ID for the current environment, or null if no entity ID exists
     */
    public function getSPEntityID()
    {
        return $this->getConfigurationVarByEnv('sp_entity_ids', $this->config()->realme_env);
    }

    private function getIdPEntityID()
    {
        $cfg = $this->config();
        return $this->getConfigurationVarByEnv('idp_entity_ids', $cfg->realme_env, $cfg->integration_type);
    }

    private function getSingleSignOnServiceURL()
    {
        $cfg = $this->config();
        return $this->getConfigurationVarByEnv('idp_sso_service_urls', $cfg->realme_env, $cfg->integration_type);
    }

    private function getRequestedAuthnContext()
    {
        return $this->getConfigurationVarByEnv('authn_contexts', $this->config()->realme_env);
    }

    /**
     * Returns the internal {@link Auth} object against which visitors are authenticated.
     *
     * @return Auth
     */
    public function getAuth(HTTPRequest $request = null)
    {
        if (isset($this->auth)) {
            return $this->auth;
        }

        if (!$request) {
            $request = self::getRequest();
            if (!$request) {
                throw new RealMeException('A request must be provided for session access');
            }
        }

        // Ensure onelogin is using the correct host, protocol and port incase a proxy is involved
        Utils::setSelfHost($request->getHeader('Host'));
        Utils::setSelfProtocol($request->getScheme());

        $port = null;
        if (isset($_SERVER['HTTP_X_FORWARDED_PORT'])) {
            $port = $_SERVER['HTTP_X_FORWARDED_PORT'];
        } elseif (isset($_SERVER['SERVER_PORT'])) {
            $port = $_SERVER['SERVER_PORT'];
        }

        if ($port) {
            Utils::setSelfPort($port);
        }

        $settings = [
            'strict' => true,
            'debug' => false,

            // Service Provider (this installation) configuration
            'sp' => [
                'entityId' => $this->getSPEntityID(),
                'x509cert' => $this->getSPCertContent('certificate'),
                'privateKey' => $this->getSPCertContent('key'),

                // According to RealMe messaging spec, must always be transient for assert; is irrelevant for login
                'NameIDFormat' => $this->getNameIdFormat(),

                'assertionConsumerService' => [
                    'url' => $this->getAssertionConsumerServiceUrlForEnvironment($this->config()->realme_env),
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST' // Always POST, not artifact binding
                ]
            ],

            // RealMe Identity Provider configuration
            'idp' => [
                'entityId' => $this->getIdPEntityID(),
                'x509cert' => $this->getIdPCertContent(),

                'singleSignOnService' => [
                    'url' => $this->getSingleSignOnServiceURL(),
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect'
                ]
            ],

            'security' => [
                'signatureAlgorithm' => 'http://www.w3.org/2000/09/xmldsig#rsa-sha1',
                'authnRequestsSigned' => true,
                'wantAssertionsEncrypted' => true,
                'wantAssertionsSigned' => true,

                'requestedAuthnContext' => [
                    $this->getRequestedAuthnContext()
                ]
            ]
        ];

        $this->auth = new Auth($settings);
        return $this->auth;
    }

    /**
     * @return string the required NameIDFormat to be included in metadata XML, based on the requested integration type
     */
    public function getNameIdFormat()
    {
        switch ($this->config()->integration_type) {
            case self::TYPE_ASSERT:
                return 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient';
                break;

            case self::TYPE_LOGIN:
            default:
                return 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent';
                break;
        }
    }

    /**
     * @param string $cfgName The static configuration value to get. This should be an array
     * @param string $env The environment to return the value for. Must be one of the RealMe environment names
     * @param string $integrationType The integration type (login or assert), if necessary, to determine return var
     * @throws InvalidArgumentException If the cfgVar doesn't exist, or is malformed
     * @return string|null Returns the value as defined in $cfgName for the given environment, or null if none exist
     */
    private function getConfigurationVarByEnv($cfgName, $env, $integrationType = null)
    {
        $value = null;

        if (in_array($env, $this->getAllowedRealMeEnvironments())) {
            $values = $this->config()->$cfgName;

            if (is_array($values) && isset($values[$env])) {
                $value = $values[$env];
            }
        }

        // If $integrationType is specified, then $value should be an array, with the array key being the integration
        // type and array value being the returned variable
        if (!is_null($integrationType) && is_array($value) && isset($value[$integrationType])) {
            $value = $value[$integrationType];
        } elseif (!is_null($integrationType)) {
            // Otherwise, we are expecting an integration type, but the value is not specified that way, error out
            throw new InvalidArgumentException(
                sprintf(
                    'Config value %s[%s][%s] not well formed (cfg var not an array)',
                    $cfgName,
                    $env,
                    $integrationType
                )
            );
        }

        if (is_null($value)) {
            throw new InvalidArgumentException(sprintf('Config value %s[%s] not set', $cfgName, $env));
        }

        return $value;
    }

    /**
     * @param string $certName The certificate name, either 'SIGNING' or 'MUTUAL'
     * @return string|null Either the full path to the certificate file, or null if it doesn't exist
     * @see self::getSigningCertPath()
     */
    private function getCertPath($certName)
    {
        $certPath = null;

        if (in_array($certName, array('SIGNING', 'MUTUAL'))) {
            $constName = sprintf('REALME_%s_CERT_FILENAME', strtoupper($certName));
            if ($filename = Environment::getEnv($constName)) {
                $certPath = $this->getCertDir($filename);
            }
        }

        // Ensure the file exists, if it doesn't then set it to null
        if (!is_null($certPath) && !file_exists($certPath)) {
            $certPath = null;
        }

        return $certPath;
    }

    /**
     * @param string $env The environment to return the domain name for. Must be one of the RealMe environment names
     * @return string|null Either the FQDN (e.g. https://www.realme-demo.govt.nz/) or null if none is specified
     */
    private function getMetadataAssertionServiceDomainForEnvironment($env)
    {
        return $this->getConfigurationVarByEnv('metadata_assertion_service_domains', $env);
    }

    /**
     * @param Auth $auth
     * @return string|null null if there's no FLT, or a string if there is one
     */
    private function retrieveFederatedLogonTag(Auth $auth)
    {
        return $auth->getNameId(); // RealMe FLT is a synonym of NameID
    }

    /**
     * @param Auth $auth
     * @return string|null null if there's not FIT, or a string if there is one
     */
    private function retrieveFederatedIdentityTag(Auth $auth)
    {
        $fit = null;
        $attributes = $auth->getAttributes();

        if (isset($attributes['urn:nzl:govt:ict:stds:authn:attribute:igovt:IVS:FIT'])) {
            $fit = $attributes['urn:nzl:govt:ict:stds:authn:attribute:igovt:IVS:FIT'][0];
        }

        return $fit;
    }

    /**
     * @param Auth $auth
     * @return FederatedIdentity|null
     * @throws RealMeException
     */
    private function retrieveFederatedIdentity(Auth $auth)
    {
        $federatedIdentity = null;
        $attributes = $auth->getAttributes();
        $nameId = $auth->getNameId();

        if (!isset($attributes[FederatedIdentity::SOURCE_XML]) && !isset($attributes[FederatedIdentity::SOURCE_JSON])) {
            return $federatedIdentity;
        }

        $source = isset($attributes[FederatedIdentity::SOURCE_XML])
            ? FederatedIdentity::SOURCE_XML
            : FederatedIdentity::SOURCE_JSON;

        // Identity information is encoded using 'Base 64 Encoding with URL and Filename Safe Alphabet'
        // For more info, review RFC3548, section 4 (https://tools.ietf.org/html/rfc3548#page-6)
        // Note: This is different to PHP's standard base64_decode() function, therefore we need to swap chars
        // to match PHP's expectations:
        // char 62 (-) becomes +
        // char 63 (_) becomes /

        $identity = $attributes[$source];

        if (!is_array($identity) || !isset($identity[0])) {
            throw new RealMeException(
                'Invalid identity response received from RealMe',
                RealMeException::INVALID_IDENTITY_VALUE
            );
        }

        // Switch from filename-safe alphabet base64 encoding to standard base64 encoding
        $identity = strtr($identity[0], '-_', '+/');
        $identity = base64_decode($identity, true);

        if (is_bool($identity) && !$identity) {
            // Strict base64_decode fails, either the identity didn't exist or was mangled during transmission
            throw new RealMeException(
                'Failed to parse safe base64 encoded identity',
                RealMeException::FAILED_PARSING_IDENTITY
            );
        }

        if ($source === FederatedIdentity::SOURCE_XML) {
            $identityDoc = new DOMDocument();
            if ($identityDoc->loadXML($identity)) {
                $federatedIdentity = FederatedIdentity::createFromXML($identityDoc, $nameId);
            }
        }

        if ($source === FederatedIdentity::SOURCE_JSON) {
            $federatedIdentity = FederatedIdentity::createFromJSON($identity, $nameId);
        }

        return $federatedIdentity;
    }

    /**
     * Finds a human-readable error message based on the error code provided in the RealMe SAML response
     *
     * @return string|null The human-readable error message, or null if one can't be found
     */
    private function findErrorMessageForCode($errorCode)
    {
        $message = null;
        $messageOverrides = $this->config()->realme_error_message_overrides;

        switch ($errorCode) {
            case self::ERR_AUTHN_FAILED:
                $message = _t(self::class . '.ERROR_AUTHNFAILED', 'You have chosen to leave RealMe.');
                break;

            case self::ERR_TIMEOUT:
                $message = _t(self::class . '.ERROR_TIMEOUT', 'Your RealMe session has timed out – please try again.');
                break;

            case self::ERR_INTERNAL_ERROR:
                $message = _t(
                    self::class . '.ERROR_INTERNAL',
                    'RealMe was unable to process your request due to a RealMe internal error. Please try again. ' .
                        'If the problem persists, please contact the RealMe Help Desk. From New Zealand dial ' .
                        '0800 664 774 (toll free), from overseas dial +64 4 462 0674 (overseas call charges apply).'
                );
                break;

            case self::ERR_NO_AVAILABLE_IDP:
                $message = _t(
                    self::class . '.ERROR_NOAVAILABLEIDP',
                    'RealMe reported that the TXT service or the token service is not available. You may try again ' .
                        'later. If the problem persists, please contact the RealMe Help Desk. From New Zealand dial ' .
                        '0800 664 774 (toll free), from overseas dial +64 4 462 0674 (overseas call charges apply).'
                );
                break;

            case self::ERR_REQUEST_UNSUPPORTED:
                $message = _t(
                    self::class . '.ERROR_REQUESTUNSUPPORTED',
                    'RealMe reported a serious application error with the message \'Request Unsupported\'. Please try' .
                        ' again later. If the problem persists, please contact the RealMe Help Desk. From New Zealand' .
                        ': 0800 664 774 (toll free), from overseas dial +64 4 462 0674 (overseas call charges apply).'
                );
                break;

            case self::ERR_NO_PASSIVE:
                $message = _t(
                    self::class . '.ERROR_NOPASSIVE',
                    'RealMe reported a serious application error with the message \'No Passive\'. Please try again ' .
                        'later. If the problem persists, please contact the RealMe Help Desk. From New Zealand: 0800 ' .
                        '664 774 (toll free), from overseas dial +64 4 462 0674 (overseas call charges apply).'
                );
                break;

            case self::ERR_REQUEST_DENIED:
                $message = _t(
                    self::class . '.ERROR_REQUESTDENIED',
                    'RealMe reported a serious application error with the message \'Request Denied\'. Please try ' .
                        'again later. If the problem persists, please contact the RealMe Help Desk. From New Zealand:' .
                        ' 0800 664 774 (toll free), from overseas dial +64 4 462 0674 (overseas call charges apply).'
                );
                break;

            case self::ERR_UNSUPPORTED_BINDING:
                $message = _t(
                    self::class . '.ERROR_UNSUPPORTEDBINDING',
                    'RealMe reported a serious application error with the message \'Unsupported Binding\'. Please ' .
                        'try again later. If the problem persists, please contact the RealMe Help Desk. From New ' .
                        'Zealand: 0800 664 774 (toll free), from overseas dial +64 4 462 0674 (overseas call charges ' .
                        'apply).'
                );
                break;

            case self::ERR_UNKNOWN_PRINCIPAL:
                $message = _t(
                    self::class . '.ERROR_UNKNOWNPRINCIPAL',
                    'You are unable to use RealMe to verify your identity if you do not have a RealMe account. ' .
                        'Visit the RealMe home page for more information and to create an account.'
                );
                break;

            case self::ERR_NO_AUTHN_CONTEXT:
                $message = _t(
                    self::class . '.ERROR_NOAUTHNCONTEXT',
                    'RealMe reported a serious application error with the message \'No AuthN Context\'. Please try ' .
                        'again later. If the problem persists, please contact the RealMe Help Desk. From New Zealand:' .
                        ' 0800 664 774 (toll free), from overseas dial +64 4 462 0674 (overseas call charges apply).'
                );
                break;

            default:
                $message = _t(
                    self::class . '.ERROR_GENERAL',
                    'RealMe reported a serious application error. Please try again later. If the problem persists, ' .
                        'please contact the RealMe Help Desk. From New Zealand: 0800 664 774 (toll free), from ' .
                        'overseas dial +64 4 462 0674 (overseas call charges apply).'
                );
                break;
        }

        // Allow message overrides if they exist
        if (array_key_exists($errorCode, $messageOverrides) && !is_null($messageOverrides[$errorCode])) {
            $message = $messageOverrides[$errorCode];
        }

        return $message;
    }
}
