silverstripe-realme
============================

[![Build Status](http://img.shields.io/travis/silverstripe/silverstripe-realme.svg?style=flat-square)](https://travis-ci.org/silverstripe/silverstripe-realme)
[![SilverStripe supported module](https://img.shields.io/badge/silverstripe-supported-0071C4.svg)](https://www.silverstripe.org/software/addons/silverstripe-commercially-supported-module-list/)
[![Code Quality](http://img.shields.io/scrutinizer/g/silverstripe/silverstripe-realme.svg?style=flat-square)](https://scrutinizer-ci.com/g/silverstripe/silverstripe-realme)
[![License](http://img.shields.io/packagist/l/silverstripe/realme.svg?style=flat-square)](LICENSE.md)

<!-- [![Version](http://img.shields.io/packagist/v/silverstripe/realme.svg?style=flat-square)](https://packagist.org/packages/silverstripe/realme) -->

Adds support to SilverStripe for authentication and identity assertion via [RealMe](https://www.realme.govt.nz/).

This module provides the foundation to support a quick integration for a SilverStripe application with RealMe as an
identity provider. This module requires extensive setup prior to being utilised effectively.

If integration with RealMe is wanted, it is best to get in touch with the RealMe team as early as possible. This can be
accomplished by [getting in touch with the RealMe team](https://www.realme.govt.nz/realme-business/).

## Releases
There are multiple releases of this module. The current stable version is the 2.x line. This is a stable module that
provides `logon` (authentication) and `assert` (identity assertion) capability. The older 0.9.x line is considered end
of life and should not be used for new integrations.

Support is provided via the GitHub Issues for this module. If you encounter any issues, please
[open a new issue here](https://github.com/silverstripe/silverstripe-realme/issues).

## Requirements
This module doesn't have any specific requirements beyond those required by
[onelogin/php-saml](https://github.com/onelogin/php-saml/blob/master/composer.json), the tool used to control
authentication with the RealMe systems.

These requirements are PHP 5.6, with the following required PHP extensions enabled: date, dom, hash, libxml, openssl,
pcre, SPL, zlib, and mcrypt with the PHP bindings.

This module is designed to be run on a [CWP](https://www.cwp.govt.nz/) instance, and there are two sets of installation
instructions - one for use on CWP, and one for generic use.

## Installation

The module is best installed via Composer, by adding the below to your composer.json. For now, we need to specify a
custom version of the excellent onelogin/php-saml module to fix some XMLDSig validation errors with the RealMe XML
responses, hence the custom `repositories` section.

```
{
    "require": {
        "silverstripe/realme": "^2.0",
        "onelogin/php-saml": "dev-fixes/realme-dsig-validation as 2.11.0"
    },

    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/madmatt/php-saml.git"
        }
    ]
}
```

Once installation is completed, configuration is required before this module will work - see below.

## Configuration of RealMe for your application

RealMe provide two testing environments and a production environment for you to integrate with. Access to these
environments is strictly controlled, and more information on these can be found on the [RealMe Developers site](https://developers.realme.govt.nz/how-to-integrate/).

See [configuration.md](docs/en/configuration.md) for environment and YML configuration required before the module can be
used.

## Providing RealMe login buttons

By default, the module integrates with the `Authenticator` class in SilverStripe, extending the standard SilverStripe
login form. If you want to provide your own separate login form just for RealMe, then the built-in templates can help
with this. They have been designed to integrate as cleanly as possible with SilverStripe templates, but it is up to you
whether you use them, or roll your own.

See the [templates documentation](docs/en/templates.md) for more information on using or modifying these.

## Testing for authentication

The `RealMeService` service object allows you to inject authentication where-ever it is required. For example, let's
take a simple Controller that ensures that all users have a valid RealMe 'FLT' (a unique string that identifies a RealMe
user, but is not their username):

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
		// authenticated with RealMe. It should only ever return 'false' if there was an error initialising config
		if($this->service->enforceLogin()) {
			$userData = $this->service->getUserData();

			printf("Congratulations, you're authenticated with a unique ID of '%s'!", $userData->SPNameID);
		} else {
			echo "There was an error while attempting to authenticate you.";
		}
	}
}
```

## Appreciation

* Sincere thanks to Jackson (@jakxnz) for his work reviewing and updating pull requests.
