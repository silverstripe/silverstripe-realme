<?php

namespace SilverStripe\RealMe\Extension;

use SilverStripe\RealMe\RealMeService;
use SilverStripe\Security\Member;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataExtension;

class SiteTreeExtension extends DataExtension
{
    private static $dependencies = array(
        'service' => '%$' . RealMeService::class
    );

    /**
     * @var RealMeService
     */
    public $service;

    /**
     * This function is an extension of the default SiteTree canView(), and allows viewing permissions for a SiteTree
     * object which has allowed a page to be presented to logged in users. With RealMe a logged in user is a user
     * which has authenticated with the identity provider, and we have stored a FLT in session.
     *
     * Return true, if the CanViewType is LoggedInUsers, and we have a valid RealMe Session authenticated.
     *
     * @param Member|int $member
     *
     * @return bool True if the current user can view this page
     */
    public function canView($member)
    {
        switch ($this->owner->CanViewType) {
            case 'Anyone':
                return true;

            case 'Inherit':
                if ($this->owner->ParentID) {
                    return $this->owner->Parent()->canView($member);
                }
                return $this->owner->getSiteConfig()->canViewPages($member);

            case 'LoggedInUsers':
                // check for any logged-in RealMe Sessions
                $data = $this->service->getUserData();
                if (!is_null($data)) {
                    return true;
                }

                if ($member && is_numeric($member)) {
                    return true;
                }

                return false;

            case 'OnlyTheseUsers':
                if ($member && is_numeric($member)) {
                    $member = DataObject::get_by_id(Member::class, $member);

                    /** @var Member $member */
                    if ($member && $member->inGroups($this->owner->ViewerGroups())) {
                        return true;
                    }
                }
        }
        return false;
    }
}
