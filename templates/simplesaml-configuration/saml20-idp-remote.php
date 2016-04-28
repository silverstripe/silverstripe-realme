<?php
/**
 * SAML 2.0 Remote Identity Provider (IdP) metadata for SimpleSAMLphp.
 *
 * This is a template file, which is processed by `RealMeSetupTask` and dropped into the SimpleSAMLphp metadata
 * configuration directory. This file should not be directed edited once it is in place within the SimpleSAMLphp
 * codebase, instead the template file should be edited, and then the RealMeSetupTask re-run with force=1 set to
 * override existing files.
 *
 * This template is designed to be used within the `RealMeSetupTask` code, and may have {{variables}} scattered
 * throughout to add custom configuration where required.
 *
 * To see all variables that can be included here, and documentation around them, check out SimpleSAMLphp's reference
 * information here: https://simplesamlphp.org/docs/stable/simplesamlphp-reference-idp-remote
 *
 * @see RealMeSetupTask::run()
 * @see https://simplesamlphp.org/docs/stable/simplesamlphp-reference-idp-remote
 */

// MTS - RealMe Messaging Test Site Environment
$metadata['https://mts.realme.govt.nz/saml2'] = array(
    'name' => 'MTS',
    'description' => 'RealMe MTS authentication system',

    'SingleSignOnService' => 'https://mts.realme.govt.nz/logon-mts/mtsEntryPoint',
    'SingleSignOnService.artifact' => 'https://mts.realme.govt.nz/logon-mts/mtsEntryPoint',

    'certificate' => 'mts_saml_idp.cer',
    'saml.SOAPClient.certificate' => 'mts_mutual_ssl_sp.cer',
    'saml.SOAPClient.privatekey_pass' => 'password',

    'ArtifactResolutionService' => array(
        array(
            'index' => 0,
            'Location' => 'https://as.mts.realme.govt.nz/sso/ArtifactResolver/metaAlias/logon/logonidp',
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:SOAP'
        )
    )
);

// ITE - RealMe Integrated Test Environment
$metadata['https://www.ite.logon.realme.govt.nz/saml2'] = array(
    'name' => 'ITE',
    'description' => 'RealMe ITE authentication system',

    'SingleSignOnService' => 'https://www.ite.logon.realme.govt.nz/sso/logon/metaAlias/logon/logonidp',
    'SingleSignOnService.artifact' => 'https://www.ite.logon.realme.govt.nz/sso/logon/metaAlias/logon/logonidp',

    'certificate' => 'ite.signing.logon.realme.govt.nz.cer',
    'saml.SOAPClient.certificate' => 'ws.ite.realme.govt.nz.cer',

    'ArtifactResolutionService' => array(
        array(
            'index' => 0,
            'Location' => 'https://ws.ite.realme.govt.nz/login/sso/ArtifactResolver/metaAlias/logon/logonidp',
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:SOAP'
        )
    )
);

// Production - RealMe Production Environment
$metadata['https://www.logon.realme.govt.nz/saml2'] = array(
    'name' => 'Production',
    'description' => 'RealMe Production authentication system',

    'SingleSignOnService' => 'https://www.logon.realme.govt.nz/sso/logon/metaAlias/logon/logonidp',
    'SingleSignOnService.artifact' => 'https://www.logon.realme.govt.nz/sso/logon/metaAlias/logon/logonidp',

    'certificate' => 'signing.logon.realme.govt.nz.cer',
    'saml.SOAPClient.certificate' => 'ws.realme.govt.nz.cer',

    'ArtifactResolutionService' => array(
        array(
            'index' => 0,
            'Location' => 'https://ws.realme.govt.nz/login/sso/ArtifactResolver/metaAlias/logon/logonidp',
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:SOAP'
        )
    )
);
