<?php

namespace SilverStripe\RealMe;

use Exception as BaseException;

class Exception extends BaseException
{
    const INVALID_IDENTITY_VALUE = 0;
    const FAILED_PARSING_IDENTITY = 1;
    const MISSING_NAMEID = 2;
    const MISSING_SESSION_EXPIRATION = 3;
    const MISSING_SESSION_INDEX = 4;
    const MISSING_ATTRIBUTES = 5;
    const MISSING_AUTHN_RESPONSE = 6;
    const NOT_AUTHENTICATED = 7;
    const MISSING_MEMBER_EXTENSION = 8;
    const PERSISTING_TRANSIENT_ID = 9;
}
