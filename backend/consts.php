<?php
/**
 * Created by PhpStorm.
 * User: Sunny
 * Date: 2017-05-08
 * Time: 6:26 PM
 */

namespace Leilo;

class Constants
{
    const SUCCESS = 0;
    const ERR_DB = 1;
    const ERR_ENTITY_ALREADY_EXISTS = 2;
    const ERR_ENTITY_NONEXISTENT = 3;
    const ERR_NO_PERMS = 4;
    const ERR_INVALID_ARGS = 5;
    const ERR_INVALID_REQ = 6;
    const ERR_VERSION_MISMATCH = 7;
    const ERR_INVALID_LOGIN=8;
    const ERR_INVALID_CALL=9;

    const PERM_NONE = 0;
    const PERM_READ = 1;
    const PERM_WRITE = 1 << 1;
    const PERM_CONFIG = 1 << 2;
    const PERMS_ALL = Constants::PERM_READ | self::PERM_WRITE | Constants::PERM_CONFIG;

    const ENTITY_ATOM = "ATOM";
    const ENTITY_GROUP = "GROUP";
    const ENTITY_USER = "USER";
    const ENTITY_NONEXISTENT = "NONEXISTENT";

    const PROTOCOL_VER="1.0.0";

    const PASS_HASH=PASSWORD_DEFAULT;
}