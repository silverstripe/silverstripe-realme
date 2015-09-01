# RealMe module for SilverStripe

[Real Me](https://www.realme.govt.nz/)

This module provides the foundation to support a quick integration for a SilverStripe application running on the 
common web platform to RealMe as an identity provider. 

## Requirements

SilverStripe 3.1

## Installation

** Composer / Packagist ([best practice](http://doc.silverstripe.org/framework/en/trunk/installation/composer))**  
Add "silverstripe/realme" to your requirements.

** Manually **  
Download, place the folder in your project root called 'realme' and run a dev/build?flush=1.

## Implementing RealMe

@todo

```
 // code
```

## Task requirements

This lists what the dev/task should do when run (inexhaustive list, should be removed from README in favour of building the actual script

* Create config.php for SimpleSAMLphp, with the following array keys changed from default:

```php
$config = array(
	'baseurlpath' => '', // ?
	'certdir' => '', // defined in _ss_env, enforce this as being outside webroot, no default value
	'loggingdir' => '', // defined in Config, enforce this as being outside webroot, /var/log/simplesaml by default?
	'tempdir' => '', // defined in Config, enforce this as being outside webroot, /tmp/simplesaml by default?
	'debug' => false, // defined in Config
	'showerrors' => false, // same value as `debug`
	'errorreporting' => false, // same value as `showerrors`
	'auth.adminpassword' => '', //  A randomly-generated long string
	'admin.protectindexpage' => true,
	'admin.protectmetadata' => true,
	'secretsalt' => '', // A randomly generated long string
	'technicalcontact_name' => '', // Defined in Config, required (just in case)
	'technicalcontact_email' => '', // Defined in Config, required (just in case)
	'enable.authmemcookie' => false,
	'session.duration' => 8 * (60 * 60), // ?, this is 8 hrs
	'language.available' => array('en'),
	'language.default' => 'en',
	'session.phpsession.savepath' => null, // check w/ Ops if it's different for Active DR customers (and synced between servers)
);
```

* Create authsources.php for SimpleSAMLphp, with the following array:

```php
$config = array(
	'realme-mts' => array(
		'saml:SP',

		// URL defined in Config, should be in format https://[site-path.govt.nz]/[privacy realm]/[service-name]
		// NB: Must match values provided in ITE/prod checklists, so discussion required with dev team to resolve this
		// Example value below:
		'entityID' => 'http://dev.realme-integration.govt.nz/onlineservices/service1',

		'idp' => 'https://mts.realme.govt.nz/saml2',
		'discoURL' => NULL,
		'NameIDPolicy' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',
		'AssertionConsumerServiceURL' => null,
		'AuthnContextClassRef' => 'urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:ModStrength',
		'ProtocolBinding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact',
		'redirect.sign'	  => true,
		'privatekey'	  =>	'mts_saml_sp.pem',
		'privatekey_pass' =>	'password',
		'ForceAuthn' => FALSE,
		'saml.SOAPClient.certificate' => 'mts_mutual_ssl_sp.pem',
		'saml.SOAPClient.privatekey_pass' => 'password',
	),

	// Same details as above, but with ITE IdP URLs and entity IDs
	'realme-ite' => array(),

	// Same details as above, but with prod IdP URLs and entity IDs
	'realme-prod' => array()
);
```

* Create file in metadatadir for the idp values in the authsources array:

```php
$metadata['https://mts.realme.govt.nz/saml2'] = array(
	'name' => 'MTS',
	'description' => 'Here you can single sign on to an MTS IdP using your RealMe logon',
	'SingleSignOnService'  => 'https://mts.realme.govt.nz/logon-mts/mtsEntryPoint',
	'SingleSignOnService.artifact'  => 'https://mts.realme.govt.nz/logon-mts/mtsEntryPoint',
	'SingleLogoutService'  => 'https://mts.realme.govt.nz/logon-mts/mtsEntryPoint',
	'certificate' => 'mts_saml_idp.cer', // File in certdir
	'ArtifactResolutionService' => array(
		array(
			'index' => 0,
			'Location' => 'https://as.mts.realme.govt.nz/sso/ArtifactResolver/metaAlias/logon/logonidp',
			'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:SOAP'
		)
	),
	'saml.SOAPClient.certificate' => 'mts_mutual_ssl_sp.cer', // File in certdir
	'saml.SOAPClient.privatekey_pass' => 'password'
);
```

## Known issues
url < 80 bytes

certificates MUST be 3years

## Customisation


Check cert connection..
```
openssl s_client -tls1 -cert vendor/simplesamlphp/simplesamlphp/cert/mts_mutual_ssl_sp.pem -connect as.mts.realme.govt.nz:443/sso/ArtifactResolver/metaAlias/logon/logonidp

```

```bash
# Generate private keys first
openssl genrsa -out ite.sa.saml.sig.website-domain-name.key 2048
openssl genrsa -out ite.sa.mutual.ssl.website-domain-name.key 2048

# Then create certificate requests

# Use the following params when openssl asks for data
# - Country Name: NZ
# - State or Province Name: <Region of Agency, typically Wellington>
# - Locality Name: <City name, typically Wellington>
# - Organisation Name: <Legal name of Agency>
# - Organisational Unit Name: Leave blank
# - Common Name: This depends on which certificate you're generating. It's either:
#   - ite.sa.saml.sig.website-domain-name, OR
#   - ite.sa.mutual.ssl.website-domain-name
# - Email Address: Leave blank
# - A challenge password: Leave blank
# - An optional company name: Leave blank

openssl req -new -key ite.sa.saml.sig.website-domain-name.key -out ite.sa.saml.sig.website.domain.name.csr
openssl req -new -key ite.sa.mutual.ssl.website-domain-name.key -out ite.sa.mutual.ssl.website.domain.name.csr
```
