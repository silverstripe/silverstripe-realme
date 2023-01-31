<?php

namespace SilverStripe\RealMe\Authenticator;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\RealMe\Authenticator;
use SilverStripe\RealMe\RealMeService;
use SilverStripe\Security\LoginForm as BaseLoginForm;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;

class LoginForm extends BaseLoginForm
{
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
     * @config
     * @var string The service name to display in the login box ("To access the [online service], you need a RealMe
     * login.")
     */
    private static $service_name_1 = null;

    /**
     * @config
     * @var string The service name to display in the What's RealMe popup header ("To log in to [this service] you need
     * a RealMe login.")
     */
    private static $service_name_2 = null;

    /**
     * @config
     * @var string The service name to display in the What's RealMe popup text ("[This service] uses RealMe login.")
     */
    private static $service_name_3 = null;

    /**
     * @var string The authentication class tied to this login form
     */
    protected $authenticator_class = Authenticator::class;

    /**
     * Returns an instance of this class
     *
     * @param Controller $controller
     * @param string $name
     */
    public function __construct($controller, $name)
    {
        $this->setController($controller);

        $service = Injector::inst()->get(RealMeService::class);
        $integrationType = $service->config()->integration_type;

        if ($integrationType === RealMeService::TYPE_ASSERT) {
            $this->template = self::class . '/RealMeAssertForm';
        }

        if ($this->config()->include_javascript) {
            Requirements::javascript('silverstripe/realme:client/dist/js/realme.js');
        }

        if ($this->config()->include_css) {
            Requirements::css('silverstripe/realme:client/dist/styles/realme.css');
        }

        parent::__construct($controller, $name, $this->getFormFields(), $this->getFormActions());
    }

    /**
     * Example function exposing a RealMe config option to the login form template
     *
     * Theme options are: default, light & dark. These are appended to a css class
     * on the template which is applied to the element .realme_widget.
     *
     * @see realme/_config/config.yml
     * @see realme/templates/Includes/LoginForm.ss
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

    /**
     * Gets the service name based on either a config value, or falling back to the $Title specified in SiteConfig
     * @param string $name The service name to get from config
     * @return string
     */
    private function getServiceName($name = 'service_name_1')
    {
        if ($this->config()->$name) {
            return $this->config()->$name;
        } else {
            return SiteConfig::current_site_config()->Title;
        }
    }

    public function getServiceName1()
    {
        return $this->getServiceName('service_name_1');
    }

    public function getServiceName2()
    {
        return $this->getServiceName('service_name_2');
    }

    public function getServiceName3()
    {
        return $this->getServiceName('service_name_3');
    }

    /**
     * Returns the last error message that the RealMe service provided, if any
     * @return string|null
     */
    public function RealMeLastError()
    {
        $session = $this->getRequest()->getSession();

        $message = $session->get('RealMe.LastErrorMessage');
        $session->clear('RealMe.LastErrorMessage');

        return $message;
    }

    public function HasRealMeLastError()
    {
        return $this->getRequest()->getSession()->get('RealMe.LastErrorMessage') !== null;
    }

    /**
     * Return the title of the form for use in the frontend
     * For tabs with multiple login methods, for example.
     * This replaces the old `get_name` method
     * @return string
     */
    public function getAuthenticatorName()
    {
        return _t(self::class . '.AUTHENTICATOR_NAME', 'RealMe Account');
    }

    /**
     * Required FieldList creation on a LoginForm
     *
     * @return FieldList
     */
    protected function getFormFields()
    {
        return FieldList::create(array(
            HiddenField::create('AuthenticationMethod', null, $this->authenticator_class)
        ));
    }

    /**
     * Required FieldList creation for the login actions on this LoginForm
     *
     * @return FieldList
     */
    protected function getFormActions()
    {
        /** @var RealMeService $service */
        $service = Injector::inst()->get(RealMeService::class);

        $integrationType = $service->config()->integration_type;

        if ($integrationType === RealMeService::TYPE_ASSERT) {
            $loginButtonContent = ArrayData::create(array(
                'Label' => _t(
                    self::class . '.ASSERTLOGINBUTTON',
                    'Share your details with {orgname}',
                    ['orgname' => $service->config()->metadata_organisation_display_name]
                ),
                'ShowNewWindowIcon' => false
            ))->renderWith(self::class . '/RealMeLoginButton');
        } else {
            // Login button
            $loginButtonContent = ArrayData::create(array(
                'Label' => _t(self::class . '.LOGINBUTTON', 'Login'),
                'ShowNewWindowIcon' => true
            ))->renderWith(self::class . '/RealMeLoginButton');
        }

        return FieldList::create(array(
            FormAction::create('doLogin', _t(self::class . '.LOGINBUTTON', 'Login'))
                ->setUseButtonTag(true)
                ->setButtonContent($loginButtonContent)
                ->setAttribute('class', 'realme_button')
        ));
    }
}
