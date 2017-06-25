<?php
/**
 * Created by PhpStorm.
 * User: Sunny
 * Date: 2017-04-26
 * Time: 6:24 PM
 * Random utilities
 **/

namespace Leilo;

require_once "consts.php";

class Util
{
    public static function db_connect()
    {
        // Load configuration as an array. Use the actual location of your configuration file
        $config = parse_ini_file(API_PATH . '/backend/config.ini');
        $connection = new \mysqli('localhost', $config['username'], $config['password'], $config['dbname']);
        // If connection was not successful, handle  the error
        if (isset($connection->connect_error)) {
            // Handle error - notify administrator, log to a file, show an error screen, etc
//            error_log("Failed to connect to database", 0);
            throw new \Exception("");
        }

        return $connection;
    }

    public static function getUUID(\mysqli $db)
    {
        $result = $db->query("SELECT UUID()");
        if (!$result)
            throw new \Exception("Database query failed", Constants::ERR_DB);
        return $result->fetch_assoc()["UUID()"];
    }

    public static function printPerm($perms)
    {
        echo "Perms: [";
        if (LeiloDB::checkPermission($perms, Constants::PERM_READ))
            echo "R";

        if (LeiloDB::checkPermission($perms, Constants::PERM_WRITE))
            echo "W";

        if (LeiloDB::checkPermission($perms, Constants::PERM_CONFIG))
            echo "C";

        echo "]<br>";
    }

    public static function toArray(\mysqli_result $result, $fetch = null)
    {
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            if (isset($fetch) && isset($row[$fetch]))
                $rows[] = $row[$fetch];
            else
                $rows[] = $row;

        }
        return $rows;
    }
}