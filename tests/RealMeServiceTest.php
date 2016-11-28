<?php
class RealMeServiceTest extends SapphireTest
{
    private $pathForTempCertificate;

    public function setUpOnce()
    {
        $this->pathForTempCertificate = ASSETS_PATH . '/tmpcert.pem';
    }

    public function testGetCertificateContents()
    {
        // Test standard certificate
        $contents = <<<EOF
Bag Attributes
    friendlyName: mts.client.signing
    localKeyID: 12 34 56 78 90 AA BB CC DD EE FF 
Key Attributes: <No Attributes>
-----BEGIN PRIVATE KEY-----
Redacted private key goes here
-----END PRIVATE KEY-----
Bag Attributes
    friendlyName: mts.client.signing
    localKeyID: 12 34 56 78 90 AA BB CC DD EE FF GG 
subject=/C=NZ/ST=Unknown/L=Unknown/O=Unknown/OU=Unknown/CN=mts.client.signing
issuer=/C=NZ/ST=Unknown/L=Unknown/O=Unknown/OU=Unknown/CN=mts.client.signing
-----BEGIN CERTIFICATE-----
Redacted certificate goes here
-----END CERTIFICATE-----
EOF;

        $path = $this->pathForTempCertificate;
        file_put_contents($path, $contents);

        /** @var RealMeService $service */
        $service = Injector::inst()->get('RealMeService');
        $this->assertEquals('Redacted private key goes here', $service->getCertificateContents($path, 'key'));
        $this->assertEquals('Redacted certificate goes here', $service->getCertificateContents($path, 'certificate'));

        unlink($path);

        // Test certificate with RSA private key
        $contents = <<<EOF
Bag Attributes
    friendlyName: mts.client.signing
    localKeyID: 12 34 56 78 90 AA BB CC DD EE FF 
Key Attributes: <No Attributes>
-----BEGIN RSA PRIVATE KEY-----
Redacted private key goes here
-----END RSA PRIVATE KEY-----
Bag Attributes
    friendlyName: mts.client.signing
    localKeyID: 12 34 56 78 90 AA BB CC DD EE FF GG 
subject=/C=NZ/ST=Unknown/L=Unknown/O=Unknown/OU=Unknown/CN=mts.client.signing
issuer=/C=NZ/ST=Unknown/L=Unknown/O=Unknown/OU=Unknown/CN=mts.client.signing
-----BEGIN CERTIFICATE-----
Redacted certificate goes here
-----END CERTIFICATE-----
EOF;

        $path = $this->pathForTempCertificate;
        file_put_contents($path, $contents);

        /** @var RealMeService $service */
        $service = Injector::inst()->get('RealMeService');
        $this->assertEquals('Redacted private key goes here', $service->getCertificateContents($path, 'key'));
        $this->assertEquals('Redacted certificate goes here', $service->getCertificateContents($path, 'certificate'));

        unlink($path);
    }

    public function tearDownOnce()
    {
        // Ensure $this->pathForTempCertificate is unlink'd (doesn't get unlinked if the test fails)
        if(file_exists($this->pathForTempCertificate)) {
            unlink($this->pathForTempCertificate);
        }
    }
}