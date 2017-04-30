<?php

class RealMeSecurityExtension extends Extension
{
    /**
     * Error constants used for business logic and switching error messages
     */
    const AUTHN_FAILED = 'urn:oasis:names:tc:SAML:2.0:status:AuthnFailed';
    const TIMEOUT = 'urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:status:Timeout';
    const UNKNOWN_PRINCIPAL = 'urn:oasis:names:tc:SAML:2.0:status:UnknownPrincipal';
    const INTERNAL_ERROR = 'urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:status:InternalError';
    const NO_AVAILABLE_IDP = 'urn:oasis:names:tc:SAML:2.0:status:NoAvailableIDP';
    const GENERAL_ERROR = '';

    private static $allowed_actions = array(
        'realme'
    );

    private static $dependencies = array(
        'service' => '%$RealMeService'
    );

    /**
     * @var RealMeService
     */
    public $service;

    /**
     * Support the default security logout procedure by ensuring that RealMe hooks are cleared when the standard logout
     * is called.
     *
     * @param $request
     * @param $action
     */
    public function beforeCallActionHandler($request, $action)
    {
        switch ($action) {
            case "logout":
                $this->service->clearLogin();
                break;
        }
    }

    /**
     * Invalidate the current session, clearing the login state with RealMe as well as any state within SilverStripe
     *
     * @param bool $redirect If true, Security::logout() will redirect the user back
     *
     * @todo At the moment we would always redirectBack(), do we want to support BackURL in these contexts?
     */
    private function realMeLogout($redirect = true)
    {
        Session::clear('RealMe');
        $this->service->clearLogin();

        if ($redirect) {
            return $this->owner->logout($redirect);
        } else {
            $this->owner->logout();
            return $this->owner->redirectBack();
        }
    }

    /**
     * All publicly-accessible URLs are routed through this method. Possible method include:
     * - acs: User is redirected here after authenticating with RealMe
     * - error: Called when an error is logged by SimpleSAMLphp, we redirect to the login form with a messageset defined
     * - logout: Ensures the user is logged out from RealMe, as well as this website (via Security::logout())
     */
    public function realme()
    {
        $action = $this->owner->getRequest()->param('ID');

        switch ($action) {
            case 'acs':
                return $this->realMeACS();

            case 'error':
                return $this->realMeErrorHandler();

            case 'logout':
                return $this->realMeLogout();

            default:
                throw new InvalidArgumentException(sprintf("Unknown URL param '%s'", Convert::raw2xml($action)));
        }
    }

    /**
     * After a user is authenticated with realme, we attempt to verify the session.
     *
     * @return SS_HTTPResponse
     */
    private function realMeACS()
    {
        try {
            $authenticated = $this->service->enforceLogin();

            if($authenticated === true) {
                $authData = $this->service->getAuthData();

                // If more session vars are set here, they must be cleared in realmeLogout()
                Session::set('RealMe.SessionData', serialize($authData));
                Session::set('RealMe.OriginalResponse', $_POST['SAMLResponse']);
                return $this->owner->redirect($this->service->getBackURL());
            } else {
                if(is_string($this->service->getLastError())) {
                    Session::set('RealMe.LastErrorMessage', $this->service->getLastError());

                    // Redirect to the 'Error Back URL' if set
                    $backUrl = $this->service->getErrorBackURL();
                    if($backUrl) {
                        return $this->owner->redirect($backUrl);
                    } else {
                        // Fallback to homepage
                        return $this->owner->redirect('/');
                    }
                }

                throw new RealMeException(
                    'Attempted access of RealMeSecurityExtension->realMeACS() without SAML response',
                    RealMeException::MISSING_AUTHN_RESPONSE
                );
            }
        } catch(Exception $e) {
            $msg = sprintf(
                'Error during RealMe authentication process. Code: %d, Message: %s',
                $e->getCode(),
                $e->getMessage()
            );

            SS_Log::log($msg, SS_Log::ERR);
        }

        return Security::permissionFailure(
            $this->owner,
            _t(
                'RealMeSecurityExtension.LOGINFAILURE',
                'Unfortunately we\'re not able to log you in through RealMe right now. Please try again shortly.'
            )
        );
    }

    /**
     * Process the error/Exception returned from SimpleSaml and return an appropriate error to the user.
     *
     * @return SS_HTTPResponse
     */
    private function realMeErrorHandler()
    {
        // Error handling, to prevent infinite login loops if there was an internal error with SimpleSAMLphp
        if ($exceptionId = $this->owner->getRequest()->getVar('SimpleSAML_Auth_State_exceptionId')) {
            if (is_string($exceptionId) && strlen($exceptionId) > 1) {
                $authState = SimpleSAML_Auth_State::loadExceptionState($exceptionId);
                if (true === array_key_exists('SimpleSAML_Auth_State.exceptionData', $authState)
                    && $authState['SimpleSAML_Auth_State.exceptionData'] instanceof sspmod_saml_Error) {
                    $exception = $authState['SimpleSAML_Auth_State.exceptionData'];
                    $message = $this->getErrorMessage($exception);

                    SS_Log::log(
                        sprintf('Error while validating RealMe authentication details: %s', $message),
                        SS_Log::ERR
                    );

                    return Security::permissionFailure($this->owner, $message);
                }
            }
        }

        SS_Log::log('Unknown error while attempting to parse RealMe authentication', SS_Log::ERR);

        return Security::permissionFailure(
            $this->owner,
            _t('RealMeSecurityExtension.GENERAL_ERROR', '',
                array('errorMsg' => 'Unknown')
            )
        );
    }

    /**
     * Return the realme error message associated with a SimpleSAML error.
     *
     * @param $exception sspmod_saml_Error
     *
     * @return string
     */
    private function getErrorMessage($exception)
    {
        switch ($exception->getSubStatus()) {

            // if the identity provider goes down, it usually means something like the SMS service is down.
            case self::NO_AVAILABLE_IDP:
                return _t('RealMeSecurityExtension.NO_AVAILABLE_IDP', '', array('errorMsg' => $exception->getMessage()));

            // Usually means your entity ID is miss-matched against this server metadata (re-upload metadata),
            // but can mean first time users need to use a specific setting.
            case self::UNKNOWN_PRINCIPAL:
                return _t('RealMeSecurityExtension.UNKNOWN_PRINCIPAL', '', array('errorMsg' => $exception->getMessage()));

            // Something went terribly wrong at realme.
            case self::INTERNAL_ERROR:
                return _t('RealMeSecurityExtension.INTERNAL_ERROR', '', array('errorMsg' => $exception->getMessage()));

            // General time out
            case self::TIMEOUT:
                return _t('RealMeSecurityExtension.TIMEOUT', '', array('errorMsg' => $exception->getMessage()));

            // They logged out from realme.
            case self::AUTHN_FAILED:
                return _t('RealMeSecurityExtension.AUTHN_FAILED', '', array('errorMsg' => $exception->getMessage()));

            // Give the general error for all others: REQUEST_UNSUPPORTED,UNSUPPORTED_BINDING,,REQUEST_DENIED or unknown.
            default :
                return _t('RealMeSecurityExtension.GENERAL_ERROR',
                    "RealMe reported a serious application error with the message [{errorMsg}]. " .
                    "Please try again later. If the problem persists, please contact RealMe Help " .
                    "Desk on 0800 664 774.",
                    array('errorMsg' => $exception->getMessage())
                );
        }
    }
}
