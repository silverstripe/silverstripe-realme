<?php

/**
 * Class RealMeUser
 *
 * Holds information about a RealMe user, as stored and retrieved from session.
 *
 * @property string NameID
 * @property string SessionIndex
 * @property ArrayData Attributes
 * @property RealMeFederatedIdentity FederatedIdentity
 */
class RealMeUser extends ArrayData {
    /**
     * @return bool true if the data given to this object is sufficient to ensure the user is valid
     */
    public function isValid()
    {
        $valid = is_string($this->NameID) && is_string($this->SessionIndex) && $this->Attributes instanceof ArrayData;

        // Only validate the FederatedIdentity if it exists
        if($valid && $this->array['FederatedIdentity'] && $this->array['FederatedIdentity'] instanceof RealMeFederatedIdentity) {
            $valid = $this->array['FederatedIdentity']->isValid();
        }

        return $valid;
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
     * @return RealMeFederatedIdentity|null
     */
    public function getFederatedIdentity()
    {
        return $this->array['FederatedIdentity'];
    }
}