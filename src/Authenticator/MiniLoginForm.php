<?php

namespace SilverStripe\RealMe\Authenticator;

use SilverStripe\Control\Controller;
use SilverStripe\RealMe\Authenticator\LoginForm;

class MiniLoginForm extends LoginForm
{
    /**
     * @var string The position at which the 'What's RealMe?' popup appears on hover. Can be either 'left' or 'right'.
     * @see self::setMiniLoginFormPopupPosition()
     */
    private $popupPosition = 'left';

    public function __construct($controller, $name)
    {
        parent::__construct($controller, $name);
        $this->setFormMethod('GET', true);

        $buttonName = sprintf('action_%s', self::$action_button_name);
        $this->Actions()->fieldByName($buttonName)->addExtraClass('mini');
    }

    public function getRealMeMiniLoginLink()
    {
        $fields = $this->Fields();
        $buttonName = sprintf('action_%s', self::$action_button_name);
        $action = $this->Actions()->fieldByName($buttonName);

        $authMethod = $fields->dataFieldByName('AuthenticationMethod')->Value();
        $token = $fields->dataFieldByName('SecurityID')->Value();
        $actionName = $action->getName();
        $actionValue = _t('RealMeLoginForm.LOGINBUTTON', 'LoginAction');

        $queryString = sprintf(
            '?AuthenticationMethod=%s&SecurityID=%s&%s=%s',
            $authMethod,
            $token,
            $actionName,
            $actionValue
        );
        return Controller::join_links($this->FormAction(), $queryString);
    }

    public function getMiniLoginFormPopupPosition()
    {
        return sprintf('realme_arrow_top_%s', $this->popupPosition);
    }

    /**
     * The mini login form can either have the popup appear below and to the left or right. When creating the form, call
     * $form->setMiniLoginFormPopupPosition(), with the first arg being either 'left' or 'right'. This is actually the
     * 'arrow' position, so it's the opposite of what you expect (in other words, if you set it to 'left', the box will
     * extend out to the right under the mini login form.
     */
    public function setMiniLoginFormPopupPosition($dir)
    {
        if (!in_array($dir, [ 'left', 'right' ])) {
            $dir = 'left';
        }

        $this->popupPosition = $dir;
    }
}
