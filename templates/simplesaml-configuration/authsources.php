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
 * This currently uses the same signing and mutual certificate paths and password for all 3 environments. This
 * means that you can't test e.g. connectivity with ITE on the production server environment. However, the
 * alternative is that all certificates and passwords must be present on all servers, which is sub-optimal.
 *
 * See RealMeSetupTask::createAuthSourcesFromTemplate()
 */

$config = array();

// Authentication source which handles admin authentication for accessing /simplesaml/index.php. See config.php
$config['admin'] = array(
    'core:AdminPassword',
);

// MTS - RealMe Messaging Test Site Environment
$config['realme-mts'] = array(
    'saml:SP',
    'entityID' => '{{mts-entityID}}',
    'idp' => 'https://mts.realme.govt.nz/saml2',
    'discoURL' => null,

    'NameIDPolicy' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',
    'AssertionConsumerServiceURL' => null,
    'AuthnContextClassRef' => '{{mts-authncontext}}',
    'ProtocolBinding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact',
    'redirect.sign' => true,
    'ForceAuthn' => false,
    // @todo SHA1 (the default) is deprecated and old, does RealMe support anything else?
    // 'signature.algorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',

    'privatekey' => '{{mts-privatepemfile-signing}}',
    'saml.SOAPClient.certificate' => '{{mts-privatepemfile-mutual}}',
    'saml.SOAPClient.ssl' => array(
        'verify_peer' => true,
        'verify_peer_name' => true,
        'capture_peer_cert' => true,
        'allow_self_signed' => false,
        'verify_depth' => 5,
        'peer_name' => 'as.mts.realme.govt.nz',
        'cafile' => $_SERVER['DOCUMENT_ROOT']. "/mysite/certificate-bundle.pem"
    )
);

// The password used to decrypt the signing key for MTS is only added if necessary
$signingKeyPass = '{{mts-privatepemfile-signing-password}}';
if (strlen($signingKeyPass) > 0) {
    $config['realme-mts']['privatekey_pass'] = $signingKeyPass;
}

// The password used to decrypt the mutual key for MTS is only added if necessary
$mutualKeyPass = '{{mts-privatepemfile-mutual-password}}';
if (strlen($mutualKeyPass) > 0) {
    $config['realme-mts']['saml.SOAPClient.privatekey_pass'] = $mutualKeyPass;
}

// The proxyhost and proxyport values for the back-channel SOAPClient connection are only added if necessary
$proxyHost = '{{mts-backchannel-proxyhost}}';
$proxyPort = '{{mts-backchannel-proxyport}}';
if (strlen($proxyHost) > 0 && strlen($proxyPort) > 0) {
    $config['realme-mts']['saml.SOAPClient.proxyhost'] = $proxyHost;
    $config['realme-mts']['saml.SOAPClient.proxyport'] = $proxyPort;
}

// ITE - RealMe Integrated Test Environment
$config['realme-ite'] = array(
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
    'saml.SOAPClient.ssl' => array(
        'verify_peer' => true,
        'verify_peer_name' => true,
        'capture_peer_cert' => true,
        'allow_self_signed' => false,
        'verify_depth' => 5,
        'peer_name' => 'as.ite.logon.realme.govt.nz',
        'cafile' => $_SERVER['DOCUMENT_ROOT']. "/mysite/certificate-bundle.pem"
    )
);

// The password used to decrypt the signing key for ITE is only added if necessary
$signingKeyPass = '{{ite-privatepemfile-signing-password}}';
if (strlen($signingKeyPass) > 0) {
    $config['realme-ite']['privatekey_pass'] = $signingKeyPass;
}

// The password used to decrypt the mutual key for ITE is only added if necessary
$mutualKeyPass = '{{ite-privatepemfile-mutual-password}}';
if (strlen($mutualKeyPass) > 0) {
    $config['realme-ite']['saml.SOAPClient.privatekey_pass'] = $mutualKeyPass;
}

// The proxyhost and proxyport values for the back-channel SOAPClient connection are only added if necessary
$proxyHost = '{{ite-backchannel-proxyhost}}';
$proxyPort = '{{ite-backchannel-proxyport}}';
if (strlen($proxyHost) > 0 && strlen($proxyPort) > 0) {
    $config['realme-ite']['saml.SOAPClient.proxyhost'] = $proxyHost;
    $config['realme-ite']['saml.SOAPClient.proxyport'] = $proxyPort;
}

// Production - RealMe Production Environment
$config['realme-prod'] = array(
    'saml:SP',
    'entityID' => '{{prod-entityID}}',
    'idp' => 'https://www.logon.realme.govt.nz/saml2',
    'discoURL' => null,

    'NameIDPolicy' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',
    'AssertionConsumerServiceURL' => null,
    'AuthnContextClassRef' => '{{prod-authncontext}}',
    'ProtocolBinding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact',
    'redirect.sign' => true,
    'ForceAuthn' => false,
    // @todo SHA1 (the default) is deprecated and old, does RealMe support anything else?
    // 'signature.algorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',

    'privatekey' => '{{prod-privatepemfile-signing}}',
    'saml.SOAPClient.certificate' => '{{prod-privatepemfile-mutual}}',
    'saml.SOAPClient.ssl' => array(
        'verify_peer' => true,
        'verify_peer_name' => true,
        'capture_peer_cert' => true,
        'allow_self_signed' => false,
        'verify_depth' => 5,
        'peer_name' => 'as.logon.realme.govt.nz',
        'cafile' => $_SERVER['DOCUMENT_ROOT']. "/mysite/certificate-bundle.pem"
    )
);

// The password used to decrypt the signing key for prod is only added if necessary
$signingKeyPass = '{{prod-privatepemfile-signing-password}}';
if (strlen($signingKeyPass) > 0) {
    $config['realme-prod']['privatekey_pass'] = $signingKeyPass;
}

// The password used to decrypt the mutual key for prod is only added if necessary
$mutualKeyPass = '{{prod-privatepemfile-mutual-password}}';
if (strlen($mutualKeyPass) > 0) {
    $config['realme-prod']['saml.SOAPClient.privatekey_pass'] = $mutualKeyPass;
}

// The proxyhost and proxyport values for the back-channel SOAPClient connection are only added if necessary
$proxyHost = '{{prod-backchannel-proxyhost}}';
$proxyPort = '{{prod-backchannel-proxyport}}';
if (strlen($proxyHost) > 0 && strlen($proxyPort) > 0) {
    $config['realme-prod']['saml.SOAPClient.proxyhost'] = $proxyHost;
    $config['realme-prod']['saml.SOAPClient.proxyport'] = $proxyPort;
}
