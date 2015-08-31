#RealMe for SilverStripe

![RealMe](images/logo.png) [URL Variable Tools](https://www.realme.govt.nz/)

This module provides the foundation to support a quick integration for a SilverStripe application running on the 
common web platform to RealMe as an identity provider. 

##Requirements

SilverStripe 3.1

##Installation

** Composer / Packagist ([best practice](http://doc.silverstripe.org/framework/en/trunk/installation/composer))**  
Add "silverstripe/realme" to your requirements.

** Manually **  
Download, place the folder in your project root called 'realme' and run a dev/build?flush=1.

##Implementing RealMe

@todo

```
 // code
```


##Known issues
url < 80 bytes

certificates MUST be 3years

##Customisation


Check cert connection..
```
openssl s_client -tls1 -cert vendor/simplesamlphp/simplesamlphp/cert/mts_mutual_ssl_sp.pem -connect as.mts.realme.govt.nz:443/sso/ArtifactResolver/metaAlias/logon/logonidp

```