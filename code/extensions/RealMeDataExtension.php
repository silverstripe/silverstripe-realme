<?php
class RealMeDataExtension extends DataExtension
{
    private static $dependencies = array(
        'service' => '%$RealMeService'
    );

    /**
     * @var RealMeService
     */
    public $service;

    /**
     * @return RealMeUser|false
     */
    public function RealMeUser()
    {
        $user = $this->service->getUserData();

        if($user && $user->isValid()) {
            return $user;
        } else {
            return false;
        }
    }
}
