<?php

use OneLogin\Saml2\Auth;

class RealMeServiceTest extends SapphireTest
{
    private $pathForTempCertificate;

    /**
     * @var RealMeService
     */
    private $service;

    public function testGetCertificateContents()
    {
        $this->pathForTempCertificate = ASSETS_PATH . '/tmpcert.pem';

        /**
         * Test standard certificate
         */

        $contents = file_get_contents(BASE_PATH . '/realme/tests/certs/standard_cert.pem');

        // Strip carriage returns
        $contents = str_replace("\r", '', $contents);

        $path = $this->pathForTempCertificate;
        file_put_contents($path, $contents);

        /** @var RealMeService $service */
        $service = Injector::inst()->get('RealMeService');

        $this->assertEquals('Redacted private key goes here', $service->getCertificateContents($path, 'key'));
        $this->assertEquals('Redacted certificate goes here', $service->getCertificateContents($path, 'certificate'));

        unlink($path);

        /**
         * Test certificate with RSA private key
         */

        $contents = file_get_contents(BASE_PATH . '/realme/tests/certs/rsa_cert.pem');

        // Strip carriage returns
        $contents = str_replace("\r", '', $contents);

        $path = $this->pathForTempCertificate;
        file_put_contents($path, $contents);

        /** @var RealMeService $service */
        $service = Injector::inst()->get('RealMeService');
        $this->assertEquals('Redacted private key goes here', $service->getCertificateContents($path, 'key'));
        $this->assertEquals('Redacted certificate goes here', $service->getCertificateContents($path, 'certificate'));

        unlink($path);
    }

    public function testGetAuth()
    {
        $auth = $this->service->getAuth();
        $this->assertTrue(get_class($auth) === Auth::class);

        // Service Provider settings
        $spData = $auth->getSettings()->getSPData();
        $this->assertSame('https://example.com/realm/service', $spData['entityId']);
        $this->assertSame('https://example.com/Security/realme/acs', $spData['assertionConsumerService']['url']);
        $this->assertSame('urn:oasis:names:tc:SAML:2.0:nameid-format:persistent', $spData['NameIDFormat']);

        // Identity Provider settings
        $idpData = $auth->getSettings()->getIdPData();
        
        $expected = 'https://login.mts.realme.govt.nz/4af8e0e0-497b-4f52-805c-00fa09b50c16' .
                '/B2C_1A_DIA_RealMe_MTSLoginService';
        $this->assertSame($expected, $idpData['entityId']);

        $expected = 'https://login.mts.realme.govt.nz/b2cdiamts01rmpubdir.onmicrosoft.com' .
                '/B2C_1A_DIA_RealMe_MTSLoginService/samlp/sso/login';
        $this->assertSame($expected, $idpData['singleSignOnService']['url']);

        // Security settings
        $securityData = $auth->getSettings()->getSecurityData();
        $this->assertSame('urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:LowStrength', $securityData['requestedAuthnContext'][0]);
    }

    public function testGetAuthCustomSPEntityId()
    {
        Config::inst()->update('RealMeService', 'sp_entity_ids', ['mts' => 'https://example.com/custom-realm/custom-service']);
        $spData = $this->service->getAuth()->getSettings()->getSPData();
        $this->assertSame('https://example.com/custom-realm/custom-service', $spData['entityId']);
    }

    public function testGetAuthCustomIdPEntityId()
    {
        Config::inst()->update('RealMeService', 'idp_entity_ids', ['mts' => [ 'login' => 'https://example.com/idp-entry']]);
        $idpData = $this->service->getAuth()->getSettings()->getIdPData();
        $this->assertSame('https://example.com/idp-entry', $idpData['entityId']);
    }

    public function testGetAuthCustomAuthnContext()
    {
        Config::inst()->update('RealMeService', 'authn_contexts', ['mts' => 'urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:ModStrength::OTP:Mobile:SMS']);
        $securityData = $this->service->getAuth()->getSettings()->getSecurityData();
        $this->assertSame('urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:ModStrength::OTP:Mobile:SMS', $securityData['requestedAuthnContext'][0]);
    }

    public function setUpOnce()
    {
        parent::setUpOnce();

        if(defined('REALME_CERT_DIR') || defined('REALME_SIGNING_CERT_FILENAME')) {
            die('You must not have REALME_CERT_DIR or REALME_SIGNING_CERT_FILENAME defined for the tests to run');
        }

        define('REALME_CERT_DIR', BASE_PATH . '/realme/tests/certs');
        define('REALME_SIGNING_CERT_FILENAME', 'standard_cert.pem');
    }

    public function setUp()
    {
        parent::setUp();
        $this->service = Injector::inst()->get('RealMeService');

        // Configure for login integration and mts by default
        Config::inst()->update('RealMeService', 'sp_entity_ids', ['mts' => 'https://example.com/realm/service']);
        Config::inst()->update('RealMeService', 'metadata_assertion_service_domains', ['mts' => 'https://example.com']);
        Config::inst()->update('RealMeService', 'authn_contexts', ['mts' => 'urn:nzl:govt:ict:stds:authn:deployment:GLS:SAML:2.0:ac:classes:LowStrength']);
    }

    public function tearDownOnce()
    {
        parent::tearDownOnce();

        // Ensure $this->pathForTempCertificate is unlink'd (otherwise it won't get unlinked if the test fails)
        if(file_exists($this->pathForTempCertificate)) {
            unlink($this->pathForTempCertificate);
        }
    }
}
