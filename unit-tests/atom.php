<?php
/**
 * Created by PhpStorm.
 * User: Sunny
 * Date: 2017-04-27
 * Time: 1:49 PM
 */

namespace Leilo;

require_once '../backend/util.php';
require_once '../backend/leilodb.php';
echo
"<html>
<body>
<h1><i>atom</i> unit test</h1>";
try {
    $db = new LeiloDB(Util::db_connect());

    echo "<b>Test basic create:</b> <br>";
    $created_id_1 = $db->createAtom("derp");
    echo "UUID of created atom: " . $created_id_1;
    echo "<br>";
    $created_id_2 = $db->createAtom("lol");
    echo "UUID of created atom: " . $created_id_2;
    echo "<br>";

    echo "<b>Test read op:</b> <br>";
    echo "Read " . $created_id_1 . ": " . $db->readAtom($created_id_1);
    echo "<br>";
    echo "Read " . $created_id_2 . ": " . $db->readAtom($created_id_2);
    echo "<br>";

    echo "<b>Test write op:</b> <br>";
    echo "Writing to $created_id_1 <br>";
    $db->writeAtom($created_id_1, "123456");
    echo "Read " . $created_id_1 . ": " . $db->readAtom($created_id_1);
    echo "<br>";
    echo "Read " . $created_id_2 . ": " . $db->readAtom($created_id_2);
    echo "<br>";

    echo "<b>Test delete ops:</b> <br>";
    $db->deleteEntity($created_id_1);
    $db->deleteEntity($created_id_2);
    echo "Try to read " . $created_id_1 . ": " . $db->readAtom($created_id_1);
    echo "<br>";
    echo "Try to read " . $created_id_2 . ": " . $db->readAtom($created_id_2);
    echo "<br>";



} catch (\Exception $e) {
    echo "$e occured";
}

echo
"</body>
</html>";
?>