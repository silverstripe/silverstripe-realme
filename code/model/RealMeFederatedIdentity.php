<?php

/**
 * Class RealMeFederatedIdentity
 *
 * Contains data to describe an identity, verified by RealMe. Provides simpler access to identity information, rather
 * than having to parse XML via {@link DOMDocument} or similar.
 *
 * All public methods return individual elements from the federated identity.
 *
 * Standard usage:
 * Injector::inst()->get('RealMeService')->enforceLogin(); // Enforce login and ensure auth data exists
 * $identity = Injector::inst()->get('RealMeService')->getAuthData()->getIdentity();
 *
 * Notes:
 * - We can't store the original DOMDocument as it's not possible to properly serialize and unserialize this such that
 *   it can be stored in session. Therefore, during object instantiation, we parse the XML, and store individual details
 *   directly against properties.
 *
 * - See this object's constructor for the XML / DOMDocument object expected to be passed during instantiation.
 */
class RealMeFederatedIdentity extends ViewableData
{

    /**
     * @var string The FIT (Federated Identity Tag) for this identity. This is the unique string that identifies an
     * individual, and should generally be mapped one-to-one with a {@link Member} object
     */
    private $nameId;

    /**
     * Constructor that sets the expected federated identity details based on a provided DOMDocument. The expected XML
     * structure for the DOMDocument is the following:
     *
     * <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
     * <ns1:Party xmlns:ns1="urn:oasis:names:tc:ciq:xpil:3" xmlns:ns2="urn:oasis:names:tc:ciq:ct:3" xmlns:ns3="urn:oasis:names:tc:ciq:xnl:3" xmlns:ns4="http://www.w3.org/1999/xlink" xmlns:ns5="urn:oasis:names:tc:ciq:xal:3">
     *     <ns1:PartyName>
     *         <ns3:PersonName>
     *             <ns3:NameElement ns3:ElementType="FirstName">Edmund</ns3:NameElement>
     *             <ns3:NameElement ns3:ElementType="MiddleName">Percival</ns3:NameElement>
     *             <ns3:NameElement ns3:ElementType="LastName">Hillary</ns3:NameElement>
     *         </ns3:PersonName>
     *     </ns1:PartyName>
     *     <ns1:PersonInfo ns1:Gender="M"/>
     *     <ns1:BirthInfo ns2:DataQualityType="Valid">
     *         <ns1:BirthInfoElement ns1:Type="BirthYear">1919</ns1:BirthInfoElement>
     *         <ns1:BirthInfoElement ns1:Type="BirthMonth">07</ns1:BirthInfoElement>
     *         <ns1:BirthInfoElement ns1:Type="BirthDay">20</ns1:BirthInfoElement>
     *         <ns1:BirthPlaceDetails ns2:DataQualityType="Valid">
     *             <ns5:Country>
     *                 <ns5:NameElement ns5:NameType="Name">New Zealand</ns5:NameElement>
     *             </ns5:Country>
     *             <ns5:Locality>
     *                 <ns5:NameElement ns5:NameType="Name">Auckland</ns5:NameElement>
     *             </ns5:Locality>
     *         </ns1:BirthPlaceDetails>
     *     </ns1:BirthInfo>
     * </ns1:Party>
     *
     * @param DOMDocument $identity
     * @param string $nameId
     */
    public function __construct(DOMDocument $identity, $nameId)
    {
        parent::__construct();
        $this->nameId = $nameId;

        $xpath = new DOMXPath($identity);
        $xpath->registerNamespace('p', 'urn:oasis:names:tc:ciq:xpil:3');
        $xpath->registerNamespace('dataQuality', 'urn:oasis:names:tc:ciq:ct:3');
        $xpath->registerNamespace('n', 'urn:oasis:names:tc:ciq:xnl:3');
        $xpath->registerNamespace('xlink', 'http://www.w3.org/1999/xlink');
        $xpath->registerNamespace('addr', 'urn:oasis:names:tc:ciq:xal:3');

        // Name elements
        $this->FirstName = $this->getNodeValue($xpath, "/p:Party/p:PartyName/n:PersonName/n:NameElement[@n:ElementType='FirstName']");
        $this->MiddleName = $this->getNodeValue($xpath, "/p:Party/p:PartyName/n:PersonName/n:NameElement[@n:ElementType='MiddleName']");
        $this->LastName = $this->getNodeValue($xpath, "/p:Party/p:PartyName/n:PersonName/n:NameElement[@n:ElementType='LastName']");

        // Gender
        $this->Gender = $this->getNamedItemNodeValue($xpath, '/p:Party/p:PersonInfo[@p:Gender]', 'Gender');

        // Birth info
        $this->BirthInfoQuality = $xpath->query("/p:Party/p:BirthInfo[@dataQuality:DataQualityType]");

        // Birth date
        $this->BirthYear = $this->getNodeValue($xpath, "/p:Party/p:BirthInfo/p:BirthInfoElement[@p:Type='BirthYear']");
        $this->BirthMonth = $this->getNodeValue($xpath, "/p:Party/p:BirthInfo/p:BirthInfoElement[@p:Type='BirthMonth']");
        $this->BirthDay = $this->getNodeValue($xpath, "/p:Party/p:BirthInfo/p:BirthInfoElement[@p:Type='BirthDay']");

        // Birth place
        $this->BirthPlaceQuality = $this->getNamedItemNodeValue($xpath, '/p:Party/p:BirthInfo/p:BirthPlaceDetails[@dataQuality:DataQualityType]', 'DataQualityType');
        $this->BirthPlaceCountry = $this->getNodeValue($xpath, "/p:Party/p:BirthInfo/p:BirthPlaceDetails/addr:Country/addr:NameElement[@addr:NameType='Name']");
        $this->BirthPlaceLocality = $this->getNodeValue($xpath, "/p:Party/p:BirthInfo/p:BirthPlaceDetails/addr:Locality/addr:NameElement[@addr:NameType='Name']");
    }

    public function isValid()
    {
        return true;
    }

    public function getDateOfBirth()
    {
        if($this->BirthYear && $this->BirthMonth && $this->BirthDay) {
            $value = sprintf('%d-%d-%d', $this->BirthYear, $this->BirthMonth, $this->BirthDay);
            return DBField::create_field('SS_DateTime', $value);
        } else {
            return null;
        }
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

        if($query->length > 0) {
            $item = $query->item(0);

            if($item->hasAttributes()) {
                $value = $item->attributes->getNamedItem($namedAttr);

                if(strlen($value->nodeValue) > 0) {
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
