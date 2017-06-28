<?php
/**
 * Created by PhpStorm.
 * User: Sunny
 * Date: 2017-04-26
 * Time: 6:48 PM
 * Provides bottom level operations to database. All database calls should go through this class. Provides null checks, error checks, type checks, and SQL sanitization.
 * NOTES: CODE DOES NOT SANITIZE ANYTHING AS OF NOW
 */

namespace Leilo;

require_once "paths.php";
require_once "util.php";
require_once "consts.php";

class LeiloDB
{
    //error codes

    function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    //special operations that aren't enabled by any entity permissions

    public function createAtom($init_val, $name = null)
    {
        if ($init_val == null)
            $init_val = "null";
        $UUID = Util::getUUID($this->db);
        if ($name == null)
            $name = $UUID;
        $this->queryW("INSERT INTO atoms (id, value, name) VALUES ('$UUID', '$init_val', '$name')");


        $this->addEntityEntry($UUID, Constants::ENTITY_ATOM);
        return $UUID;
    }

    public function createGroup($name = null)
    {
        $UUID = Util::getUUID($this->db);

        if ($name == null)
            $name = $UUID;

        $this->queryW("INSERT INTO groups (id, name) VALUES ('$UUID','$name')");

        $this->addEntityEntry($UUID, Constants::ENTITY_GROUP);
        return $UUID;
    }

    public function createUser($username, $password_hash)
    {
        if (!$this->isUsernameTaken($username)) {
            $UUID = Util::getUUID($this->db);
            $this->addEntityEntry($UUID, Constants::ENTITY_USER);
            $this->queryW("INSERT INTO users (id, username, password_hash) VALUES ('$UUID','$username','$password_hash')");

            return $UUID;
        }
        throw new \Exception("User already exists", Constants::ERR_ENTITY_ALREADY_EXISTS);
    }

    public function checkHashMatch($user_id, $password)
    {
        $res = $this->queryW("SELECT password_hash FROM users WHERE id='$user_id'");
        $res = $res->fetch_assoc()["password_hash"];
        return password_verify($password, $res);
    }

    public function getWidget($widget_id)
    {
        return $this->queryEntity("SELECT config FROM widgets WHERE widget_id='$widget_id'", "config");
    }

    public function writeWidget($widget_id, $config)
    {
        $this->queryW("UPDATE atoms SET value='$config' WHERE widget_id='$widget_id'");
    }

    public function createWidget($config)
    {
        $UUID = Util::getUUID($this->db);
        $this->queryW("INSERT INTO widgets (widget_id, config) VALUES ('$UUID','$config')");
        return $UUID;
    }

    public function deleteWidget($widget_id)
    {
        $this->queryW("DELETE FROM widgets WHERE widget_id='$widget_id'");
        $this->queryW("DELETE FROM user_widgets WHERE widget_id='$widget_id'");
    }

    public function listWidgets($user_id)
    {
        return Util::toArray($this->queryW("SELECT widget_id FROM user_widgets WHERE user_id='$user_id'"), "widget_id");
    }

    public function setWidgetOwner($user_id, $widget_id)
    {
        $this->queryW("INSERT IGNORE INTO user_widgets (user_id, widget_id) values ('$user_id', '$widget_id')");
    }

    public function getWidgetOwner($widget_id)
    {
        return $this->queryEntity("SELECT user_id FROM user_widgets WHERE widget_id='$widget_id'", "widget")["user_id"];
    }

    //permissions

    //permissions bitbanging functions

    public static function checkPermission($orig, $permission)
    {
        if (($orig & $permission) == Constants::PERM_NONE)
            return false;

        return true;
    }

    public static function setPermission($orig, $permission, $value)
    {
        if ($value)
            return $orig | $permission;
        return $orig & (~$permission);
    }

    //operations related to the READ permission
    protected function doesAtomExist($atom_id)
    {
        return $this->queryEntity("SELECT value FROM atoms WHERE id='$atom_id'", "atom", false, true, null);
    }


    public function readAtom($atom_id)
    {
        return $this->queryEntity("SELECT value FROM atoms WHERE id='$atom_id'", "atom")['value'];
    }

