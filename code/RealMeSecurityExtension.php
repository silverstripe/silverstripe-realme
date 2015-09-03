<?php

class RealMeSecurityExtension extends Extension {
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
	 * Support the default security logout procedure by ensuring that realme hooks are cleared when the standard logout
	 * is called.
	 *
	 * @param $request
	 * @param $action
	 */
	public function beforeCallActionHandler($request, $action){
		switch($action){
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
	private function realMeLogout($redirect = true) {
		$this->service->clearLogin();

		if($redirect) {
			return $this->owner->logout($redirect);
		} else {
			return $this->owner->redirectBack();
		}
	}

	/**
	 * All publicly-accessible URLs are routed through this method. Possible method include:
	 * - acs: User is redirected here after authenticating with RealMe
	 * - error: Called when an error is logged by SimpleSAMLphp, we redirect to the login form with a messageset defined
	 * - logout: Ensures the user is logged out from RealMe, as well as this website (via Security::logout())
	 */
	public function realme() {
		$action = $this->owner->getRequest()->param('ID');

		switch($action) {
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

	private function realMeACS() {
		$loggedIn = $this->service->enforceLogin();

		if($loggedIn) {
			return $this->owner->redirect($this->service->getBackURL());
		} else {
			return Security::permissionFailure(
				$this->owner,
				_t(
					'RealMeSecurityExtension.LOGINFAILURE',
					'Unfortunately we\'re not able to log you in through RealMe right now.'
				)
			);
		}
	}

	private function realMeErrorHandler() {
		// Error handling, to prevent infinite login loops if there was an internal error with SimpleSAMLphp
		if($exceptionId = $this->owner->getRequest()->getVar('SimpleSAML_Auth_State_exceptionId')) {
			if(is_string($exceptionId) && strlen($exceptionId) > 1) {
				//				$session = SimpleSAML_Session::getSessionFromRequest();
				//				$data = $session->getData('SimpleSAML_Auth_State', $exceptionId);
				$authState = SimpleSAML_Auth_State::loadExceptionState($exceptionId);
				if(isset($authState['SimpleSAML_Auth_State.exceptionData'])) {
					$exception = $authState['SimpleSAML_Auth_State.exceptionData'];
					if($exception instanceof sspmod_saml_Error) {
						$message = $exception->getStatusMessage();
					} elseif($exception instanceof SimpleSAML_Error_Exception) {
						$message = $exception->getMessage();
					}

					if(isset($message)) {
						SS_Log::log(
							sprintf('Error while validating RealMe authentication details: %s', $message),
							SS_Log::ERR
						);

						return Security::permissionFailure(
							$this->owner,
							"Sorry, we couldn't verify your RealMe account. Please try again."
						);
					}
				}
			}
		}

		SS_Log::log('Unknown error while attempting to parse RealMe authentication errors', SS_Log::ERR);
		return Security::permissionFailure(
			$this->owner,
			"Sorry, we couldn't verify your RealMe account. Please try again."
		);
	}
}