<?php
/**
 * Created by PhpStorm.
 * User: Sunny
 * Date: 2017-04-30
 * Time: 9:53 AM
 */

namespace OvenHacks;

class D
{
    public static function p($s)
    {
        echo "$s<br>";
    }

    public static function pe($s)
    {
        echo "<span style=\"color:red\">$s</span><br>";
    }

    public static function ph1($s)
    {
        echo "<h1>$s</h1><br>";
    }

    public static function pb($s)
    {
        echo "<b>$s</b><br>";
    }

    public static function pi($s)
    {
        echo "<i>$s</i><br>";
    }

    public static function ppb($header)
    {
        echo "<html><body><h1><i>$header</i> unit test</h1>";
    }

    public static function ppe()
    {
        echo "</body></html>";
    }
}