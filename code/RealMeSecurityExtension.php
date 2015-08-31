<?php

class RealMeSecurityExtension extends Extension {

	/**
	 * @var RealMeService
	 */
	public $service;

	private static $allowed_actions = array(
		'realmevalidate',
		'realmeacs',
		'realmelogout',
	);

	private static $dependencies = array(
		'service' => '%$RealMeService'
	);

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
	 * @return SS_HTTPResponse|void
	 */
	public function realmevalidate() {
		$loggedIn = $this->service->enforceLogin();

		if(true === $loggedIn) {
			return $this->owner->redirect(Director::baseURL());
		}

		return Security::permissionFailure(
			$this->owner,
			_t(
				'RealMeSecurityExtension.LOGINFAILURE',
				'Unfortunately we\'re not able to log you in through RealMe right now.'
			)
		);
	}

	/**
	 * Invalidate the current sessions realme authentication
	 *
	 * @param bool|true $redirect
	 */
	public function realmelogout($redirect = true){
		$this->service->clearLogin();
		$this->owner->logout($redirect);
	}

	/**
	 * RealMe returns the user to this method, which is used to proxy SimpleSAMLphp, so that it can be easily replaced
	 * in the future if required.
	 */
	public function realmeacs() {
		echo 1;
		// redirect to simplesaml path /module.php/saml/sp/saml2-acs.php/default-sp
	}
}