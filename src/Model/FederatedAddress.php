<?php

namespace SilverStripe\RealMe\Model;

use DOMDocument;
use DOMXPath;
use SilverStripe\View\ViewableData;

/**
 * Class FederatedAddress
 *
 * Contains data to describe the address returned as part of an identity.
 *
 * @see https://developers.realme.govt.nz/how-realme-works/verified-address-data/
 */
class FederatedAddress extends ViewableData
{
    /**
     * Types of addresses
     */
    const TYPE_STANDARD = 'NZStandard';
    const TYPE_RURAL_DELIVERY = 'NZRuralDelivery';

    /**
     * @var string The type of address, either NZStandard or NZRuralDelivery
     */
    public $AddressType;

    /**
     * @var string Date when this address was marked as verified
     */
    public $VerificationDate;

    /**
     * @var string Undocumented in RealMe messaging spec, generally describes the quality of the address information
     */
    public $DataQuality;

    /**
     * @var string Street number, suffix, and the name of the street, e.g 103 Courtenay Place
     */
    public $NZNumberStreet;

    /**
     * @var string String representing the RD number of this address, e.g RD 123. Required if the address type is rural
     */
    public $NZRuralDelivery;

    /**
     * @var string Optional name of the suburb for this city, e.g Te Aro
     */
    public $NZSuburb;

    /**
     * @var string Name of the town or city for this address, e.g Wellington
     */
    public $NZTownOrCity;

    /**
     * @var string Alphanumeric 4 digit string representing a postcode with leading zeroes, e.g 6011 or 0002
     */
    public $NZPostCode;

    /**
     * Constructor that sets the expected federated identity details based on a provided DOMDocument. The expected XML
     * structure for the DOMDocument is the following:
     *
     * <?xml version="1.0" encoding="UTF-8"?>
     * <p:Party
     *  xmlns:p="urn:oasis:names:tc:ciq:xpil:3"
     *  xmlns:a="urn:oasis:names:tc:ciq:xal:3">
     *     <a:Addresses>
     *          <a:Address Type="NZStandard" Usage="Residential" DataQualityType="Valid" ValidFrom="13/11/2012">
     *              <a:Locality>
     *                  <a:NameElement a:NameType="NZTownCity">Wellington</a:NameElement>
     *                  <a:NameElement a:NameType="NZSuburb">Kelburn</a:NameElement>
     *              </a:Locality>
     *              <a:Thoroughfare>
     *                  <a:NameElement a:NameType="NZNumberStreet">1 Main St</a:NameElement>
     *              </a:Thoroughfare>
     *              <a:PostCode>
     *                  <a:Identifier Type="NZPostCode">1111</a:Identifier>
     *              </a:PostCode>
     *         </a:Address>
     *     </a:Addresses>
     * </p:Party>
     *
     * @param DOMDocument $identity
     */
    public function __construct(DOMDocument $identity)
    {
        parent::__construct();

        $xpath = new DOMXPath($identity);
        $xpath->registerNamespace('p', 'urn:oasis:names:tc:ciq:xpil:3');
        $xpath->registerNamespace('a', 'urn:oasis:names:tc:ciq:xal:3');

        // Record info
        $this->DataQuality = $this->getNamedItemNodeValue($xpath, '/p:Party/a:Addresses/a:Address', 'DataQualityType');
        $this->VerificationDate = $this->getNamedItemNodeValue($xpath, '/p:Party/a:Addresses/a:Address', 'ValidFrom');

        // Address info
        $this->AddressType = $this->getNamedItemNodeValue($xpath, '/p:Party/a:Addresses/a:Address', 'Type');
        $this->NZNumberStreet = $this->getNodeValue(
            $xpath,
            "/p:Party/a:Addresses/a:Address/a:Thoroughfare/a:NameElement[@a:NameType='NZNumberStreet']"
        );
        $this->NZRuralDelivery = $this->getNodeValue(
            $xpath,
            "/p:Party/a:Addresses/a:Address/a:RuralDelivery/a:Identifier[@Type='NZRuralDelivery']"
        );
        $this->NZSuburb = $this->getNodeValue(
            $xpath,
            "/p:Party/a:Addresses/a:Address/a:Locality/a:NameElement[@a:NameType='NZSuburb']"
        );
        $this->NZTownOrCity = $this->getNodeValue(
            $xpath,
            "/p:Party/a:Addresses/a:Address/a:Locality/a:NameElement[@a:NameType='NZTownCity']"
        );
        $this->NZPostCode = $this->getNodeValue(
            $xpath,
            "/p:Party/a:Addresses/a:Address/a:PostCode/a:Identifier[@Type='NZPostCode']"
        );
    }

    /**
     * @return bool
     */
    public function isRuralDeliveryAddress()
    {
        return $this->AddressType === self::TYPE_RURAL_DELIVERY;
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        return true;
    }

    /**
     * @param DOMXPath $xpath The DOMXPath object to carry out the query on
     * @param string $query The XPath query to find the relevant node
     * @param string $namedAttr The named attribute to retrieve from the XPath query
     * @return string|null Either the value from the named item, or null if no item exists
     */
    private function getNamedItemNodeValue(DOMXPath $xpath, $query, $namedAttr)
    {
        $query = $xpath->query($query);
        $value = null;

        if ($query->length > 0) {
            $item = $query->item(0);

            if ($item->hasAttributes()) {
                $value = $item->attributes->getNamedItem($namedAttr);

                if (strlen($value->nodeValue) > 0) {
                    $value = $value->nodeValue;
                }
            }
        }

        return $value;
    }

    /**
     * @param DOMXPath $xpath The DOMXPath object to carry out the query on
     * @param string $query The XPath query to find the relevant node
     * @return string|null Either the first matching node's value (there should only ever be one), or null if none found
     */
    private function getNodeValue(DOMXPath $xpath, $query)
    {
        $query = $xpath->query($query);
        return ($query->length > 0 ? $query->item(0)->nodeValue : null);
    }
}