    public function getAtomPermissions($group_id, $atom_id)
    {
        return $this->queryEntity("SELECT permissions FROM group_permissions WHERE group_id='$group_id' AND atom_id='$atom_id'", "atom", 0)['permissions'];
    }

    public function getAtoms($group_id)
    {
        return Util::toArray($this->queryW("SELECT atom_id FROM group_permissions WHERE group_id='$group_id'"), "atom_id");
    }

    public function lookupGroups($atom_id)
    {
        return Util::toArray($this->queryW("SELECT group_id FROM group_permissions WHERE atom_id='$atom_id'"), "group_id");
    }

    public function getGroupPermissions($user_id, $group_id)
    {
        return $this->queryEntity("SELECT permissions FROM user_permissions WHERE user_id='$user_id' AND group_id='$group_id'", "group", 0)['permissions'];
    }

    public function getGroups($user_id)
    {
        return Util::toArray($this->queryW("SELECT group_id FROM user_permissions WHERE user_id='$user_id'"), "group_id");
    }

    public function lookupUsers($group_id)
    {
        return Util::toArray($this->queryW("SELECT user_id FROM user_permissions WHERE group_id='$group_id'"), "user_id");
    }

    public function getUserUUID($username)
    {
        return $this->queryEntity("SELECT id FROM users WHERE username='$username'", "user")["id"];
    }

    protected function isUsernameTaken($username)
    {
        return $this->queryEntity("SELECT id FROM users WHERE username='$username'", "user", false, true);
    }

    public function writeAtom($atom_id, $value)
    {
        if (!$this->doesAtomExist($atom_id))
            throw new \Exception("Atom doesn't exist", Constants::ERR_ENTITY_NONEXISTENT);

        $this->queryW("UPDATE atoms SET value='$value' WHERE id='$atom_id'");
    }

    public function getUserName($user_id)
    {
        return $this->queryEntity("SELECT username FROM users WHERE id='$user_id'", "user")["username"];
    }

    public function getGroupName($group_id)
    {
        return $this->queryEntity("SELECT name FROM groups WHERE id='$group_id'", "group")["name"];
    }

    public function getAtomName($atom_id)
    {
        return $this->queryEntity("SELECT name FROM atoms WHERE id='$atom_id'", "atom")["name"];
    }

    //operations related to CONFIG

    //set $permissions to 0 to delete
    public function setAtomPermissions($group_id, $atom_id, $permissions)
    {
        if ($this->getEntityType($group_id) != Constants::ENTITY_GROUP || $this->getEntityType($atom_id) != Constants::ENTITY_ATOM)
            throw new \Exception("Atom or Group doesn't exist", Constants::ERR_ENTITY_NONEXISTENT);

        if ($permissions == Constants::PERM_NONE) {
//            echo "DELETE FROM group_permissions WHERE group_id='$group_id' AND atom_id='$atom_id'";
            $this->queryW("DELETE FROM group_permissions WHERE group_id='$group_id' AND atom_id='$atom_id'");
        } else

            $this->queryW("INSERT INTO group_permissions (group_id, atom_id, permissions) VALUES ('$group_id', '$atom_id', '$permissions') ON DUPLICATE KEY UPDATE group_id='$group_id', atom_id='$atom_id', permissions='$permissions'");
    }

    public function setAtomName($atom_id, $name)
    {
        if (!$this->doesAtomExist($atom_id))
            throw new \Exception("Atom doesn't exist", Constants::ERR_ENTITY_NONEXISTENT);
        $this->queryW("UPDATE atoms SET name='$name' WHERE id='$atom_id'");
    }

    public function setGroupName($group_id, $name)
    {
        $this->queryW("UPDATE groups SET name='$name' WHERE id='$group_id'");
    }

    public function setAtomPermission($group_id, $atom_id, $permission, $value)
    {
        $this->setAtomPermissions($group_id, $atom_id, $this->setPermission($this->getAtomPermissions($group_id, $atom_id), $permission, $value));
    }

