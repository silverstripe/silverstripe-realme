<?php

/**
 * Class RealmeMemberExtension
 */
class RealMeMemberExtension extends DataExtension
{
    private static $db = array(
        "RealmeSPNameID" => "Varchar(50)",
    );

    private static $indexes = array(
        "RealmeSPNameID" => true
    );
}