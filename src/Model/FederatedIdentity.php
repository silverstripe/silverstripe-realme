<?php

namespace SilverStripe\RealMe\Model;

use DOMDocument;
use DOMXPath;

use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\ViewableData;
use SilverStripe\ORM\FieldType\DBDatetime;

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
class FederatedIdentity extends ViewableData
{
    const SOURCE_XML = 'urn:nzl:govt:ict:stds:authn:safeb64:attribute:igovt:IVS:Assertion:Identity';
    const SOURCE_JSON = 'urn:nzl:govt:ict:stds:authn:safeb64:attribute:igovt:IVS:Assertion:JSON:Identity';

    /**
     * @var string The FIT (Federated Identity Tag) for this identity. This is the unique string that identifies an
     * individual, and should generally be mapped one-to-one with a {@link Member} object
     */
    private $nameId;

    /**
     * @var string The given first name(s) of the federated identity returned by RealMe.
     */
    public $FirstName;

    /**
     * @var string The given middle name(s) of the federated identity returned by RealMe.
     */
    public $MiddleName;

    /**
     * @var string The given last name of the federated identity returned by RealMe.
     */
    public $LastName;

    /**
     * @var string The gender of the federated identity returned by RealMe. Will be one of 'M', 'F', possibly 'U' or 'O'
     * (messaging specs are unclear).
     */
    public $Gender;

    /**
     * @var DOMNodeList Undocumented in RealMe messaging spec, generally describes the quality of birth info based
     * presumably on the source.
     */
    public $BirthInfoQuality;

    /**
     * @var string The birth year of the federated identity returned by RealMe, e.g. 1993, 1954, 2015.
     * Probably better to use {@link getDateOfBirth()} which will return an {@link SS_Datetime} object.
     */
    public $BirthYear;

    /**
     * @var string The birth month of the federated identity returned by RealMe, e.g. 05 (May).
     * Probably better to use {@link getDateOfBirth()} which will return an {@link SS_Datetime} object.
     */
    public $BirthMonth;

    /**
     * @var string The birth day of the federated identity returned by RealMe, e.g. 05 (5th day of the month).
     * Probably better to use {@link getDateOfBirth()} which will return an {@link SS_Datetime} object.
     */
    public $BirthDay;

    /**
     * @var string Undocumented in RealMe messaging spec, generally describes the quality of birthplace info based
     * presumably on the source.
     */
    public $BirthPlaceQuality;

    /**
     * @var string The country of birth for the given federated identity returned by RealMe.
     */
    public $BirthPlaceCountry;

    /**
     * @var string The birthplace 'locality' of the federated identity returned by RealMe, e.g. 'Wellington', 'Unknown'
     */
    public $BirthPlaceLocality;

    public function __construct($nameId)
    {
        parent::__construct();
        $this->nameId = $nameId;
    }

