# Change Log for silverstripe-realme

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [2.0.0] - Unreleased
- [Added]: RealMeService::currentRealMeUser() added for accessing the valid realme user from anywhere
- [Changed]: RealMeService now implements TemplateGlobalProvider
- [Changed]: RealMeService::getUserData() functionality moved to RealMeService::user_data()
- [Removed]: SiteTree::RealMeUser() use RealMeService::currentRealMeUser() instead
- [Removed]: RealMeDataExtension.php and associated SiteTree/SiteConfig extension definitions in extensions.yml
- [Removed]: RealMeSessionData.ss template helper (instead, use `$RealMeUser` directly from your templates)
- [Removed]: Dependency on convoluted SimpleSAMLphp module no longer necessary, now using onelogin/php-saml
- [Changed]: Module no longer uses the HTTP-Artifact binding, it now supports HTTP-Post binding only
- [Removed]: `REALME_MUTUAL_CERT_FILENAME`, `REALME_MUTUAL_CERT_PASSWORD`, `REALME_LOG_DIR`, `REALME_TEMP_DIR` constants
- [Deprecated]: `REALME_SIGNING_CERT_PASSWORD` should no longer be required, marked for removal


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
