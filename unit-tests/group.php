<?php
/**
 * Created by PhpStorm.
 * User: Sunny
 * Date: 2017-04-26
 * Time: 8:57 PM
 */

namespace Leilo;

require_once '../backend/util.php';
require_once '../backend/leilodb.php';
require_once '../backend/consts.php';
echo
"<html>
<body>
<h1><i>group</i> unit test</h1>";
try {
    $db = new LeiloDB(Util::db_connect());

    $group_1 = $db->createGroup();
    $group_2 = $db->createGroup();

    $atom_1 = $db->createAtom("lol");
    $atom_2 = $db->createAtom("lol1");
    $atom_3 = $db->createAtom("lol2");

    //set db
    $db->setAtomPermission($group_1, $atom_1, Constants::PERM_CONFIG, true);
    $db->setAtomPermission($group_2, $atom_2, Constants::PERM_CONFIG, true);
    $db->setAtomPermission($group_2, $atom_3, Constants::PERM_CONFIG, true);


    echo "<b>Test basic read:</b> <br>";
    $perms = $db->getAtomPermissions($group_1, $atom_1);

    echo "Type of perms: ";
    echo gettype($perms);
    echo "<br>";


    echo "Value of perms = " .
        base_convert($perms, 16, 2);
    echo "<br>";

    Util::printPerm($perms);

    $db->setAtomPermission($group_1, $atom_1, Constants::PERM_READ, true);

    echo "<b>Testing add perms:</b> <br>";
    $perms = $db->getAtomPermissions($group_1, $atom_1);
    Util::printPerm($perms);

    $db->setAtomPermission($group_1, $atom_1, Constants::PERM_WRITE, true);

    $perms = $db->getAtomPermissions($group_1, $atom_1);
    Util::printPerm($perms);

    $res = $db->getAtoms($group_2);
    echo "List atoms of group '$group_2': <br>";
    foreach ($res as $row) {
        echo $row . " ";
    }
    echo '<br>';

    echo "<b>Testing remove perms:</b> <br>";
    $db->setAtomPermission($group_1, $atom_1, Constants::PERM_READ, false);

    $perms = $db->getAtomPermissions($group_1, $atom_1);
    Util::printPerm($perms);

    $db->setAtomPermission($group_1, $atom_1, Constants::PERM_WRITE, false);

    //delete stuff
    $db->deleteEntity($atom_1);
    $db->deleteEntity($atom_2);
    $db->deleteEntity($atom_3);
    $db->deleteEntity($group_1);
    $db->deleteEntity($group_2);

    //check
    $perms = $db->getAtomPermissions($group_1, $atom_1);
    Util::printPerm($perms);

    $res = $db->getAtoms($group_2);
    echo "List atoms of group '$group_2': ";
    foreach ($res as $row) {
        echo $row . " ";
    }
    echo '<br>';


} catch (\Exception $e) {
    echo "$e occured";
}

echo
"</body>
</html>";
?>