    /**
     * Constructor that sets the expected federated identity details based on a provided DOMDocument. The expected XML
     * structure for the DOMDocument is the following:
     *
     * <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
     * <ns1:Party
     *  xmlns:ns1="urn:oasis:names:tc:ciq:xpil:3"
     *  xmlns:ns2="urn:oasis:names:tc:ciq:ct:3"
     *  xmlns:ns3="urn:oasis:names:tc:ciq:xnl:3"
     *  xmlns:ns4="http://www.w3.org/1999/xlink"
     *  xmlns:ns5="urn:oasis:names:tc:ciq:xal:3">
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
    public static function createFromXML(DOMDocument $identityDocument, $nameId)
    {
        $identity = new self($nameId);

        $xpath = new DOMXPath($identityDocument);
        $xpath->registerNamespace('p', 'urn:oasis:names:tc:ciq:xpil:3');
        $xpath->registerNamespace('dataQuality', 'urn:oasis:names:tc:ciq:ct:3');
        $xpath->registerNamespace('n', 'urn:oasis:names:tc:ciq:xnl:3');
        $xpath->registerNamespace('xlink', 'http://www.w3.org/1999/xlink');
        $xpath->registerNamespace('addr', 'urn:oasis:names:tc:ciq:xal:3');

        // Name elements
        $identity->FirstName = self::getNodeValue(
            $xpath,
            "/p:Party/p:PartyName/n:PersonName/n:NameElement[@n:ElementType='FirstName']"
        );
        $identity->MiddleName = self::getNodeValue(
            $xpath,
            "/p:Party/p:PartyName/n:PersonName/n:NameElement[@n:ElementType='MiddleName']"
        );
        $identity->LastName = self::getNodeValue(
            $xpath,
            "/p:Party/p:PartyName/n:PersonName/n:NameElement[@n:ElementType='LastName']"
        );

        // Gender
        $identity->Gender = self::getNamedItemNodeValue($xpath, '/p:Party/p:PersonInfo[@p:Gender]', 'Gender');

        // Birth info
        $identity->BirthInfoQuality = $xpath->query("/p:Party/p:BirthInfo[@dataQuality:DataQualityType]");

        // Birth date
        $identity->BirthYear = self::getNodeValue(
            $xpath,
            "/p:Party/p:BirthInfo/p:BirthInfoElement[@p:Type='BirthYear']"
        );
        $identity->BirthMonth = self::getNodeValue(
            $xpath,
            "/p:Party/p:BirthInfo/p:BirthInfoElement[@p:Type='BirthMonth']"
        );
        $identity->BirthDay = self::getNodeValue(
            $xpath,
            "/p:Party/p:BirthInfo/p:BirthInfoElement[@p:Type='BirthDay']"
        );

        // Birth place
        $identity->BirthPlaceQuality = self::getNamedItemNodeValue(
            $xpath,
            '/p:Party/p:BirthInfo/p:BirthPlaceDetails[@dataQuality:DataQualityType]',
            'DataQualityType'
        );
        $identity->BirthPlaceCountry = self::getNodeValue(
            $xpath,
            "/p:Party/p:BirthInfo/p:BirthPlaceDetails/addr:Country/addr:NameElement[@addr:NameType='Name']"
        );
        $identity->BirthPlaceLocality = self::getNodeValue(
            $xpath,
            "/p:Party/p:BirthInfo/p:BirthPlaceDetails/addr:Locality/addr:NameElement[@addr:NameType='Name']"
        );

        return $identity;
    }

    public function isValid()
    {
        return true;
    }

    public function getDateOfBirth()
    {
        if ($this->BirthYear && $this->BirthMonth && $this->BirthDay) {
            $value = sprintf('%d-%d-%d', $this->BirthYear, $this->BirthMonth, $this->BirthDay);
            $dateTime = DBDatetime::create()->setValue($value);
            return $dateTime;
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
    private static function getNamedItemNodeValue(DOMXPath $xpath, $query, $namedAttr)
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
    private static function getNodeValue(DOMXPath $xpath, $query)
    {
        $query = $xpath->query($query);
        return ($query->length > 0 ? $query->item(0)->nodeValue : null);
    }

    /**
     * create a FederatedIdentity from a JSON string.
     *
     * @param string $identityHashMap
     * @param string $nameId
     * @return void
     */
    public static function createFromJSON($identityHashMap, $nameId)
    {
        $identity = new self($nameId);

        $identityMap = json_decode($identityHashMap, true);

        // Name elements
        $identity->FirstName = $identityMap['name']['firstName'];
        $identity->MiddleName = $identityMap['name']['middleName'];
        $identity->LastName = $identityMap['name']['lastName'];

        // Gender
        $identity->Gender = $identityMap['gender']['genderValue'];

        // Birth info
        $identity->BirthInfoQuality = $identityMap['dateOfBirth']['dateOfBirthDisputed'] ?: 'Valid';

        // Birth date
        $identity->BirthYear = explode('-', $identityMap['dateOfBirth']['dateOfBirthValue'])[0];
        $identity->BirthMonth = explode('-', $identityMap['dateOfBirth']['dateOfBirthValue'])[1];
        $identity->BirthDay = explode('-', $identityMap['dateOfBirth']['dateOfBirthValue'])[2];

        // Birth place
        $identity->BirthPlaceQuality = $identityMap['placeOfBirth']['placeOfBirthDisputed']?: 'Valid';
        $identity->BirthPlaceCountry = $identityMap['placeOfBirth']['country'];
        $identity->BirthPlaceLocality = $identityMap['placeOfBirth']['locality'];

        return $identity;
    }
}
