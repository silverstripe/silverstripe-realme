# RealMe module for SilverStripe

## SSL Certificates

Information regarding purchasing and using SSL certificates for RealMe will be documented in this file.

Four certificates must be purchased by the agency - two each for ITE and production environments.

**Note: This is not required if using CWP infrastructure. In CWP, you should [raise a service desk ticket](https://www.cwp.govt.nz/service-desk/new-request/) to begin this process - CWP Operations staff will purchase certificates, install them, and invoice you for this service. These instructions are only necessary when using this module on infrastructure other than CWP. 

### Requirements when purchasing & installing certificates

RealMe places some restrictions on which certificate authorities can be used, and also the type of certificates purchased. Of note, these are:

* SSL Certificates must be purchased from either [RapidSSL](https://www.rapidssl.com/) or [VeriSign](https://www.verisign.com/).
* When purchasing certificates, RealMe requires that three-year expiries are purchased and used.
* The certificate bit length must be 2048 (this is generally the default).
* The serial number must be non-negative (the default).
* The common name on the certificates must be as per RealMe instructions for the different environments - see the below table.

### Certificate naming requirements

Exact instructions can be found in the Technical Architecture document within the RealMe Shared Workspace.

In the table below, `highlighted` text indicates sections of the common name that would be changed when purchasing certificates.

| **Certificate Description**                | **Common Name Example**                      |
| ------------------------------------------ | -------------------------------------------- |
| SAML Signing certificate for ITE           | ite.sa.saml.sig.`realme-demo.cwp.govt.nz`    |
| SAML Mutual SSL certificate for ITE        | ite.sa.mutual.ssl.`realme-demo.cwp.govt.nz`  |
| SAML Signing certificate for production    | prod.sa.saml.sig.`realme-demo.cwp.govt.nz`   |
| SAML Mutual SSL certificate for production | prod.sa.mutual.ssl.`realme-demo.cwp.govt.nz` |

### Manually creating certificate requests

Step One: Generate private key files:
Note the domain names in these commands should be replaced with your own.

```bash
openssl genrsa -out ite.sa.saml.sig.realme-demo.cwp.govt.nz.key 2048
openssl genrsa -out ite.sa.mutual.ssl.realme-demo.cwp.got.nz.key 2048
```

Step Two: Create certificate signing requests:
Note the domain names in these commands should be replaced with your own.

```bash
openssl req -new -key ite.sa.saml.sig.realme-demo.cwp.govt.nz.key -out ite.sa.saml.sig.realme-demo.cwp.govt.nz.csr
openssl req -new -key ite.sa.mutual.ssl.realme-demo.cwp.govt.nz.key -out ite.sa.mutual.ssl.realme-demo.cwp.govt.nz.csr
```

When prompted by `openssl`, use the following parameters:

| **Paramater**            | **Value**                              |
| ------------------------ | -------------------------------------- |
| Country Name             | NZ                                     |
| State or Province Name   | Region of Agency, typically Wellington |
| Locality Name            | City name, typically Wellington        |
| Organisation Name        | Legal name of Agency                   |
| Organisational Unit Name | Leave blank                            |
| Common Name              | See above table for examples           |
| Email Address            | Leave blank                            |
| A challenge password     | Leave blank                            |
| An optional company name | Leave blank                            |

