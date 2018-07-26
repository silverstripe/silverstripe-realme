<?php

namespace SilverStripe\RealMe\Extension;

use SilverStripe\RealMe\RealMeService;
use SilverStripe\Security\InheritedPermissions;
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
     * @return bool|null True if the current user can view this page (or null to defer)
     */
    public function canView($member)
    {
        // Defer if there's a member - this only catches allowing those who aren't members but might be authenticated
        // with RealMe
        if ($member && $member->ID) {
            return null;
        }
        
        $data = $this->service->getUserData();
        if (empty($data)) {
            // Defer if there's no logged in RealMe user
            return null;
        }

        // Follow existing SiteTree logic where orphaned pages aren't viewable
        if ($this->owner->isOrphaned()) {
            return false;
        }

        if ($this->owner->CanViewType === InheritedPermissions::LOGGED_IN_USERS) {
            // We have a logged in RealMe user (which may not be a member)
            return true;
        }

        // Defer in all other cases
        return null;
    }
}
