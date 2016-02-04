<?php
/**
 * The main configuration file of SimpleSAMLphp.
 *
 * This is a template file, which is processed by `RealMeSetupTask` and dropped into the SimpleSAMLphp configuration
 * directory. This file should not be directed edited once it is in place within the SimpleSAMLphp codebase, instead the
 * template file should be edited, and then the RealMeSetupTask re-run with force=1 set to override existing files.
 *
 * This template is designed to be used within the `RealMeSetupTask` code, and has {{variables}} scattered throughout to
 * add custom configuration where required.
 *
 * To see all variables that can be included here, and documentation around them, check out SimpleSAMLphp's config.php
 * template here: https://github.com/simplesamlphp/simplesamlphp/blob/master/config-templates/config.php
 *
 * @see RealMeSetupTask::run()
 */
$config = array(
    'baseurlpath' => '{{baseurlpath}}',
    'certdir' => '{{certdir}}',
    'loggingdir' => '{{loggingdir}}',
    'datadir' => 'data/',
    'tempdir' => '{{tempdir}}',
    'metadatadir' => '{{metadatadir}}',

    'debug' => false,
    'showerrors' => false,
    'errorreporting' => false,
    'debug.validatexml' => false,

    'auth.adminpassword' => '{{adminpassword}}',
    'admin.protectindexpage' => true,
    'admin.protectmetadata' => true,

    'secretsalt' => '{{secretsalt}}',

    'timezone' => null,

    'logging.level' => SimpleSAML_Logger::DEBUG,
    'logging.handler' => 'file',
    'logging.facility' => defined('LOG_LOCAL5') ? constant('LOG_LOCAL5') : LOG_USER,
    'logging.processname' => 'simplesamlphp',
    'logging.logfile' => 'simplesamlphp.log',

    'enable.saml20-idp' => false,
    'enable.shib13-idp' => false,
    'enable.adfs-idp' => false,
    'enable.wsfed-sp' => false,
    'enable.authmemcookie' => false,

    'store.type' => 'phpsession',
    'session.duration' => 28800, // 8 hours
    'session.datastore.timeout' => 14400, // 4 hours
    'session.state.timeout' => 3600, // 1 hour
    'session.cookie.name' => null,
    'session.cookie.lifetime' => 0, // 0 means expires immediately
    'session.cookie.path' => '/',
    'session.cookie.domain' => null,
    'session.cookie.secure' => false,
    'session.phpsession.cookiename' => null,
    'session.phpsession.savepath' => null,
    'session.phpsession.httponly' => true,
    'session.authtoken.cookiename' => 'SimpleSAMLAuthToken',
    'session.rememberme.enable' => false,
    'session.rememberme.checked' => false,
    'session.rememberme.lifetime' => 0,

    'enable.http_post' => false,

    'language.available' => array('en'),
    'language.default' => 'en',

    'shib13.signresponse' => true,

    'metadata.sign.enable' => false,
    'metadata.sign.privatekey' => null,
    'metadata.sign.privatekey_pass' => null,
    'metadata.sign.certificate' => null,

    'trusted.url.domains' => null,
);
