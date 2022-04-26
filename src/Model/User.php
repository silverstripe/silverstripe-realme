<?php

namespace SilverStripe\RealMe\Model;

use SilverStripe\Core\Config\Config;
use SilverStripe\RealMe\Extension\MemberExtension;
use SilverStripe\RealMe\RealMeService;
use SilverStripe\Security\Member;
use SilverStripe\View\ArrayData;

/**
 * Class RealMeUser
 *
 * Holds information about a RealMe user, as stored and retrieved from session.
 *
 * @property string SPNameID
 * @property string SessionIndex
 * @property ArrayData Attributes
 * @property FederatedIdentity FederatedIdentity
 */
class User extends ArrayData
{
    /**
     * @return bool true if the data given to this object is sufficient to ensure the user is valid for the given
     * authentication type
     */
    public function isValid()
    {
        // Login Assertion requires only the SPNameID and Session ID.
        $validLogin = is_string($this->SPNameID) && is_string($this->SessionIndex);
        if (Config::inst()->get(RealMeService::class, "integration_type") === RealMeService::TYPE_LOGIN) {
            return $validLogin;
        }

        // Federated login requires the FIT.
        $hasFederatedLogin =
            $validLogin && is_string($this->UserFederatedTag) && $this->Attributes instanceof ArrayData;

        if ($hasFederatedLogin && $this->getFederatedIdentity()) {
            return $this->getFederatedIdentity()->isValid();
        }

        return false;
    }

    /**
     * Alias of isValid(), but called this way so it's clear that a valid RealMeUser object is semantically the same as
     * an authenticated user
     * @return bool
     */
    public function isAuthenticated()
    {
        return $this->isValid();
    }

    /**
     * @return Member
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function getMember()
    {
        $memberExtended = Member::has_extension(MemberExtension::class);
        $member = null;

        if ($memberExtended) {
            $member = Member::get()->filter('RealmeSPNameID', $this->SPNameID)->first();
        }

        if (!$member) {
            $memberAttributes = [];

            if (RealMeService::config()->get('integration_type') === RealMeService::TYPE_ASSERT) {
                $memberAttributes = [
                    'FirstName' => $this->getFederatedIdentity()->getField('FirstName'),
                    'Surname' => $this->getFederatedIdentity()->getField('LastName'),
                ];
            }

            if ($memberExtended) {
                $memberAttributes += ["RealmeSPNameID" => $this->SPNameID];
            }

            $member = Member::create($memberAttributes);
        }

        return $member;
    }

    /**
     * @return FederatedIdentity|null
     */
    public function getFederatedIdentity()
    {
        // Check if identity is present
        if (!array_key_exists('FederatedIdentity', $this->array ?? [])) {
            return null;
        }

        // Get federated identity from array
        $id = $this->array['FederatedIdentity'];

        // Sanity check class
        if (!$id instanceof FederatedIdentity) {
            return null;
        }

        return $id;
    }
}
