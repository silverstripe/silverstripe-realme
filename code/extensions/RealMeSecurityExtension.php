<?php

class RealMeSecurityExtension extends Extension
{
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

            if ($authenticated === true) {
                $authData = $this->service->getAuthData();

                // If more session vars are set here, they must be cleared in realmeLogout()
                Session::set('RealMe.SessionData', serialize($authData));
                Session::set('RealMe.OriginalResponse', $_POST['SAMLResponse']);

                // If a redirect has not already been set, then redirect to the default BackURL
                if (!$this->owner->redirectedTo()) {
                    return $this->owner->redirect($this->service->getBackURL());
                }
            } else {

                if (is_string($this->service->getLastError())) {
                    Session::set('RealMe.LastErrorMessage', $this->service->getLastError());

                    // Redirect to the 'Error Back URL' if set
                    $backUrl = $this->service->getErrorBackURL();
                    if ($backUrl) {
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
        } catch (Exception $e) {
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
}
