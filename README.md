silverstripe-realme
============================

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

## Requirements
This module doesn't have any specific requirements beyond those required by [SimpleSAMLphp](https://simplesamlphp.org): 
the tool used to control authentication with the RealMe systems.

These requirements are PHP 5.3+, with the following required PHP extensions enabled: date, dom, hash, libxml, openssl, 
pcre, SPL, zlib, and mcrypt.

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










### MTS: Messaging Test Environment

The first environment is MTS. This environment is setup to allow testing of your code on your development environment. 
In this environment, RealMe provide all SSL certificates required to communicate.

- Obtain access to RealMe and the Shared Workspace for MTS public/private development keys
- Download 'Integration Bundle Login MTS' from the [RealMe Shared Workspace](https://see.govt.nz/realme/realme/Library/Forms/Library.aspx)
- Unpack the four certificates (mts_saml_idp.cer, mts_saml_sp.pem, mts_mutual_ssl_sp.cer, mts_mutual_ssl_sp.pem) into the directory you've specified in `REALME_CERT_DIR` (ideally outside of your webroot)



- Run the RealMe build task to create the directores and the metadata files for MTS (coming soon)
- unpack the certificates into vendor/simplesamlphp/simplesamlphp/cert (create if not present)
    - mts_mutual_ssl_idp.cer
    - mts_mutual_ssl_sp.cer
    - mts_mutual_ssl_sp.pem
    - mts_saml_idp.cer
    - mts_saml_sp.pem
- include the session data realme/templates/Layout/RealMeSessionData.ss in your template, or reference session data
directly from any descendant of SiteTree $RealMeSessionData, or by using SiteConfig: SiteConfig::current_site_config()->RealMeSessionData();

### ITE: Integration Test Environment

@todo
 
### PROD: Production Environment

@todo







@todo Refactor all of the below


## Known issues
RelayState < 80 bytes
