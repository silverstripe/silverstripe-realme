<?php
class RealMeLoginForm extends LoginForm {

	/**
	 * @var array
	 */
	private static $allowed_actions = array(
		'redirectToRealMe'
	);

	/**
	 * @var string
	 */
	protected $authenticator_class = 'RealMeAuthenticator';

	/**
	  * Returns an instance of this class
	  *
	  * @param Controller
	  * @param String
	  * @return RealMeLoginForm
	  */
	public function __construct($controller, $name) {
		$fields = new FieldList(array(
			new HiddenField('AuthenticationMethod', null, $this->authenticator_class)
		));

		$actions = new FieldList(array(
			FormAction::create('redirectToRealMe', _t('RealMeLoginForm.LOGINBUTTON', 'LoginAction'))
				->setUseButtonTag(true)
				->setButtonContent('<span class="realme_button_padding">Login or register with RealMe<span class="realme_icon_new_window"></span> <span class="realme_icon_padlock"></span>')
				->setAttribute('class', 'realme_button')
		));

		// Taken from MemberLoginForm
		if(isset($_REQUEST['BackURL'])) {
			$backURL = $_REQUEST['BackURL'];
		} elseif(Session::get('BackURL')) {
			$backURL = Session::get('BackURL');
		}

		if(isset($backURL)) {
			// Ensure that $backURL isn't redirecting us back to login form or a RealMe authentication page
			if(strpos($backURL, 'Security/login') === false && strpos($backURL, 'Security/realme') === false) {
				$fields->push(new HiddenField('BackURL', 'BackURL', $backURL));
			}
		}

		// optionally include requirements {@see /realme/_config/config.yml}
		if(Config::inst()->get('RealMeLoginForm', 'realme_include_jquery')) {
			Requirements::javascript(THIRDPARTY_DIR."/jquery/jquery.js");
		}

		if(Config::inst()->get('RealMeLoginForm', 'realme_include_javascript')) {
			Requirements::javascript(REALME_MODULE_PATH."/javascript/realme.js");
		}

		if(Config::inst()->get('RealMeLoginForm', 'realme_include_css')) {
			Requirements::css(REALME_MODULE_PATH."/css/realme.css");
		}

		parent::__construct($controller, $name, $fields, $actions);
	}

	/**
	 * Returns
	 *
	 * @param
	 * @return
	 */
	public function redirectToRealMe($data, Form $form) {
		/** @var RealMeService $service */
		$service = Injector::inst()->get('RealMeService');

		// If there's no service, ensure we throw a predictable error
		if(!$service) return $this->controller->httpError(500);

		// This will either redirect to Real Me (via SimpleSAMLphp) or return true/false to indicate logged in state
		$loggedIn = $service->enforceLogin();

		if($loggedIn) {
			return $this->controller->redirect($service->getBackURL());
		} else {
			return Security::permissionFailure(
				$this->controller,
				_t(
					'RealMeSecurityExtension.LOGINFAILURE',
					'Unfortunately we\'re not able to authenticate you via RealMe right now.'
				)
			);
		}
	}

	/**
	 * Example function exposing a realme config option to the login form template
	 *
	 * Theme options are: default, dark & light. These are appended to a css class
	 * on the template which is applied to the element .realme_widget.
	 *
	 * @see realme/_config/config.yml
	 * @see realme/templates/Includes/RealMeLoginForm.ss
	 *
	 * @return string
	 */
	public function getRealMeWidgetTheme() {
		if($theme = $this->stat('realme_widget_theme')) {
			return $theme;
		}
		return 'default';
	}
}