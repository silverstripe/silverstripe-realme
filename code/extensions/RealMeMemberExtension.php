<?php

/**
 * Class RealmeMemberExtension
 */
class RealMeMemberExtension extends DataExtension
{
    private static $db = array(
        "RealmeSPNameID" => "Varchar(35)",
    );

    private static $indexes = array(
        "RealmeSPNameID" => true
    );
}