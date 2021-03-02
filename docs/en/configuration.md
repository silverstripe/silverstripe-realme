# RealMe module for SilverStripe

## Configuration

### Environment variables and certificates

The following values need to be defined in your `.env` file for **all** environments. See the [SilverStripe documentation on environment management](https://docs.silverstripe.org/en/3.1/getting_started/environment_management/) for more information.

| **Environment Const**          | **Example**                     | **Notes**                                                                                                                                                                                    |
| ------------------------------ | ------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `REALME_CERT_DIR`              | /sites/realme-dev/secure/certs  | Directory where all certificates will reside. All certificates should be placed here. Needs to be readable (but ideally not writeable) by the web server user.                               |
| `REALME_SIGNING_CERT_FILENAME` | mts_saml_sp.pem                 | Name of the SAML secure signing certificate for the required environment (stored in `REALME_CERT_DIR`). For MTS, this is provided by RealMe, and is available on the RealMe developers site. |

It is important to note that the file referred to by `REALME_SIGNING_CERT_FILENAME` is expected to be in [PEM format](https://en.wikipedia.org/wiki/Privacy-Enhanced_Mail), containing both the private key and the certificate (and optionally any intermediary certificates). If your files are not structured this way it can be easily created by e.g. `cat yoursite.crt yoursite.ca-bundle yoursite.key > yoursite.pem` provided each file has the appropriate `-----BEGIN *-----` and `-----END *-----` headers & footers.

The `REALME_CERT_DIR` needs to contain the following files, depending on which environment you are integrating with:

#### For MTS
You must include `mts_saml_sp.pem` and either `mts_login_saml_idp.cer` or `mts_assert_saml_idp.cer` (depending on whether you are integration for logon or assert) from the MTS bundle available on the RealMe Developers website. Place both of these in your `REALME_CERT_DIR`.

#### For ITE
You must include your private key and signing certificate (PEM file) and then from the ITE integration bundle, take the `realme_signing.crt` file and rename it to `ite.signing.logon.realme.govt.nz.cer` (which is the Common Name on the certificate) and place this in your `REALME_CERT_DIR`.

#### For Production
You must include your private key and signing certificate (PEM file) and then from the Production integration bundle, take the `<filename unknown>` file and rename it to `<tbc>` (which is the Common Name on the certificate) and place this in your `REALME_CERT_DIR`.

## YML configuration
In addition to these environment variables, YML configuration is required to specify some values that should be consistently applied across
environments. These are noted below.

Create a file in your project called for example `mysite/_config/realme.yml`. In this file, specify the following, with
appropriate values set. Examples are given below, but should be evaluated for your own application.

Note that the below configuration assumes that you are using the `SS_ENVIRONMENT_TYPE` const correctly on your
development, staging/test and production environments.

```yaml

---
Name: realmedev
---
SilverStripe\RealMe\RealMeService:
  realme_env: 'mts'
  integration_type: 'login'
  sp_entity_ids:
    mts: "https://dev.your-website.govt.nz/p-realm/s-name"
    ite: "https://uat.your-website.govt.nz/p-realm/s-name"
    prod: "https://www.your-website.govt.nz/p-realm/s-name"
  authn_contexts:
    mts: "urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:LowStrength"
    ite: "urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:LowStrength"
    prod: "urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:LowStrength"
  metadata_assertion_service_domains:
    mts: "https://dev.your-website.govt.nz/"
    ite: "https://uat.your-website.govt.nz"
    prod: "https://www.your-website.govt.nz/"
  metadata_organisation_name: "RealMe Demo Organisation"
  metadata_organisation_display_name: "RealMe Demo Organisation"
  metadata_organisation_url: "https://realme-demo.govt.nz"
  metadata_contact_support_company: "Your Company"
  metadata_contact_support_firstnames: "Your"
  metadata_contact_support_surname: "Name"

SilverStripe\RealMe\Authenticator\LoginForm:
  service_name_1: "this website"
  service_name_2: "this website"
  service_name_3: "this website"

---
Name: realmetest
Only:
  environment: test
After:
  - 'realmedev'
---
SilverStripe\RealMe\RealMeService:
  realme_env: 'ite'

---
Name: realmeprod
Only:
  environment: live
After:
  - 'realmedev'
---
SilverStripe\RealMe\RealMeService:
  realme_env: 'prod'

```

The value you set for `realme_env` must be one of 'mts', 'ite' or 'prod'.

The value you set for `integration_type` must be one of 'login' or 'assert'.

The values you set for `sp_entity_ids` should conform to the RealMe standard for entity IDs. In summary, the
domain should be relevant to the agency, the first part of the path should be the privacy realm name, and
the second part of the path should be the service name.

The values for `service_name_1`, `service_name_2` and `service_name_3` should fit in these sentences:

* `service_name_1`: "To access the [online service], you need a RealMe login."
* `service_name_2`: "To log in to [this service] you need a RealMe login."
* `service_name_3`: "[This service] uses RealMe login to secure and protect your personal information."

**Note:** None of these are required for the assert form, as they are not used (it only uses organisation name, which is
taken from the `metadata_organisation_name` config value instead.

**Note:** the service name cannot be more than 10 characters in length, or the validation will fail.

The values you set for `authn_contexts` can be one of the following, depending on the requirements of your
application:

| **AuthN Context value**                                                                    | **Description**                                                                                                             |
| ------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------- |
| urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:LowStrength                 | Requires a username and password, no second factor of authentication.                                                       |
| urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:ModStrength                 | Requires a username, password, and a moderate-security second factor of authentication (Google Auth, SMS token, RSA token). |
| urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:ModStrength::OTP:Mobile:SMS | Not recommended. Requires a username, password, and specifically requires the use of an SMS token.                          |
| urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:ModStrength::OTP:Token:SID  | Not recommended. Requires a username, password, and specifically requires the use of an RSA token.                          |

**Note:** The AuthN context must be set to 'ModStrength' if you are using the 'assert' integration type, low strength is
not available for this integration type.

If you are wanting to test SMS tokens on the ITE environment, further documentation is available on the [RealMe developers site](https://developers.realme.govt.nz/how-to-integrate/testing-tools/).

## RealMe Environments

The RealMe system consists of three separate environments - MTS, ITE and Production.

In MTS, you confirm that your setup is correct, and you can correctly parse all the different types of messages that
RealMe may pass back to your application.

In ITE, which is equivalent to a pre-prod or staging environment, you confirm that your website will work correctly when
deployed to production, using your own secure certificates, and any custom configuration (e.g. `authn_context` values)
set.

In production, you allow real users to use RealMe for authentication.

### MTS: [Messaging Test Environment](https://mts.realme.govt.nz/logon-mts/home)

The development environment is known as MTS. This environment is setup to allow testing of your code on your development
environment. In this environment, RealMe provide all SSL certificates required to communicate.

- Review the documentation on the 'Try it out now' page on the [RealMe Developers site](https://developers.realme.govt.nz/try-it-out-now/).
- Download the integration bundle from the [RealMe Developers site](https://developers.realme.govt.nz/try-it-out-now/).
- Unpack the following three certificates into the directory you've specified in `REALME_CERT_DIR` (outside of your webroot):
    - `mts_assert_saml_idp.cer`
    - `mts_login_saml_idp.cer`
    - `mts_saml_sp.pem`
- The `mts_assert_saml_idp.cer` and `mts_login_saml_idp.cer` files are not correctly provided. You will need to manually add the following to the files:
    - Add a new line as line 1 of the file with the following: `-----BEGIN CERTIFICATE-----`
    - Add a new line as the last line of the file with the following: `-----END CERTIFICATE-----`
- Ensure your `realme.yml` [configuration](docs/en/configuration.md) is complete (see above).
- Run the RealMe build task to validate your configuration and get the XML metadata to provide to MTS: `vendor/bin/sake dev/tasks/RealMeSetupTask forEnv=mts`
- Save the XML output from the above task to an XML file, and upload this to MTS:
    - For a 'logon' integration, submit here: [MTS logon metadata upload](https://mts.realme.govt.nz/logon-mts/metadataupdate).
    - For an 'assert' integration, submit here: [MTS assert metadata upload](https://mts.realme.govt.nz/realme-mts/metadata/import.xhtml).
- Either use the `$RealMeLoginForm` global template variable or add the `RealMeAuthenticator` and access `/Security/login`.
- Once authenticated, you can access user data from templates using `$RealMeUser` (e.g. `$RealMeUser.SPNameID`), or in a controller by using `RealMeService::currentRealMeUser()`.

If you are developing locally, note that the module enforces your environment to be configured for https. If you don't
have this setup by default, [ngrok](https://ngrok.com/download) is a nice, easy to use tool that provides this
functionality. You just run ngrok, and copy the https URL that it gives you - this will let you access your site
protected via https, however you will need to ensure you set the `SS_TRUSTED_PROXY_IPS` const in your
_ss_environment.php, e.g. `define('SS_TRUSTED_PROXY_IPS', '*');` so that we know that ngrok is trust-worthy and allowed
to pass http traffic as https.

If you do this, ngrok will give you a random URL each time you start it, which means that you will need to change the
above YML configuration and re-integrate to MTS every time you restart ngrok. Alternatively, set this up on a
development server that has the capability to perform SSL communication natively. You can use self-signed certificates
if required.

You should now be able to proceed to testing the standard login form, or [using the RealMe templates](templates.md).

### ITE: Integration Test Environment

- Complete an integration to MTS.
- You will need a secure certificate which meets the requirements as seen on the [Certificate requirements](https://developers.realme.govt.nz/how-realme-works/certificate-requirements/) page.
    - If you are using the Common Web Platform, once you have the certificate raise a ticket on the [CWP Service desk](https://www.cwp.govt.nz/service-desk/new-request/) to get it installed.
    - Otherwise, you can generate one yourself and install it into your test or staging environment.
- Request an account on the [RealMe Developers site](https://developers.realme.govt.nz/), and complete an integration request for ITE.
- Publish your site to your test or staging environment with a working configuration (`realme.yml` file) for ITE.

### PROD: Production Environment

- Complete an integration to MTS and ITE.
- Follow the steps as for the ITE environment above, but creating an integration request for production rather than ITE.

## More complex environments
If you are working with multiple website environments (e.g. multiple test sites or similar), you will encounter issues
using the basic configuration system above, because you will want multiple different websites to point to different RealMe
integrations (e.g. 'test1' website points to MTS, while 'test2' and 'staging' websites both point to ITE). To do this,
each website installation needs a separate RealMe integration (that is, a different SP Entity ID). Once these are
configured with RealMe, you can switch them out by modifying configuration. For now (until a solution to #26 is built),
the best solution is to use `RealMeService::config()->set()` for SS4. **Note:** This can cause a significant performance impact in SilverStripe 4, as by default YML configuration is immutable, and this overrides this which is not ideal. Use this only if it's really necessary, and ensure that the majority of your environments are still configured via YML as normal.

In your app/\_config.php:

```php
use \SilverStripe\RealMe\RealMeService

$changed = false;
$entityIds = RealMeService::config()->get('sp_entity_ids');
$domains = RealMeService::config()->get('metadata_assertion_service_domains');

// Update the configured entity IDs and return domains based on however you know what site you're on
if (getenv('SITE_ENVIRONMENT') == 'test2')) {
    $entityIds['ite'] = 'https://test2-domain.example.com/privacy-realm/service-name';
    $domains['ite'] = 'https://test2-domain.example.com';
    $changed = true;
} elseif(getenv('SITE_ENVIRONMENT') == 'staging')) {
    $entityIds['ite'] = 'https://staging-domain.example.com/privacy-realm/service-name';
    $domains['ite'] = 'https://staging-domain.example.com';
    $changed = true;
}

// Only mutate config if it's really necessary
if ($changed) {
    RealMeService::config()->set('sp_entity_ids', $entityIds);
    RealMeService::config()->set('metadata_assertion_service_domains', $domains);
}
```

This will allow you, if necessary, to re-configure the module in real-time based on the website environment. Note that you will still need to deploy the correct private/public keypairs to the correct servers etc.

## Syncing Realme with SilverStripe members
After logging in the module can sync the attributes returned from RealMe (depending on your assertion type) and sync the
details with the appropriate members. This is not available for `assert` type authenticaiton as the unique identifier is
valid only for that session, meaning each time a user logged in they would have a new `Member` object created for them,
and any associated historic user activity would be lost to them.

To setup syncing, you **must** be using the `login` type of authentication and have the `RealMeMemberExtension` enabled
on `Member` (or a subclass of it) and then tell the module to sync with the database via the following configuration in
realme.yml. You can also include `login_member_after_authentication` which will automatically login a user (as a
Silverstripe `Member` object) after successful RealMe authentication.
 
```yaml
SilverStripe\Security\Member:
  extensions:
    - SilverStripe\RealMe\Extension\MemberExtension

SilverStripe\RealMe\RealMeService:
  sync_with_local_member_database: true
  login_member_after_authentication: true
```

Run a `dev/build` to ensure the configuration changes are accounted for.

When a RealMe login completes with success, a new member will be synced based on the RealMe FLT. If no member matching the
FLT is found, a new member will be created. _Note this is not supported for `assert`, as the FIT is transient (changes each
time a member logs in)._

### UAT and production environments

The SAML signing security certificates must be purchased by the agency. If you are hosting on the Common Web Platform,
the [CWP Service desk](https://www.cwp.govt.nz/service-desk/new-request/) can help generating the certificate signing
request (CSR) and installing the certificate once purchased by the agency. More information on the requirements can be
found on the [RealMe developers site](https://developers.realme.govt.nz/how-realme-works/certificate-requirements/).

#### When you're hosting on CWP

For UAT and production environments, the above environment consts will be defined for you by CWP Operations once the
certificates have been purchased and installed. [Create a Service Desk ticket](https://www.cwp.govt.nz/service-desk/new-request/)
to request the start of this process.

#### When you're hosting elsewhere

You will need to purchase and install these certificates yourself in appropriate places on your server,
and then set all environment constants appropriately.
