<?php
/**
 * Class RealMeAuthenticator
 *
 *
 */
class RealMeAuthenticator extends Authenticator {
	public static function get_login_form(Controller $controller) {
		return RealMeLoginForm::create($controller, 'LoginForm');
	}

	/**
	 * Ensures that enough detail has been configured to allow this authenticator to function properly. Specifically,
	 * this checks the following:
	 *
	 * @todo Implement this - needs to check certs are in place, vendor-specific RealMe details are inserted etc.
	 * @return bool false if the authenticator shouldn't be registered
	 */
	protected static function on_register() {
		return true;
	}

	public static function get_name() {
		return _t('RealMeAuthenticator.TITLE', 'RealMe Account');
	}
}