# Change Log for silverstripe-realme

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) 
and this project adheres to [Semantic Versioning](http://semver.org/).

## [2.0.0] - Unreleased
- [Removed]: RealMeSessionData.ss template helper (instead, use `$RealMeUser` directly from your templates)
- [Removed]: Dependency on convoluted SimpleSAMLphp module no longer necessary, now using onelogin/php-saml which is a much cleaner separation of concerns

## [0.9.1] - 2016-04-28
- [Added]: 
- [Changed]: 
- [Deprecated]: 
- [Security]:


## [0.9.0] - 2015-12-08
- [Added]: Initial release, utilising a forked & modified version of [SimpleSAMLphp](https://simplesamlphp.org/) to authenticate.


[Unreleased]: https://github.com/silverstripe/silverstripe-realme/compare/2.0.0...HEAD
[2.0.0]: https://github.com/silverstripe/silverstripe-realme/compare/1.0.0...2.0.0
[1.0.0]: https://github.com/silverstripe/silverstripe-realme/compare/0.9.1...1.0.0
[0.9.1]: https://github.com/silverstripe/silverstripe-realme/compare/0.9.0...0.9.1
[0.9.0]: Initial release