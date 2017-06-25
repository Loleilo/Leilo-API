<?php
/**
 * Created by PhpStorm.
 * User: Sunny
 * Date: 2017-04-27
 * Time: 1:49 PM
 */

//todo code coverage is not complete

namespace Leilo;

use OvenHacks\D;

require_once '../backend/util.php';
require_once '../backend/leilodb.php';
require_once '../backend/session.php';
require_once '../backend/consts.php';

require_once 'print.php';

D::ppb("session");
try {
    $db = new LeiloDB(Util::db_connect());

    $user = $db->createUser("test_user", "lol");
    $group1 = $db->createGroup("test_group");
    $atom = $db->createAtom("lol");
    $db->setGroupPermission($user, $group1, Constants::PERMS_ALL, true);
    $db->setAtomPermission($group1, $atom, Constants::PERMS_ALL, true);
    D::pi("Testing rig created");

    $mgr = new LeiloSessionManager($db);

    D::pi("Session manager started.");

    D::pb("<b>Basic login stuff</b>");
    $mgr->tryActivateSession($user);
    D::p("Is logged on: " . $mgr->isLoggedIn());

    $mgr->killSession();
    D::pi("Killed session");
    D::p("Is logged on: " . $mgr->isLoggedIn());

    $mgr->tryActivateSession($user);
    D::pi("Relogged in");
    D::p("Is logged on: " . $mgr->isLoggedIn());

    D::pb("Testing basic read/write functions");
    D::p("getUserName: " . $mgr->getUserName());
    $res = $mgr->listGroups();
    D::p("listGroups (with groupname): ");
    foreach ($res as $row) {
        echo " " . $row . "=>" . $mgr->getGroupName($row) . " ";
    }
    D::p("");

    echo "getGroupPermissions: ";
    Util::printPerm($mgr->getGroupPermissions($group1));

    $res = $mgr->listAtoms($group1);
    D::p("listAtoms (wtih get val and get permissions): ");
    foreach ($res as $row) {
        echo " " . $row . "=>" . $mgr->readAtom($group1, $row) . ", perms=>" . $mgr->getAtomPermissions($group1, $atom);
    }
    D::p("");

    echo "Test writing to atom - ";
    $mgr->writeAtom($group1, $atom, "abc123");
    D::p("value is now " . $mgr->readAtom($group1, $atom));

    D::pb("Testing basic config functions");

    echo "Test changing username - ";
    $mgr->setUsername("test_abc");
    D::p("value is now " . $mgr->getUserName());

    D::p("Test creating group");
    $group_tmp = $mgr->createGroup("xd");
    $res = $mgr->listGroups();
    D::p("listGroups (with groupname): ");
    foreach ($res as $row){
        echo " " . $row . "=>" . $mgr->getGroupName($row) . " ";
    }
    D::p("");

    D::p("Test deleting group");
    $mgr->deleteGroup($group_tmp);
    $res = $mgr->listGroups();
    D::p("listGroups (with groupname): ");
    foreach ($res as $row){
        echo " " . $row . "=>" . $mgr->getGroupName($row) . " ";
    }
    D::p("");

    D::p("Test setting group permissions");
    $mgr->setGroupPermission($group1, $user, Constants::PERM_READ, false);
    echo "New perms: ";
    Util::printPerm($mgr->getGroupPermissions($group1));

    D::p("Test setting atom permissions");
    $mgr->setAtomPermission($group1, $atom, $group1, Constants::PERM_READ, false);
    echo "New perms: ";
    Util::printPerm($mgr->getAtomPermissions($group1, $atom));
} catch (\Exception $e) {
    D::pe("$e occured");
} finally {
    if (isset($mgr)) {
        D::pi("Deleting user");
        $mgr->deleteUser();
    }
    if (isset($db)) {
        if (isset($atom))
            $db->deleteEntity($atom);
        if (isset($group1))
            $db->deleteEntity($group1);
    }
    D::pi("Testing rig destroyed");
}

D::ppe();
?>