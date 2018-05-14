# RealMe module for SilverStripe

## Using the built-in templates

In order to help developers integrate this module with an existing website, the module
provides templates that can be used.

When the module is installed, a new authenticator is registered which allows the login form
to show a template for RealMe login.

RealMe have some strict rules for how you present the RealMe login functionality, please see
the instructions [on the RealMe Developers site](https://developers.realme.govt.nz/how-to-integrate/application-design-and-branding-guide/realme-page-elements/)
for complete details.

Along with the standard large-form login forms, there is also a 'mini' login form, suitable
for use in the header and footer of websites. This can be included by adding a method to your
normal Page_Controller that returns a `new RealMeMiniLoginForm($this, __FUNCTION__);`. This
form uses `GET` rather than `POST`, so is an extension of the normal login form. This bypasses
the requirement to go to `Security/login`, so is only useful when it is the only method of
login to a website.

Further documentation on using these templates can be found in the template files themselves:
[RealMeLoginForm.ss](../../templates/Includes/RealMeLoginForm.ss) and
[RealMeLoginForm_secondary.ss](../../templates/Includes/RealMeLoginForm_secondary.ss).
