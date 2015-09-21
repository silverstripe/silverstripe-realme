<?php
/**
 * SAML 2.0 Authentication Source list for SimpleSAMLphp.
 *
 * This is a template file, which is processed by `RealMeSetupTask` and dropped into the SimpleSAMLphp configuration
 * directory. This file should not be directed edited once it is in place within the SimpleSAMLphp codebase, instead the
 * template file should be edited, and then the RealMeSetupTask re-run with force=1 set to override existing files.
 *
 * This template is designed to be used within the `RealMeSetupTask` code, and has {{variables}} scattered throughout to
 * add custom configuration where required.
 *
 * To see all variables that can be included here, and documentation around them, check out SimpleSAMLphp's
 * authsources.php config template here:
 * https://github.com/simplesamlphp/simplesamlphp/blob/master/config-templates/authsources.php
 *
 * @see RealMeSetupTask::createAuthSourcesFromTemplate()
 * @see https://simplesamlphp.org/docs/stable/simplesamlphp-reference-idp-remote
 */

/**
 * @todo Determine what to do with multiple certificates.
 *
 * This currently uses the same signing and mutual certificates paths for all 3 environments. This means that
 * you can't test e.g. connectivity with ITE on the production server environment. However, the alternative is
 * that all certificates must be present on all servers, which is sub-optimal.
 *
 * See RealMeSetupTask::createAuthSourcesFromTemplate()
 */

$config = array(
	// Authentication source which handles admin authentication for accessing /simplesaml/index.php. See config.php
	'admin' => array(
		'core:AdminPassword',
	),

	// MTS - RealMe Messaging Test Site Environment
	'realme-mts' => array(
		'saml:SP',
		'entityID' => '{{mts-entityID}}', // http://dev.realme-integration.govt.nz/onlineservices/service1
		'idp' => 'https://mts.realme.govt.nz/saml2',
		'discoURL' => null,

		'NameIDPolicy' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',
		'AssertionConsumerServiceURL' => null,
		'AuthnContextClassRef' => '{{mts-authncontext}}', // e.g. urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:LowStrength and ModStrength
		'ProtocolBinding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact',
		'redirect.sign' => true,
		'ForceAuthn' => false,
		// @todo SHA1 (the default) is deprecated and old, does RealMe support anything else?
		// 'signature.algorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',

		'privatekey' => '{{mts-privatepemfile-signing}}',
		'privatekey_pass' => 'password',
		'saml.SOAPClient.certificate' => '{{mts-privatepemfile-mutual}}',
		'saml.SOAPClient.privatekey_pass' => 'password',
	),

	// ITE - RealMe Integrated Test Environment
	'realme-ite' => array(
		'saml:SP',
		'entityID' => '{{ite-entityID}}', // https://realme-demo.cwp.govt.nz/realme-demo/service1
		'idp' => 'https://www.ite.logon.realme.govt.nz/saml2',
		'discoURL' => null,

		'NameIDPolicy' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',
		'AssertionConsumerServiceURL' => null,
		'AuthnContextClassRef' => '{{ite-authncontext}}', // As above
		'ProtocolBinding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact',
		'redirect.sign' => true,
		'ForceAuthn' => false,
		// @todo SHA1 (the default) is deprecated and old, does RealMe support anything else?
		// 'signature.algorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',

		'privatekey' => '{{ite-privatepemfile-signing}}',
		'saml.SOAPClient.certificate' => '{{ite-privatepemfile-mutual}}',
	),

	// Production - RealMe Production Environment
	'realme-prod' => array(
		'saml:SP',
		'entityID' => '{{prod-entityID}}',
		'idp' => 'https://www.logon.realme.govt.nz/saml2',
		'discoURL' => NULL,

		'NameIDPolicy' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',
		'AssertionConsumerServiceURL' => null,
		'AuthnContextClassRef' => '{{prod-authncontext}}',
		'ProtocolBinding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact',
		'redirect.sign' => TRUE,
		'ForceAuthn' => FALSE,
		// @todo SHA1 (the default) is deprecated and old, does RealMe support anything else?
		// 'signature.algorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',

		'privatekey' => '{{prod-privatepemfile-signing}}',
		'saml.SOAPClient.certificate' => '{{prod-privatepemfile-mutual}}',
	)
);
