<?php
class RealMeController extends Controller {
	private static $allowed_actions = array(
		'login'
	);

	private static $dependencies = array(
		'service' => '%$RealMeService'
	);

	/**
	 * @var RealMeService
	 */
	public $service;

	public function login() {
		$loggedIn = $this->service->enforceLogin();

		if($loggedIn) {
			return $this->redirect(Director::baseURL());
		} else {
			return Security::permissionFailure(
				$this->controller,
				_t(
					'RealMeController.LOGINFAILURE',
					'Unfortunately we\'re not able to log you in through RealMe right now.'
				)
			);
		}
	}
}