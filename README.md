silverstripe-realme
============================

Adds support to SilverStripe for authentication via [RealMe](https://www.realme.govt.nz/).

This module provides the foundation to support a quick integration for a SilverStripe application running on the
common web platform to RealMe as an identity provider.

## Requirements
[CWP basic recipe 1.1.1](https://www.cwp.govt.nz/guides/core-technical-documentation/common-web-platform-core/en/releases/)

This module is designed to be run on a [CWP](https://www.cwp.govt.nz/) instance.

## Installation
via Composer / Packagist ([best practice](http://doc.silverstripe.org/framework/en/trunk/installation/composer))

Ensure repository 'https://packages.cwp.govt.nz/' is added to composer (CWP provides this by default)

```json
'"repositories": [
    {
        "type": "composer",
        "url": "https://packages.cwp.govt.nz/"
    }
```

Add "silverstripe/realme" to your composer requirements.

```
composer require silverstripe/realme
composer update
```

#### Manual Installation
[Download](https://gitlab.cwp.govt.nz/silverstripe/realme), place the folder in your project root called 'realme' and
run a dev/build?flush=1.

## Configuration of RealMe in your application

See [configuration.md](docs/en/configuration.md) for environment and YML configuration required before the module can be setup.

Setup
- Symlink simplesaml in the project root to vendor/simplesamlphp/simplesamlphp/www/
```
ln -s vendor/simplesamlphp/simplesamlphp/www/ simplesaml
```

### MTS Messaging Test Environment
- Add this module to your composer requirements
- Fill out complete the MTS checklist to start development
- Obtain access to RealMe and the Shared Workspace for MTS public/private development keys.
- Download the ["Integration Bundle Assert MTS"](https://see.govt.nz/realme/realme/Library/Forms/Library.aspx) from
  https://see.govt.nz/realme/realme/Library/Forms/Library.aspx
- Run the RealMe build task to create the directores and the metadata files for MTS (coming soon)
- unpack the certificates into vendor/simplesamlphp/simplesamlphp/cert (create if not present)
    - mts_mutual_ssl_idp.cer
    - mts_mutual_ssl_sp.cer
    - mts_mutual_ssl_sp.pem
    - mts_saml_idp.cer
    - mts_saml_sp.pem
- include the session data realme/templates/Layout/RealMeSessionData.ss in your template, or reference session data
directly from any descendant of SiteTree $RealMeSessionData, or by using SiteConfig: SiteConfig::current_site_config()->RealMeSessionData();

### ITE Integration Test Environment
 @todo

## Known issues
url < 80 bytes

Certificates
- Procured from
  - Verisign: http://www.verisign.com/
  - RapidSSL: http://www.rapidssl.com/
- Must have an expiry of three years from creation.
- Bit length is 2048.
- serial number must be non-negative.
- Follow naming convention (concatenation)
 - unique, lowercase a-z 0-9 .
 - The RealMe assertion service environment
 - the keyword 'sa'
 - purpose (saml.sig or mutual.ssl).
 - An identifier that is unique across all certificates

 e.g. {environment}.sa.{purpose}.{client domain} | ite.sa.mutual-ssl.mydomain.cwp.govt.nz


## Manual Certificate Creation.

Check cert connection..

```openssl s_client -tls1 -cert vendor/simplesamlphp/simplesamlphp/cert/mts_mutual_ssl_sp.pem -connect as.mts.realme.govt.nz:443/sso/ArtifactResolver/metaAlias/logon/logonidp

# Generate private keys

openssl genrsa -out ite.sa.saml.sig.website-domain-name.key 2048
openssl genrsa -out ite.sa.mutual.ssl.website-domain-name.key 2048
```

Then create certificate requests
 Use the following params when openssl asks for data
 - Country Name: NZ
 - State or Province Name: <Region of Agency, typically Wellington>
 - Locality Name: <City name, typically Wellington>
 - Organisation Name: <Legal name of Agency>
 - Organisational Unit Name: Leave blank
 - Common Name: This depends on which certificate you're generating. It's either:
   - ite.sa.saml.sig.website-domain-name, OR
   - ite.sa.mutual.ssl.website-domain-name
 - Email Address: Leave blank
 - A challenge password: Leave blank
 - An optional company name: Leave blank

```
openssl req -new -key ite.sa.saml.sig.website-domain-name.key -out ite.sa.saml.sig.website.domain.name.csr
openssl req -new -key ite.sa.mutual.ssl.website-domain-name.key -out ite.sa.mutual.ssl.website.domain.name.csr
```

## Task requirements

This lists what the dev/task should do when run (inexhaustive list, should be removed from README in favour of building the actual script

* Create config.php for SimpleSAMLphp, with the following array keys changed from default:

- REALME_CERT_DIR -  Updated on CWP to be constant defined in SS_ENV.
/sites/{instancename}/certs/realme
via puppet.

- REALME_LOG_DIR - /var/log/realme

/tmp/realme

logging

SSL

```php
$config = array(
	'baseurlpath' => '', // ?
	'certdir' => REALME_CERT_DIR, // defined in _ss_env, enforce this as being outside webroot, no default value
	'loggingdir' => '', // defined in Config, enforce this as being outside webroot, /var/log/realme by default?
	'tempdir' => '', // defined in Config, enforce this as being outside webroot, /tmp/simplesaml by default?
	'debug' => false, // forced to false
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

* Create saml20-idp-remote.php file in metadatadir for the idp values in the authsources array:

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

// Also need values for ITE and prod environments
```

* Create metadata XML file for upload to RealMe Shared Workspace. The below sample is for ITE, with the following
substitutions required:
    * **{{entityID}}**: The URL for your entity, as specified in authsources.php
(e.g. https://realme-demo.cwp.govt.nz/realme-demo/service1)
    * **{{saml.sig}}**: The certificate file (*.cer) as provided by the certificate provider (e.g. RapidSSL). Note that
    this should **not** contain the ---- BEGIN CERTIFICATE ---- or ---- END CERTIFICATE ---- lines.
    * **{{assertion.service.url}}**: The URL to SimpleSAMLphp, and your assertion consumer service. In this example, the
    URL would be `https://realme-demo.cwp.govt.nz/simplesaml/module.php/saml/sp/saml2-acs.php/realme-ite` if we were
    integrating into the ITE environment.
    * **{{organisation.name}}**: The Organisation name that this authentication is for (e.g. Department of Internal
    Affairs)
    * **{{organisation.display.name}}**: The display name for the organisation that this authentication is for (often
    the same as above)
    * **{{organisation.url}}**: The URL to the organisation's homepage
    * **{{contact.1.type}}**: Contact type (e.g. 'support') for contact #1
    * **{{contact.1.company}}**: The company name for contact #1
    * **{{contact.1.givenname}}**: First / Given name for contact #1
    * **{{contact.1.surname}}**: Surname for contact #1

```xml
<?xml version="1.0" encoding="UTF-8"?>
<EntityDescriptor xmlns="urn:oasis:names:tc:SAML:2.0:metadata" entityID="{{entityID}}">
  <SPSSODescriptor AuthnRequestsSigned="true"
    WantAssertionsSigned="true"
    protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
    <KeyDescriptor use="signing">
      <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
        <ds:X509Data>
          <ds:X509Certificate>
{{saml.sig}}
          </ds:X509Certificate>
        </ds:X509Data>
      </ds:KeyInfo>
    </KeyDescriptor>
    <NameIDFormat>urn:oasis:names:tc:SAML:2.0:nameid-format:persistent</NameIDFormat>
    <NameIDFormat>urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified</NameIDFormat>
    <AssertionConsumerService
      Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact"
      Location="{{assertion.service.url}}" index="0"
      isDefault="true">
    </AssertionConsumerService>
  </SPSSODescriptor>
  <Organization>
    <OrganizationName xml:lang="en-us">{{organisation.name}}</OrganizationName>
    <OrganizationDisplayName xml:lang="en-us">{{organisation.display.name}}</OrganizationDisplayName>
    <OrganizationURL xml:lang="en-us">{{organisation.url}}</OrganizationURL>
  </Organization>
  <ContactPerson contactType="{{contact.1.type}}">
    <Company>{{contact.1.company}}</Company>
    <GivenName>{{contact.1.givenname}}</GivenName>
    <SurName>{{contact.1.surname}}</SurName>
  </ContactPerson>
</EntityDescriptor>
```

## Known issues
url < 80 bytes

Certificates
- Procured from
  - Verisign: http://www.verisign.com/
  - RapidSSL: http://www.rapidssl.com/
- Must have an expiry of three years from creation.
- Bit length is 2048.
- serial number must be non-negative.
- Follow naming convention (concatenation)
 - unique, lowercase a-z 0-9 .
 - The RealMe assertion service environment
 - the keyword 'sa'
 - purpose (saml.sig or mutual.ssl).
 - An identifier that is unique across all certificates

 e.g. {environment}.sa.{purpose}.{client domain} | ite.sa.mutual-ssl.mydomain.cwp.govt.nz


## Customisation

Check cert connection..
```
openssl s_client -tls1 -cert vendor/simplesamlphp/simplesamlphp/cert/mts_mutual_ssl_sp.pem -connect as.mts.realme.govt.nz:443/sso/ArtifactResolver/metaAlias/logon/logonidp

```
# Generate private keys first
openssl genrsa -out ite.sa.saml.sig.website-domain-name.key 2048
openssl genrsa -out ite.sa.mutual.ssl.website-domain-name.key 2048

# Then create certificate requests

 Use the following params when openssl asks for data
 - Country Name: NZ
 - State or Province Name: <Region of Agency, typically Wellington>
 - Locality Name: <City name, typically Wellington>
 - Organisation Name: <Legal name of Agency>
 - Organisational Unit Name: Leave blank
 - Common Name: This depends on which certificate you're generating. It's either:
   - ite.sa.saml.sig.website-domain-name, OR
   - ite.sa.mutual.ssl.website-domain-name
 - Email Address: Leave blank
 - A challenge password: Leave blank
 - An optional company name: Leave blank

openssl req -new -key ite.sa.saml.sig.website-domain-name.key -out ite.sa.saml.sig.website.domain.name.csr
openssl req -new -key ite.sa.mutual.ssl.website-domain-name.key -out ite.sa.mutual.ssl.website.domain.name.csr
```