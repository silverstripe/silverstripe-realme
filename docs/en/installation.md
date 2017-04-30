# Installation of the RealMe module

The module is best installed via Composer, by adding the below to your composer.json. For now, we need to specify a 
custom version of the excellent onelogin/php-saml module to fix some XMLDSig validation errors with the RealMe XML 
responses, hence the custom `repositories` section.

```
{
    "require": {
        "silverstripe/realme": "dev-pulls/onelogin",
        "onelogin/php-saml": "dev-tmp/remove-sig-validation as 2.10.2"
    },
    
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/madmatt/php-saml.git"
        }
    ]
}
```

Once installation is completed, configuration is required before this module will work. See the 
[configuration section](configuration.md) for full details.