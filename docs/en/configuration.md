# RealMe module for SilverStripe

## Configuration

The following values need to be defined in your `_ss_environment.php` file for **all** environments. See the [SilverStripe documentation on environment management](https://docs.silverstripe.org/en/3.1/getting_started/environment_management/) for more information.

| **Environment Const**          | **Example**                     | **Notes**                                                                                                                                                                      |
| ------------------------------ | ------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `REALME_CONFIG_DIR`            | /sites/realme-dev/secure/config | Directory where SimpleSAMLphp configuration will reside. Needs to be writeable by the web server user during setup, and readable afterwards.                                   |
| `REALME_CERT_DIR`              | /sites/realme-dev/secure/certs  | Directory where certificates will reside. All certificates should be placed here. Needs to be readable (but ideally not writeable) by the web server user.                     |
| `REALME_LOG_DIR`               | /sites/realme-dev/logs          | Directory where SimpleSAMLphp logs will reside. Needs to be writeable by the web server user.                                                                                  |
| `REALME_TEMP_DIR`              | /tmp/simplesaml                 | Directory where SimpleSAMLphp can create temporary files. Needs to be writeable by the web server user.                                                                        |
| `REALME_SIGNING_CERT_FILENAME` | mts_saml_sp.pem                 | Name of the SAML secure signing certificate for the required environment. For MTS, this is provided by RealMe, and is available in the RealMe Shared Workspace.                |
| `REALME_MUTUAL_CERT_FILENAME`  | mts_mutual_ssl_sp.pem           | Name of the mutual back-channel secure signing certificate for the required environment. For MTS, this is provided by RealMe, and is available in the RealMe Shared Workspace. |
| `REALME_SIGNING_CERT_PASSWORD` | password                        | Only required if your SAML secure signing certificate (`REALME_SIGNING_CERT_FILENAME`) requires a password to use. Do not define this unless it's required.                    |
| `REALME_MUTUAL_CERT_PASSWORD`  | password                        | Only required if your mutual back-channel secure signing certificate (`REALME_SIGNING_CERT_FILENAME`) requires a password to use. Do not define this unless it's required.     |

In addition to these, YML configuration is required to specify some values that should be consistently applied across environments. These are noted below.

Create a file in your project called for example `mysite/_config/realme.yml`. In this file, specify the following, with appropriate values set. Examples are given below, but should be evaluated for your own application.
```yml
---
Name: realmeproject
---
RealMeService:
  entity_ids:
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
  backchannel_proxy_hosts:
    mts: null
    ite: "env:http_proxy"
    prod: "env:http_proxy"
  backchannel_proxy_ports:
    mts: null
    ite: "env:http_proxy"
    prod: "env:http_proxy"
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
  auth_source_name: 'realme-ite'
---
Name: realmeprod
Only:
  environment: live
After:
  - 'RealMe'
---
RealMeService:
  auth_source_name: 'realme-prod'
---
```

The values you set for `entity_ids` should conform to the RealMe standard for entity IDs. In summary, the domain should be relevant to the agency, the first part of the path should be the privacy realm name, and the second part of the path should be the service name.

The values you set for `authn_contexts` can be one of the following, depending on the requirements of your application:

| **AuthN Context value**                                                                    | **Description**                                                                                                             |
| ------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------- |
| urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:LowStrength                 | Requires a username and password, no second factor of authentication.                                                       |
| urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:ModStrength                 | Requires a username, password, and a moderate-security second factor of authentication (Google Auth, SMS token, RSA token). |
| urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:ModStrength::OTP:Mobile:SMS | Not recommended. Requires a username, password, and specifically requires the use of an SMS token.                          |
| urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:ModStrength::OTP:Token:SID  | Not recommended. Requires a username, password, and specifically requires the use of an RSA token.                          |

If you are wanting to test SMS tokens on the ITE environment, further documentation is available on the RealMe Shared Workspace.

## RealMe Environments

The RealMe system consists of three separate environments - MTS, ITE and Production.

In MTS, you confirm that your setup is correct, and you can correctly parse all the different types of messages that RealMe may pass back to your application.

In ITE, which is equivalent to a pre-prod or staging environment, you confirm that your website will work correctly when deployed to production, using your own secure certificates, and any custom configuration (e.g. `authn_context` values) set.

In production, you allow real users to use RealMe for authentication.

### Configuring for MTS

The required SSL certificates for MTS are provided by the RealMe Operations team, once you have access to the RealMe Shared Workspace. These certificates (at time of writing they are named `mts_saml_sp.pem`, `mts_mutual_ssl_sp.pem`) should be loaded into the directory specified by `REALME_CERT_DIR`.

Once in place, and ensuring the `REALME_SIGNING_CERT_FILENAME` and `REALME_MUTUAL_CERT_FILENAME` consts are defined correctly, you can run the setup task which will validate all provided details, create the configuration files required, and provide you with the XML you need to provide to RealMe.

```bash
cd /path/to/your/webroot
framework/sake dev/tasks/RealMeSetupTask forEnv=mts
```

If any validation errors are found, these will be listed and will need to be fixed. Once you've fixed these, just re-run the setup task above. If you need to change YML configuration, just add flush=1 to the third parameter (e.g. `framework/sake dev/tasks/RealMeSetupTask forEnv=mts\&flush=1`).

If you've already run the setup task, you can re-run it to update configuration files by using `force=1`. 

By default on your development site, the module will use the connection to MTS, so no other changes need to be made. You should now be able to proceed to testing the standard login form, or [using the RealMe templates](templates.md).

If there are difficulties connecting to RealMe using the mutual back-channel SSL certificate (via the `SOAPClient` call), you can use the following `openssl` command to test connectivity outside of PHP to rule out firewall/networking issues (note the paths to the PEM file which may need to change):

```bash
openssl s_client -tls1 -cert /path/to/certificate/directory/mts_mutual_ssl_sp.pem -connect as.mts.realme.govt.nz:443/sso/ArtifactResolver/metaAlias/logon/logonidp
```

### UAT and production environments

The SAML signing and mutual security certificates must be purchased by the agency. More information on SSL certificates can be found in the [SSL Certificates](ssl-certs.md) documentation.

#### When you're hosting on CWP

For UAT and production environments, the above environment consts will be defined for you by CWP Operations once the certificates have been purchased and installed. [Create a Service Desk ticket](https://www.cwp.govt.nz/service-desk/new-request/) to request the start of this process.

#### When you're hosting elsewhere

You will need to purchase and install these certificates yourself in appropriate places on your server, and then set all environment constants appropriately. More information on SSL certificates can be found in the [SSL Certificates](ssl-certs.md) documentation.