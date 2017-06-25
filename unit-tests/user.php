<?php
/**
 * Created by PhpStorm.
 * User: Sunny
 * Date: 2017-04-26
 * Time: 8:57 PM
 */

namespace Leilo;

use OvenHacks\D;

require_once '../backend/util.php';
require_once '../backend/leilodb.php';
require_once 'print.php';
require_once '../backend/consts.php';

D::ppb("user");
try {
    $db = new LeiloDB(Util::db_connect());

    $user_1 = $db->createUser("stuart", "derp");
    $user_2 = $db->createUser("gunderson", "pony");

//    echo $user_1;

//    echo $db->getEntityType($user_1);

    $group_1 = $db->createGroup();
    $group_2 = $db->createGroup();
    $group_3 = $db->createGroup();

    //set db
    $db->setGroupPermission($user_1, $group_1, Constants::PERM_CONFIG, true);
    $db->setGroupPermission($user_2, $group_2, Constants::PERM_CONFIG, true);
    $db->setGroupPermission($user_2, $group_3, Constants::PERM_CONFIG, true);


    D::pb("Test basic read");
    $perms = $db->getGroupPermissions($user_1, $group_1);

    echo "Type of perms: ";
    echo gettype($perms);
    echo "<br>";


    echo "Value of perms = " .
        base_convert($perms, 16, 2);
    echo "<br>";

    Util::printPerm($perms);

    $db->setGroupPermission($user_1, $group_1, Constants::PERM_READ, true);

    echo "<b>Testing add perms:</b> <br>";
    $perms = $db->getGroupPermissions($user_1, $group_1);
    Util::printPerm($perms);

    $db->setGroupPermission($user_1, $group_1, Constants::PERM_WRITE, true);

    $perms = $db->getGroupPermissions($user_1, $group_1);
    Util::printPerm($perms);

    $res = $db->getGroups($user_2);
    echo "List groups of user '$user_2': <br>";
    foreach ($res as $row) {
        echo $row . " ";
    }
    echo '<br>';

    echo "<b>Testing remove perms:</b> <br>";
    $db->setGroupPermission($user_1, $group_1, Constants::PERM_READ, false);

    $perms = $db->getGroupPermissions($user_1, $group_1);
    Util::printPerm($perms);

    $db->setGroupPermission($user_1, $group_1, Constants::PERM_WRITE, false);

    //delete stuff
    $db->deleteEntity($group_1);
    $db->deleteEntity($group_2);
    $db->deleteEntity($group_3);
    $db->deleteEntity($user_1);
    $db->deleteEntity($user_2);

    //check
    $perms = $db->getGroupPermissions($user_1, $group_1);
    Util::printPerm($perms);

    $res = $db->getGroups($user_2);
    echo "List groups of user '$user_2': ";
    foreach ($res as $row) {
        echo $row . " ";
    }
    echo '<br>';


} catch (\Exception $e) {
    echo "$e occured";
}

D::ppe();
?>
