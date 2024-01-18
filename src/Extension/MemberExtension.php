<?php

namespace SilverStripe\RealMe\Extension;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Member;

/**
 * @extends DataExtension<Member>
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
