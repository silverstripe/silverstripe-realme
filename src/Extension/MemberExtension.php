<?php

namespace SilverStripe\RealMe\Extension;

use SilverStripe\ORM\DataExtension;

/**
 * Class MemberExtension
 */
class MemberExtension extends DataExtension
{
    private static $db = array(
        "RealmeSPNameID" => "Varchar(35)",
    );

    private static $indexes = array(
        "RealmeSPNameID" => true
    );
}
