# Silverstripe Realme

[![CI](https://github.com/silverstripe/silverstripe-realme/actions/workflows/ci.yml/badge.svg)](https://github.com/silverstripe/silverstripe-realme/actions/workflows/ci.yml)
[![Silverstripe supported module](https://img.shields.io/badge/silverstripe-supported-0071C4.svg)](https://www.silverstripe.org/software/addons/silverstripe-commercially-supported-module-list/)

Adds support to Silverstripe for authentication and identity assertion via [RealMe](https://www.realme.govt.nz/).

This module provides the foundation to support a quick integration for a Silverstripe application with RealMe as an
identity provider. This module requires extensive setup prior to being utilised effectively.

If integration with RealMe is wanted, it is best to get in touch with the RealMe team as early as possible. This can be
accomplished by [getting in touch with the RealMe team](https://www.realme.govt.nz/realme-business/).

If you encounter any issues please [open a new issue here](https://github.com/silverstripe/silverstripe-realme/issues).

## Installation

```sh
composer require silverstripe/realme
```

## Configuration of RealMe for your application

RealMe provide two testing environments and a production environment for you to integrate with. Access to these
environments is strictly controlled, and more information on these can be found on the [RealMe Developers site](https://developers.realme.govt.nz/how-to-integrate/).

See [configuration.md](docs/en/configuration.md) for environment and YML configuration required before the module can be
used.

## Providing RealMe login buttons

By default, the module provides an `Authenticator` class in SilverStripe, adding a new login form. If you want to provide your own separate login form just for RealMe, then the built-in templates can help
with this. They have been designed to integrate as cleanly as possible with Silverstripe templates, but it is up to you
whether you use them, or roll your own.

See the [templates documentation](docs/en/templates.md) for more information on using or modifying these.

## Testing for authentication

The `RealMeService` service object allows you to inject authentication where-ever it is required. For example, let's
take a simple Controller that ensures that all users have a valid RealMe 'FLT' (a unique string that identifies a RealMe
user, but is not their username):

```php
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\RealMe\RealMeService;

class RealMeTestController extends Controller {
	/**
	 * @var RealMeService
	 */
	public $realMeService;

	private static $dependencies = array(
		'realMeService' => '%$SilverStripe\RealMe\RealMeService'
	);

	public function index(HTTPRequest $request) {
		// enforceLogin will redirect the user to RealMe if they're not authenticated, or return true if they are
		// authenticated with RealMe. It should only ever return 'false' if there was an error initialising config
		if($this->realMeService->enforceLogin($request)) {
			$userData = $this->realMeService->getUserData();

			printf("Congratulations, you're authenticated with a unique ID of '%s'!", $userData->SPNameID);
		} else {
			echo "There was an error while attempting to authenticate you.";
		}
	}
}
```

## Appreciation

* Sincere thanks to Jackson (@jakxnz) for his work reviewing and updating pull requests.
