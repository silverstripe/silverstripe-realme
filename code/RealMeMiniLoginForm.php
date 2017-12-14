<?php
class RealMeMiniLoginForm extends RealMeLoginForm
{
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

        $queryString = sprintf('?AuthenticationMethod=%s&SecurityID=%s&%s=%s', $authMethod, $token, $actionName, $actionValue);
        return Controller::join_links($this->FormAction(), $queryString);
    }
}