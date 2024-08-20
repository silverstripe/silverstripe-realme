<?php

namespace SilverStripe\RealMe\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\Security\Member;

/**
 * @extends Extension<Member>
 */
class MemberExtension extends Extension
{
    private static $db = array(
        "RealmeSPNameID" => "Varchar(35)",
    );

    private static $indexes = array(
        "RealmeSPNameID" => true
    );
}
