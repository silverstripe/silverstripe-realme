# RealMe module for SilverStripe

## Configuration

The following values need to be defined in your `_ss_environment.php` file for **all** environments. See the [SilverStripe documentation on environment management](https://docs.silverstripe.org/en/3.1/getting_started/environment_management/) for more information.

| **Environment Const**          | **Example**                     | **Notes**                                                                                                                                                                       |
| ------------------------------ | ------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `REALME_CERT_DIR`              | /sites/realme-dev/secure/certs  | Directory where certificates will reside. All certificates should be placed here. Needs to be readable (but ideally not writeable) by the web server user.                      |
| `REALME_SIGNING_CERT_FILENAME` | mts_saml_sp.pem                 | Name of the SAML secure signing certificate for the required environment. For MTS, this is provided by RealMe, and is available in the RealMe Shared Workspace.                 |
| `REALME_SIGNING_CERT_PASSWORD` | password                        | Only required if your SAML secure signing certificate (`REALME_SIGNING_CERT_FILENAME`) requires a password to use. Do not define this unless it's required. This is deprecated. |

In addition to these, YML configuration is required to specify some values that should be consistently
applied across environments. These are noted below.

Create a file in your project called for example `mysite/_config/realme.yml`. In this file, specify the
following, with appropriate values set. Examples are given below, but should be evaluated for your own
application.

```yml
---
Name: realmeproject
---
RealMeService:
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
  metadata_contact_support_company: "SilverStripe"
  metadata_contact_support_firstnames: "Jane"
  metadata_contact_support_surname: "Smith"
---
Name: realmetest
Only:
  environment: test
After:
  - 'RealMe'
---
RealMeService:
  realme_env: 'ite'
---
Name: realmeprod
Only:
  environment: live
After:
  - 'RealMe'
---
RealMeService:
  realme_env: 'prod'
---
```

The value you set for `realme_env` must be one of 'mts', 'ite' or 'prod'.

The value you set for `integration_type` must be one of 'login' or 'assert'.

The values you set for `sp_entity_ids` should conform to the RealMe standard for entity IDs. In summary, the
domain should be relevant to the agency, the first part of the path should be the privacy realm name, and
the second part of the path should be the service name. 

#### Note: the service name cannot be more than 10 characters in length, or the validation will fail.

The values you set for `authn_contexts` can be one of the following, depending on the requirements of your
application:

| **AuthN Context value**                                                                    | **Description**                                                                                                             |
| ------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------- |
| urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:LowStrength                 | Requires a username and password, no second factor of authentication.                                                       |
| urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:ModStrength                 | Requires a username, password, and a moderate-security second factor of authentication (Google Auth, SMS token, RSA token). |
| urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:ModStrength::OTP:Mobile:SMS | Not recommended. Requires a username, password, and specifically requires the use of an SMS token.                          |
| urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:ModStrength::OTP:Token:SID  | Not recommended. Requires a username, password, and specifically requires the use of an RSA token.                          |

*Note:* The AuthN context must be set to 'ModStrength' if you are using the 'assert' integration type, low strength is 
not available for this integration type.

If you are wanting to test SMS tokens on the ITE environment, further documentation is available on the RealMe
Shared Workspace.

## RealMe Environments

The RealMe system consists of three separate environments - MTS, ITE and Production.

In MTS, you confirm that your setup is correct, and you can correctly parse all the different types of
messages that RealMe may pass back to your application.

In ITE, which is equivalent to a pre-prod or staging environment, you confirm that your website will work
correctly when deployed to production, using your own secure certificates, and any custom configuration
(e.g. `authn_context` values) set.

In production, you allow real users to use RealMe for authentication.

### Configuring for MTS

The required SSL certificates for MTS are provided by the RealMe Operations team, once you have access to
the RealMe Shared Workspace. These certificates (at time of writing they are named `mts_saml_sp.pem`,
`mts_mutual_ssl_sp.pem`) should be loaded into the directory specified by `REALME_CERT_DIR`.

You will also need to place `mts_saml_idp.cer` into the same directory, however this file as provided by
RealMe is incorrect and requires a minor edit.

* On the first line of the file, before the certificate starts, you need to add the following: `-----BEGIN CERTIFICATE-----`
* Add a new line to the end of the file, after the certificate ends, and add the following: `-----END CERTIFICATE-----`

The file should now look something like this:
```
-----BEGIN CERTIFICATE-----
MIIECT...
...
...
-----END CERTIFICATE-----
```

Once in place, and ensuring the `REALME_SIGNING_CERT_FILENAME` and `REALME_MUTUAL_CERT_FILENAME` consts are
defined correctly, you can run the setup task which will validate all provided details, create the
configuration files required, and provide you with the XML you need to provide to RealMe.

If you are developing locally, note that the module enforces your environment to be configured for https.
If you don't have this setup by default, [ngrok](https://ngrok.com/download) is a nice, easy to use tool
that provides this functionality. You just run ngrok, and copy the https URL that it gives you - this will
let you access your site protected via https, however you will need to ensure you set the `SS_TRUSTED_PROXY_IPS`
const in your _ss_environment.php , e.g. `define('SS_TRUSTED_PROXY_IPS', '*');` so that we know that ngrok is
trust-worthy and allowed to pass http traffic as https.

If you do this, ngrok will give you a random URL each time you start it, which means that you will need to
change the above YML configuration and re-run the below task every time you restart ngrok. Alternatively,
set this up on a development server that has the capability to perform SSL communication natively. You
can use self-signed certificates if required.

Run the below task as the user that your web server runs as (for example, the `www-data` or `httpd` user).

```bash
cd /path/to/your/webroot
framework/sake dev/tasks/RealMeSetupTask forEnv=mts
```

If any validation errors are found, these will be listed and will need to be fixed. Once you've fixed these,
just re-run the setup task above. If you need to change YML configuration, just add flush=1 to the third
parameter (e.g. `framework/sake dev/tasks/RealMeSetupTask forEnv=mts\&flush=1`).

If you've already run the setup task, you can re-run it to update configuration files by using `force=1`. 

The above command will generate a screen of XML configuration. This needs to be copied into a new XML file
and [uploaded to MTS here](https://mts.realme.govt.nz/logon-mts/metadataupdate) in order to verify
bi-directional communication between the RealMe MTS servers and your local development environment.
Note that this means the URLs you use to access the website cannot change - if you do change them,
you will need to re-run the `RealMeSetupTask` and re-upload the resulting XML to RealMe.

By default on your development site, the module will use the connection to MTS, so no other changes
need to be made. You should now be able to proceed to testing the standard login form, or
[using the RealMe templates](templates.md).

If there are difficulties connecting to RealMe using the mutual back-channel SSL certificate (via the
`SOAPClient` call), you can use the following `openssl` command to test connectivity outside of PHP
to rule out firewall/networking issues (note the paths to the PEM file which may need to change):

```bash
openssl s_client -tls1 -cert /path/to/certificate/directory/mts_mutual_ssl_sp.pem -connect as.mts.realme.govt.nz:443/sso/ArtifactResolver/metaAlias/logon/logonidp
```

### UAT and production environments

The SAML signing and mutual security certificates must be purchased by the agency. More information
on SSL certificates can be found in the [SSL Certificates](ssl-certs.md) documentation.

#### When you're hosting on CWP

For UAT and production environments, the above environment consts will be defined for you by CWP Operations
once the certificates have been purchased and installed.
[Create a Service Desk ticket](https://www.cwp.govt.nz/service-desk/new-request/) to request the start of
this process.

#### When you're hosting elsewhere

You will need to purchase and install these certificates yourself in appropriate places on your server,
and then set all environment constants appropriately. More information on SSL certificates can be found
in the [SSL Certificates](ssl-certs.md) documentation.
