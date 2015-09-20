# RealMe module for SilverStripe

## Configuration

The following values need to be defined in your `_ss_environment.php` file for **all** environments. See the [SilverStripe documentation on environment management](https://docs.silverstripe.org/en/3.1/getting_started/environment_management/) for more information.

| **Environment Const**         | **Example**                                    | **Notes**                                                                                                                                                                      |
| ----------------------------- | ---------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| REALME_CERT_DIR               | /sites/realme-dev/secure/certs                 | Directory where certificates will reside. All certificates should be placed here. Needs to be readable (but ideally not writeable) by the web server user.                     |
| REALME_LOGGING_DIR            | /sites/realme-dev/logs                         | Directory where SimpleSAMLphp logs will reside. Needs to be writeable by the web server user.                                                                                  |
| REALME_TEMP_DIR               | /tmp/simplesaml                                | Directory where SimpleSAMLphp can create temporary files. Needs to be writeable by the web server user.                                                                        |
| REALME_SIGNING_CERT_FILENAME  | mts_saml_sp.pem                                | Name of the SAML secure signing certificate for the required environment. For MTS, this is provided by RealMe, and is available in the RealMe Shared Workspace.                |
| REALME_MUTUAL_CERT_FILENAME   | mts_mutual_ssl_sp.pem                          | Name of the mutual back-channel secure signing certificate for the required environment. For MTS, this is provided by RealMe, and is available in the RealMe Shared Workspace. |

### UAT and production environments

The SAML signing and mutual security certificates must be purchased by the agency. More information on SSL certificates can be found in the [SSL Certificates](ssl-certs.md) documentation.

#### When you're hosting on CWP

For UAT and production environments, the above environment consts will be defined for you by CWP Operations once the certificates have been purchased and installed.

#### When you're hosting elsewhere

You will need to purchase and install these certificates yourself in appropriate places on your server, and then set the `REALME_SIGNING_CERT_FILENAME` and `REALME_MUTUAL_CERT_FILENAME` consts appropriately. More information on how to do this can be found in the [SSL Certificates](ssl-certs.md) documentation.