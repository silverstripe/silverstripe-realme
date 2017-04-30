silverstripe-realme
============================

[![Build Status](http://img.shields.io/travis/silverstripe/silverstripe-realme.svg?style=flat-square)](https://travis-ci.org/silverstripe/silverstripe-realme)
[![Code Quality](http://img.shields.io/scrutinizer/g/silverstripe/silverstripe-realme.svg?style=flat-square)](https://scrutinizer-ci.com/g/silverstripe/silverstripe-realme)
[![License](http://img.shields.io/packagist/l/silverstripe/realme.svg?style=flat-square)](LICENSE.md)

<!-- [![Version](http://img.shields.io/packagist/v/silverstripe/realme.svg?style=flat-square)](https://packagist.org/packages/silverstripe/realme) -->

Adds support to SilverStripe for authentication via [RealMe](https://www.realme.govt.nz/).

This module provides the foundation to support a quick integration for a SilverStripe application with RealMe as an
identity provider. This module requires extensive setup prior to being utilised effectively.

If integration with RealMe is wanted, it is best to get in touch with the RealMe team as early as possible. There are a
number of documents mentioned in this documentation that can only be found by accessing the RealMe Shared Workspace.
This can be accomplished by [getting in touch with the RealMe team](https://www.realme.govt.nz/realme-business/).

**Note:** Currently this module does not integrate with the `Member` functionality of SilverStripe. It is initially
intended to purely provide an authentication mechanism that can be extended by customers that require it. One such
extension would be to create standard SilverStripe `Member` records linked to a unique RealMe identifier, but that's
not currently built in.

## Work in progress
This module is a work in progress. It is generally considered stable, but should have a decent knowledge of RealMe or at
least standard SAML conventions in order to debug issues. Support is provided via the GitHub Issues for this module. If
you encounter any issues, please [open a new issue here](https://github.com/silverstripe/silverstripe-realme/issues).

## Requirements
This module doesn't have any specific requirements beyond those required by
[onelogin/php-saml](https://github.com/onelogin/php-saml/blob/master/composer.json), the tool used to control
authentication with the RealMe systems.

These requirements are PHP 5.6, with the following required PHP extensions enabled: date, dom, hash, libxml, openssl,
pcre, SPL, zlib, and mcrypt with the PHP bindings.

This module is designed to be run on a [CWP](https://www.cwp.govt.nz/) instance, and there are two sets of installation
instructions - one for use on CWP, and one for generic use.

## Installation

See the [Installation section](docs/en/installation.md) for full details.

## Configuration of RealMe for your application

RealMe provide two testing environments and a production environment for you to integrate with. Access to these
environments is strictly controlled, and you must [contact the RealMe team](https://www.realme.govt.nz/realme-business/)
to gain access to the documentation required for these environments.

See [configuration.md](docs/en/configuration.md) for environment and YML configuration required before the module can be
setup.

The configuration instructions above also steps you through setting up all three environments.

## Providing RealMe login buttons

By default, the module integrates with the `Authenticator` class in SilverStripe, extending the standard SilverStripe
login form. If you want to provide your own separate login form just for RealMe, then the built-in templates can help
with this. They have been designed to integrate as cleanly as possible with SilverStripe templates, but it is up to you
whether you use them, or roll your own.

See the [templates documentation](docs/en/templates.md) for more information on using or modifying these.

## Testing for authentication

The `RealMeService` service object allows you to inject authentication where-ever it is required. For example, let's
take a simple Controller that ensures that all users have a valid RealMe 'FLT' (a unique string that identifies a RealMe
account, but is not their username.

```php
class RealMeTestController extends Controller {
	/**
	 * @var RealMeService
	 */
	public $realMeService;

	private static $dependencies = array(
		'realMeService' => '%$RealMeService'
	);

	public function index() {
		// enforceLogin will redirect the user to RealMe if they're not authenticated, or return true if they are
		// authenticated with RealMe. It should only ever return 'false' if there was an initial error dealing with
		// SimpleSAMLphp
		if($this->service->enforceLogin()) {
			$userData = $this->service->getUserData();

			printf("Congratulations, you're authenticated with a FLT of '%s'!", $userData->UserFlt);
		} else {
			echo "There was an error while attempting to authenticate you.";
		}
	}
}
```

### MTS: [Messaging Test Environment](https://mts.realme.govt.nz/logon-mts/home)

The first environment is MTS. This environment is setup to allow testing of your code on your development environment.
In this environment, RealMe provide all SSL certificates required to communicate.

- Obtain access to RealMe and the Shared Workspace for MTS public/private development keys
- Fill out the "MTS checklist" available from the shared workspace and provide to the DIA RealMe team.
- Download 'Integration Bundle Login MTS' from the [RealMe Shared Workspace](https://see.govt.nz/realme/realme/Library/Forms/Library.aspx)
- Unpack the four certificates into the directory you've specified in `REALME_CERT_DIR` (ideally outside of your webroot)
    - mts_mutual_ssl_idp.cer
    - mts_mutual_ssl_sp.cer
    - mts_mutual_ssl_sp.pem
    - mts_saml_idp.cer
    - mts_saml_sp.pem

- Run the RealMe build task to populate the configuration directories, metadata files, and authsources for MTS
```sake dev/tasks/RealMeSetupTask forEnv=mts```

#### MTS metadata example ####

```xml
<?xml version="1.0" encoding="UTF-8"?>
<EntityDescriptor xmlns="urn:oasis:names:tc:SAML:2.0:metadata" entityID="http://yourdomain.govt.nz/p-realm/s-name">
	<SPSSODescriptor AuthnRequestsSigned="true"
                     WantAssertionsSigned="true"
                     protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
        <KeyDescriptor use="signing">
            <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
                <ds:X509Data>
                    <ds:X509Certificate>
                        SSL certificate info
                    </ds:X509Certificate>
                </ds:X509Data>
            </ds:KeyInfo>
        </KeyDescriptor>
        <NameIDFormat>urn:oasis:names:tc:SAML:2.0:nameid-format:persistent</NameIDFormat>
        <NameIDFormat>urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified</NameIDFormat>
        <AssertionConsumerService
                Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact"
                Location="http://yourdomain.govt.nz/vendor/madmatt/simplesamlphp/www/module.php/saml/sp/saml2-acs.php/realme-mts" index="0"
                isDefault="true">
        </AssertionConsumerService>
    </SPSSODescriptor>
    <Organization>
        <OrganizationName xml:lang="en-us">CWP Demo Organisation</OrganizationName>
        <OrganizationDisplayName xml:lang="en-us">CWP Demo Organisation</OrganizationDisplayName>
        <OrganizationURL xml:lang="en-us">http://yourdomain.govt.nz/</OrganizationURL>
    </Organization>
    <ContactPerson contactType="support">
        <Company>SilverStripe</Company>
        <GivenName>Jane</GivenName>
        <SurName>Smith</SurName>
    </ContactPerson>
</EntityDescriptor>
```

- Save the XML output from your task to an XML file, and upload this to the [MTS metadata upload](https://mts.realme.govt.nz/logon-mts/metadataupdate). Be sure to click continue and ok after uploading.

- Include code to ensure that pages are protected, for example in your controller:

```php
class Page_Controller extends ContentController {
    public function index() {
        $service = Injector::inst()->get('RealMeService');

        if($service->enforceLogin()) {
            var_dump($service->getUserData());
        } else {
            echo "Failure during RealMe authentication.";
        }
    }
}
```

- You can also access user data from templates using `$RealMeUser`
- Or in a controller by using `RealMeService::currentRealMeUser()`

### ITE: Integration Test Environment

- Complete an integration to MTS.
- Obtain the ITE checklist from the RealMe shared document library and complete it.
- Publish your site to your CWP UAT environment with a working configuration for MTS and ITE
- Create a support ticket with [CWP Service desk](https://www.cwp.govt.nz/service-desk/new-request/) requesting access to ITE, and referencing information about your project, domain, and the ITE checklist

**Note** There will be charges associated with this, as operations will need generate and purchase the SSL certificates required for your domain, and provide them to DIA
To save time, both ITE and production certificates will be purchased at the same time.

If you wish do do this process yourself please see the [ssl-certs documentation](docs/en/ssl-certs.md)

### PROD: Production Environment

- Complete an integration to MTS and ITE.
- Obtain the Production checklist from the RealMe shared document library and complete it.
- Publish your site to your CWP UAT environment with a working configuration for MTS and ITE and a configuration for production
- Create a support ticket with [CWP Service desk](https://www.cwp.govt.nz/service-desk/new-request/) requesting access to production, and referencing information about your project, domain, and the production checklist

**Note** There will be charges associated with this, as operations will need generate and purchase the SSL certificates required for your domain, and provide them to DIA

If you wish do do this process yourself please see the [ssl-certs documentation](docs/en/ssl-certs.md)

## Known issues
The RelayState must be less than 80 bytes

