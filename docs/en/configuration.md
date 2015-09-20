# RealMe module for SilverStripe

## Configuration

### Local development environments
The following values need to be defined in your `_ss_environment.php` file. See the [SilverStripe documentation on environment management](https://docs.silverstripe.org/en/3.1/getting_started/environment_management/) for more information.

| Environment Const                 | Example                                        | Notes                                                                                                                                                                  |
| --------------------------------- | ---------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| REALME_CERT_DIR                   | /sites/realme-dev/secure/certs                 | Directory where certificates will reside. All certificates should be placed here. Needs to be readable (but ideally not writeable) by the web server user.             |
| REALME_LOGGING_DIR                | /sites/realme-dev/logs                         | Directory where SimpleSAMLphp logs will reside. Needs to be writeable by the web server user.                                                                          |
| REALME_TEMP_DIR                   | /tmp/simplesaml                                | Directory where SimpleSAMLphp can create temporary files. Needs to be writeable by the web server user.                                                                |
| REALME_MTS_SIGNING_CERT_FILENAME  | mts_saml_sp.pem                                | Name of the SAML secure signing certificate. For MTS, this is provided by RealMe, and is available in the RealMe Shared Workspace.                                     |
| REALME_MTS_MUTUAL_CERT_FILENAME   | mts_mutual_ssl_sp.pem                          | Name of the mutual back-channel secure signing certificate. For MTS, this is provided by RealMe, and is available in the RealMe Shared Workspace.                      |
| REALME_ITE_SIGNING_CERT_FILENAME  | ite.sa.saml.sig.realme-demo.cwp.govt.nz.pem    | Name of the SAML secure signing certificate. For ITE, this must be purchased by the agency. See [SSL Certificates](ssl-certs.md) for more information.                 |
| REALME_ITE_MUTUAL_CERT_FILENAME   | ite.sa.mutual.ssl.realme-demo.cwp.govt.nz.pem  | Name of the mutual back-channel secure signing certificate. For ITE, this must be purchased by the agency. See [SSL Certificates](ssl-certs.md) for more information.  |
| REALME_PROD_SIGNING_CERT_FILENAME | prod.sa.saml.sig.realme-demo.cwp.govt.nz.pem   | Name of the SAML secure signing certificate. For prod, this must be purchased by the agency. See [SSL Certificates](ssl-certs.md) for more information.                |
| REALME_PROD_MUTUAL_CERT_FILENAME  | prod.sa.mutual.ssl.realme-demo.cwp.govt.nz.pem | Name of the mutual back-channel secure signing certificate. For prod, this must be purchased by the agency. See [SSL Certificates](ssl-certs.md) for more information. |

### UAT and production environments

#### When you're hosting on CWP

#### When you're hosting elsewhere