<?php
class RealMeService extends Object {
	/**
	 * @var ArrayData|null User data returned by Real Me. Provided by {@link self::ensureLogin()}.
	 *
	 * Data within this ArrayData is as follows:
	 * - NameID:       ArrayData   Includes the UserFlt and associated formatting information
	 * - UserFlt:      string      Real Me pseudonymous username / identity
	 * - Attributes:   ArrayData   User attributes returned by Real Me
	 * - Expire:       SS_Datetime The expiry date & time of this authentication session
	 * - SessionIndex: string      Unique identifier used to identify a user with both IdP and SP for given user.
	 */
	private static $user_data = null;

	/**
	 * @return bool true if the user is correctly authenticated, false if there was an error with login
	 * NB: If the user is not authenticated, they will be redirected to Real Me to login, so a boolean false return here
	 * indicates that there was a failure during the authentication process (perhaps a communication issue
	 */
	public function enforceLogin() {
		// @todo Change this to pull auth_source from Config
		$auth = new SimpleSAML_Auth_Simple('realme');

		$auth->requireAuth(array(
			'ReturnTo' => '/Security/realmevalidate',
			'ErrorURL' => '/Security/realmevalidate'
		));

		$nameId = $userFlt = null;
		$loggedIn = false;
		$authData = $this->getAuthData($auth);

		if(is_null($authData)) {
			// @todo This means error of some kind
		} else {
			$this->config()->user_data = $authData;
			Session::set('RealMeSessionDataSerialized', serialize($authData));
			$loggedIn = true;
		}
		return $loggedIn;
	}

	/**
	 * Clear the realme credentials from our session.
	 */
	public function clearLogin(){
		Session::clear('RealMeSessionDataSerialized');
		$this->config()->__set('user_data', null);
	}

	/**
	 * Return the user data which was saved to session from the first realme auth.
	 *
	 * @return array
	 */
	public function getUserData() {
		if(is_null($this->config()->user_data)) {
			$sessionData = Session::get('RealMeSessionDataSerialized');

			if(!is_null($sessionData) && unserialize($sessionData) !== false) {
				$this->config()->user_data = unserialize($sessionData);
			}
		}

		return $this->config()->user_data;
	}

	/**
	 * @param SimpleSAML_Auth_Simple $auth The authentication context as returned from Real Me
	 * @return ArrayData
	 */
	private function getAuthData(SimpleSAML_Auth_Simple $auth) {
		$data = $auth->getAuthDataArray();
		$returnedData = null;

		if(
			is_array($data) &&
			isset($data['saml:sp:IdP']) &&
			isset($data['saml:sp:NameID']) &&
			is_array($data['saml:sp:NameID']) &&
			isset($data['saml:sp:NameID']['Value']) &&
			isset($data['Expire']) &&
			isset($data['Attributes']) &&
			isset($data['saml:sp:SessionIndex'])
		) {
			$returnedData = new ArrayData(array(
				'NameID' => new ArrayData($data['saml:sp:NameID']),
				'UserFlt' => $data['saml:sp:NameID']['Value'],
				'Attributes' => new ArrayData($data['Attributes']),
				'Expire' => $data['Expire'],
				'SessionIndex' => $data['saml:sp:SessionIndex']
			));
		}

		return $returnedData;
	}
}