    //set $permissions to 0 to delete
    public function setGroupPermissions($user_id, $group_id, $permissions)
    {
        if ($this->getEntityType($group_id) != Constants::ENTITY_GROUP || $this->getEntityType($user_id) != Constants::ENTITY_USER)
            throw new \Exception("User or Group doesn't exist", Constants::ERR_ENTITY_NONEXISTENT);

        if ($permissions == 0)
            $this->queryW("DELETE FROM user_permissions WHERE user_id='$user_id' AND group_id='$group_id'");

        $this->queryW("INSERT INTO user_permissions (user_id, group_id, permissions) VALUES ('$user_id', '$group_id', '$permissions') ON DUPLICATE KEY UPDATE user_id='$user_id', group_id='$group_id', permissions='$permissions'");
    }

    public function setGroupPermission($user_id, $group_id, $permission, $value)
    {
        $this->setGroupPermissions($user_id, $group_id, $this->setPermission($this->getGroupPermissions($user_id, $group_id), $permission, $value));
    }

    public function deleteEntity($id)
    {
        //remove all permissions
        switch ($this->getEntityType($id)) {
            case Constants::ENTITY_USER:
                $this->queryW("DELETE from user_permissions WHERE user_id='$id'");
                $this->queryW("DELETE from users WHERE id='$id'");
                break;
            case Constants::ENTITY_GROUP:
                $this->queryW("DELETE from user_permissions WHERE group_id='$id'");
                $this->queryW("DELETE from groups WHERE id='$id'");
                $this->queryW("DELETE from group_permissions WHERE group_id='$id'");
                break;
            case Constants::ENTITY_ATOM:
                $this->queryW("DELETE from group_permissions WHERE atom_id='$id'");
                $this->queryW("DELETE from atoms WHERE id='$id'");
                break;
            case Constants::ENTITY_NONEXISTENT:
                throw new \Exception("Entity doesn't exist", Constants::ERR_ENTITY_NONEXISTENT);

        }

        $this->queryW("DELETE FROM entities WHERE id='$id'");
    }

    //entity types:

    protected function addEntityEntry($entity, $type)
    {
        return $this->queryW("INSERT INTO entities (id, type) VALUES ('$entity', '$type')");
    }

    public function getEntityType($entity)
    {
//        echo "the entity is $entity";
        $result = $this->db->query("SELECT type FROM entities WHERE id='$entity'");
//        echo "The result is $result->num_rows";
        if (!isset($result->num_rows) || $result->num_rows == 0) {
            return Constants::ENTITY_NONEXISTENT;
        } else {
            $thing = $result->fetch_assoc()["type"];
//            echo "The thing iis $thing";
            switch ($thing) {
                case "ATOM":
                    return Constants::ENTITY_ATOM;
                case "GROUP":
                    return Constants::ENTITY_GROUP;
                case "USER":
                    return Constants::ENTITY_USER;
            }
        }
        throw new \Exception("Unknown entity type");
    }

    public function setUsername($user_id, $name)
    {
        if (!$this->getUserName($user_id))
            throw new \Exception("User doesn't exist", Constants::ERR_ENTITY_NONEXISTENT);
        $this->queryW("UPDATE users SET username='$name' WHERE id='$user_id'");
    }

    //todo add some kind of escaping thing

    //crazy template function that made every other function one line - tres bon!

    protected function queryW($query)
    {
        $result = $this->db->query($query);

        if (!$result)
            throw new \Exception("Database query failed", Constants::ERR_DB);

        return $result;
    }

    protected function queryEntity($query, $obj = "", $zeroret = null, $singleret = null, $multret = null)
    {
        $result = $this->queryW($query);

        if ($result->num_rows == 0)
            if (!isset($zeroret))
                throw new \Exception("$obj doesn't exist", Constants::ERR_ENTITY_NONEXISTENT);
            else
                return $zeroret;
        else if ($result->num_rows == 1)
            if (!isset($singleret))
                return $result->fetch_assoc();
            else
                return $singleret;
        else
            if (!isset($multret))
                throw new \Exception("Fatal database error - duplicate $obj entry", Constants::ERR_DB);
            else
                return $multret;
    }
}