<?php

class RealMeLoginForm extends LoginForm
{
    /**
     * @config
     * @var bool true if you want the RealMe login form to include jQuery, false if you're including it yourself
     */
    private static $include_jquery;

    /**
     * @config
     * @var bool true if you want the RealMe login form JS to be included, false if you're including it yourself
     */
    private static $include_javascript;

    /**
     * @config
     * @var bool true if you want the RealMe login form CSS to be included, false if you're including it yourself
     */
    private static $include_css;

    /**
     * @config
     * @var string Widget theme can be one of 'default', 'light', or 'dark'. Default is 'default'.
     */
    private static $widget_theme;

    /**
     * @var array
     */
    private static $allowed_actions = array(
        'redirectToRealMe'
    );

    /**
     * @var string The authentication class tied to this login form
     */
    protected $authenticator_class = 'RealMeAuthenticator';

    /**
     * Returns an instance of this class
     *
     * @param Controller $controller
     * @param string $name
     */
    public function __construct($controller, $name)
    {
        $fields = new FieldList(array(
            new HiddenField('AuthenticationMethod', null, $this->authenticator_class)
        ));

        $loginButtonContent = ArrayData::create(array(
            'Label' => _t('RealMeLoginForm.LOGINBUTTON', 'Login or register with RealMe')
        ))->renderWith('RealMeLoginButton');

        $actions = new FieldList(array(
            FormAction::create('redirectToRealMe', _t('RealMeLoginForm.LOGINBUTTON', 'LoginAction'))
                ->setUseButtonTag(true)
                ->setButtonContent($loginButtonContent)
                ->setAttribute('class', 'realme_button')
        ));

        // Taken from MemberLoginForm
        if (isset($_REQUEST['BackURL'])) {
            $backURL = $_REQUEST['BackURL'];
        } elseif (Session::get('BackURL')) {
            $backURL = Session::get('BackURL');
        }

        if (isset($backURL)) {
            // Ensure that $backURL isn't redirecting us back to login form or a RealMe authentication page
            if (strpos($backURL, 'Security/login') === false && strpos($backURL, 'Security/realme') === false) {
                $fields->push(new HiddenField('BackURL', 'BackURL', $backURL));
            }
        }

        // optionally include requirements {@see /realme/_config/config.yml}
        if ($this->config()->include_jquery) {
            Requirements::javascript(THIRDPARTY_DIR . "/jquery/jquery.js");
        }

        if ($this->config()->include_javascript) {
            Requirements::javascript(REALME_MODULE_PATH . "/javascript/realme.js");
        }

        if ($this->config()->include_css) {
            Requirements::css(REALME_MODULE_PATH . "/css/realme.css");
        }

        parent::__construct($controller, $name, $fields, $actions);
    }

    /**
     * Process login form submission
     *
     * @param array $data
     * @param Form $form
     * @return SS_HTTPResponse|void If successfully processed, returns void (SimpleSAMLphp redirects to RealMe)
     * @throws SS_HTTPResponse_Exception
     */
    public function redirectToRealMe($data, Form $form)
    {
        /** @var RealMeService $service */
        $service = Injector::inst()->get('RealMeService');

        // If there's no service, ensure we throw a predictable error
        if (null === $service) {
            return $this->controller->httpError(500);
        }

        // This will either redirect to RealMe (via SimpleSAMLphp) or return true/false to indicate logged in state
        $loggedIn = $service->enforceLogin();
        if (true === $loggedIn) {
            return $this->controller->redirect($service->getBackURL());
        }

        return Security::permissionFailure(
            $this->controller,
            _t(
                'RealMeSecurityExtension.LOGINFAILURE',
                'Unfortunately we\'re not able to authenticate you via RealMe right now.'
            )
        );
    }

    /**
     * Example function exposing a RealMe config option to the login form template
     *
     * Theme options are: default, light & dark. These are appended to a css class
     * on the template which is applied to the element .realme_widget.
     *
     * @see realme/_config/config.yml
     * @see realme/templates/Includes/RealMeLoginForm.ss
     *
     * @return string
     */
    public function getRealMeWidgetTheme()
    {
        if ($theme = $this->config()->widget_theme) {
            return $theme;
        }

        return 'default';
    }
}
