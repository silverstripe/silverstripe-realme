<?php

namespace SilverStripe\RealMe\Authenticator;

use Exception;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\RealMe\Exception as RealMeException;
use SilverStripe\RealMe\Extension\MemberExtension;
use SilverStripe\RealMe\RealMeService;
use SilverStripe\Security\Member;
use SilverStripe\Security\AuthenticationHandler;
use SilverStripe\Security\Security;

class LoginHandler extends RequestHandler
{
    private static $dependencies = [
        'service' => '%$' . RealMeService::class,
    ];

    /**
     * @var array
     */
    private static $url_handlers = [
        '' => 'login',
    ];

    /**
     * @var array
     * @config
     */
    private static $allowed_actions = [
        'login',
        'acs',
    ];

    /**
     * @var string Called link on this handler
     */
    private $link;

    /**
     * @var RealMeService
     */
    protected $service;

    /**
     * @param string $link The URL to recreate this request handler
     */
    public function __construct($link)
    {
        $this->link = $link;
        parent::__construct();
    }

    /**
     * Return a link to this request handler.
     * The link returned is supplied in the constructor
     * @param null|string $action
     * @return string
     */
    public function link($action = null)
    {
        if ($action) {
            return Controller::join_links($this->link, $action);
        }

        return $this->link;
    }

    /**
     * URL handler for the log-in screen
     *
     * @return array
     */
    public function login()
    {
        return [
            'Form' => $this->loginForm(),
        ];
    }

    public function loginForm()
    {
        return LoginForm::create($this, 'acs');
    }

    public function acs(HTTPRequest $request)
    {
        try {
            $authenticated = $this->service->enforceLogin($request);

            $session = $request->getSession();

            if ($authenticated === true) {
                $authData = $this->service->getAuthData();

                // If more session vars are set here, they must be cleared in realmeLogout()
                $session->set('RealMe.SessionData', serialize($authData));
                $session->set('RealMe.OriginalResponse', $request->postVar('SAMLResponse'));

                $realMeServiceConfig = RealMeService::config();
                if ($realMeServiceConfig->get('sync_with_local_member_database') === true) {
                    if ($realMeServiceConfig->get('integration_type') === RealMeService::TYPE_ASSERT) {
                        throw new RealMeException(
                            'NameID is transient for ASSERT - it cannot be used to identify a user between sessions.',
                            RealMeException::PERSISTING_TRANSIENT_ID
                        );
                    }
                    if (!Member::has_extension(MemberExtension::class)) {
                        throw new RealMeException(
                            'The RealMe MemberExtension should be used when syncing with a local database',
                            RealMeException::MISSING_MEMBER_EXTENSION
                        );
                    }

                    if (!$authData->getMember()->isInDb()) {
                        $authData->getMember()->write();
                    }
                    if ($realMeServiceConfig->get('login_member_after_authentication') === true) {
                        Injector::inst()->get(AuthenticationHandler::class)->login($authData->getMember());
                    }
                }

                // Redirect to the default BackURL
                return $this->redirect($this->getBackURL() ?: $this->service->getBackURL($request));
            } else {
                if (is_string($this->service->getLastError())) {
                    $session->set('RealMe.LastErrorMessage', $this->service->getLastError());

                    // Redirect to the 'Error Back URL' if set
                    $backUrl = $this->service->getErrorBackURL($request);
                    if ($backUrl) {
                        return $this->redirect($backUrl);
                    } else {
                        // Fallback to homepage
                        return $this->redirect('/');
                    }
                }
                throw new RealMeException(
                    'Attempted access of ACS action without SAML response',
                    RealMeException::MISSING_AUTHN_RESPONSE
                );
            }
        } catch (Exception $e) {
            $msg = sprintf(
                'Error during RealMe authentication process. Code: %d, Message: %s',
                $e->getCode(),
                $e->getMessage()
            );

            Injector::inst()->get(LoggerInterface::class)->info($msg);
        }

        return Security::permissionFailure(
            $this,
            _t(
                RealMeService::class . '.LOGINFAILURE',
                'Unfortunately we\'re not able to log you in through RealMe right now. Please try again shortly.'
            )
        );
    }
}
