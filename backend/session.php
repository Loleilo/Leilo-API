<?php

/**
 * Created by PhpStorm.
 * User: Sunny
 * Date: 2017-04-27
 * Time: 2:33 PM
 * Session security code is by Robert Hafner
 * Manages sessions and allowed permissions within sessions
 */

namespace Leilo;

require_once 'paths.php';
require_once 'leilodb.php';
require_once "consts.php";

class SessionSecure
{

    //very misleading name - it is used to start a session, but ALSO IS FOR RESUMING ONE THAT HAS ALREADY STARTED
    public static function sessionStart($name, $path, $secure = null, $domain = null, $limit = 0)
    {
        // Set the cookie name before we start.
        session_name($name . '_Session');

        // Set the domain to default to the current domain.
        $domain = isset($domain) ? $domain : $_SERVER['SERVER_NAME'];

        // Set the default secure value to whether the site is being accessed with SSL
        $https = isset($secure) ? $secure : isset($_SERVER['HTTPS']);

        // Set the cookie settings and start the session
        session_set_cookie_params($limit, $path, $domain, $https, true);

        //start/resume session
        if (!isset($_SESSION))
            session_start();

        //check if current session is valid
        if (self::validateSession()) {
            //check to make sure session is coming from same user
            if (!self::preventHijacking()) {
                //create a new session for this new ip, make sure not to reuse old one
                $_SESSION = array();
                $_SESSION['IPaddress'] = $_SERVER['REMOTE_ADDR'];
                $_SESSION['userAgent'] = $_SERVER['HTTP_USER_AGENT'];
                self::regenerateSession();
            } elseif (rand(1, 100) <= 5) {
                //5% chance of session id to change
                self::regenerateSession();
            }
        } else {
            //if it's invalid destroy it and make a new one
            $_SESSION = array();
            session_destroy();
            session_start();
        }
    }

    public static function invalidateSession()
    {
        // If this session is obsolete it means there already is a new id
        if (isset($_SESSION['OBSOLETE']))
            return;

        // Set current session to expire in 10 seconds
        $_SESSION['OBSOLETE'] = true;
        $_SESSION['EXPIRES'] = time() + 10;
    }

    //fixes session to single IP (prevents session from being used on multiple computers)
    static protected function preventHijacking()
    {
        //if session doesn't have ip fixed to it, make sure it does
        if (!isset($_SESSION['IPaddress']) || !isset($_SESSION['userAgent']))
            return false;

        if ($_SESSION['IPaddress'] != $_SERVER['REMOTE_ADDR'])
            return false;

        if ($_SESSION['userAgent'] != $_SERVER['HTTP_USER_AGENT'])
            return false;

        return true;
    }

    public static function regenerateSession()
    {
        self::invalidateSession();

        // Create new session without destroying the old one
        session_regenerate_id(false);

        // Grab current session ID and close both sessions to allow other scripts to use them
        $newSession = session_id();
        session_write_close();

        // Set session ID to the new one, and start it back up again
        session_id($newSession);
        session_start();

        // Now we unset the obsolete and expiration values for the session we want to keep
        unset($_SESSION['OBSOLETE']);
        unset($_SESSION['EXPIRES']);
    }

    //checks if session is valid
    static protected function validateSession()
    {
        //if session is obselete but has no expire time, it is invalid
        if (isset($_SESSION['OBSOLETE']) && !isset($_SESSION['EXPIRES']))
            return false;

        //if the current time is past the expiry time, it is invalid
        if (isset($_SESSION['EXPIRES']) && $_SESSION['EXPIRES'] < time())
            return false;

        return true;
    }
}

//maps basic DB operations to actual operations a user in a session can perform, enforces permissionss
class LeiloSessionManager
{

    protected $bi, $user_id, $config;

    function __construct(LeiloDB $bi)
    {
        $this->bi = $bi;
        $this->config = parse_ini_file(API_PATH . '/backend/config.ini');
    }

    // if user id is set to a value, a new session vill be generated
    public function tryActivateSession($user_id = null)
    {
        SessionSecure::sessionStart($this->config["sessionname"], $this->config["sessionpath"]);
        if ($user_id != null) {
            SessionSecure::regenerateSession();
            $_SESSION["user_auth"] = $user_id;
        }

        if ($this->isLoggedIn())
            $this->user_id = $_SESSION["user_auth"];
        else
            throw new \Exception("Not logged in", Constants::ERR_NO_PERMS);
    }

    public function isLoggedIn()
    {
        return isset($_SESSION["user_auth"]);
    }

    public function killSession()
    {
        $_SESSION["user_auth"] = null;
        $this->user_id = null;
        SessionSecure::invalidateSession();
    }

    protected function loginException()
    {
        if (!$this->isLoggedIn())
            throw new \Exception("Not logged in", Constants::ERR_NO_PERMS);
    }

    //read functions
    public function getUserID()
    {
        $this->loginException();
        return $this->user_id;
    }

    public function getUserName()
    {
        $this->loginException();
        return $this->bi->getUserName($this->user_id);
    }

    public function listGroups()
    {
        $this->loginException();
        return $this->bi->getGroups($this->user_id);
    }

    public function getGroupName($group_id)
    {
        $this->loginException();
        $group_perms = $this->getGroupPermissions($group_id);
        if (LeiloDB::checkPermission($group_perms, Constants::PERM_READ))
            return $this->bi->getGroupName($group_id);
        else throw new \Exception("Not enough permissions", Constants::ERR_NO_PERMS);
    }

    public function getGroupPermissions($group_id)
    {
        $this->loginException();
        return $this->bi->getGroupPermissions($this->user_id, $group_id);
    }

    public function listAtoms($group_id)
    {
        $this->loginException();
        $group_perms = $this->getGroupPermissions($group_id);
        if (LeiloDB::checkPermission($group_perms, Constants::PERM_READ))
            return $this->bi->getAtoms($group_id);
        else throw new \Exception("Not enough permissions", Constants::ERR_NO_PERMS);
    }

    public function lookupGroups($group_id, $atom_id)
    {
        $this->loginException();
        $atom_perms = $this->getAtomPermissions($group_id, $atom_id);
        if (LeiloDB::checkPermission($atom_perms, Constants::PERM_CONFIG)) {
            return $this->bi->lookupGroups($atom_id);
        } else throw new \Exception("Not enough permissions", Constants::ERR_NO_PERMS);
    }

    public function lookupUsers($group_id)
    {
        $this->loginException();
        $group_perms = $this->getGroupPermissions($group_id);
        if (LeiloDB::checkPermission($group_perms, Constants::PERM_CONFIG)) {
            return $this->bi->lookupUsers($group_id);
        } else throw new \Exception("Not enough permissions", Constants::ERR_NO_PERMS);
    }

    public function readAtom($group_id, $atom_id)
    {
        $this->loginException();
        $atom_perms = $this->getAtomPermissions($group_id, $atom_id);
        if (LeiloDB::checkPermission($atom_perms, Constants::PERM_READ))
            return $this->bi->readAtom($atom_id);
        else throw new \Exception("Not enough permissions", Constants::ERR_NO_PERMS);
    }

    public function getAtomName($group_id, $atom_id)
    {
        $this->loginException();
        $atom_perms = $this->getAtomPermissions($group_id, $atom_id);
        if (LeiloDB::checkPermission($atom_perms, Constants::PERM_READ))
            return $this->bi->getAtomName($atom_id);
        else throw new \Exception("Not enough permissions", Constants::ERR_NO_PERMS);
    }

    public function getAtomPermissions($group_id, $atom_id)
    {
        $this->loginException();
        $group_perms = $this->getGroupPermissions($group_id);
        return $group_perms & $this->bi->getAtomPermissions($group_id, $atom_id);

    }

    //write functions

    public function writeAtom($group_id, $atom_id, $value)
    {
        $this->loginException();
        $atom_perms = $this->getAtomPermissions($group_id, $atom_id);
        if (LeiloDB::checkPermission($atom_perms, Constants::PERM_WRITE)) {
            $this->bi->writeAtom($atom_id, $value);
        } else throw new \Exception("Not enough permissions", Constants::ERR_NO_PERMS);
    }

    //config functions

    public function setUsername($username)
    {
        $this->loginException();
        $this->bi->setUsername($this->user_id, $username);
    }

    public function deleteUser()
    {
        $this->loginException();

        $this->bi->deleteEntity($this->user_id);
        $this->killSession();
    }

    public function createGroup($name = null)
    {
        $this->loginException();

        $UUID = $this->bi->createGroup($name);
        $this->bi->setGroupPermissions($this->user_id, $UUID, Constants::PERMS_ALL);

        return $UUID;
    }

    public function deleteGroup($group_id)
    {
        $this->loginException();

        $group_perms = $this->getGroupPermissions($group_id);
        if (LeiloDB::checkPermission($group_perms, Constants::PERM_CONFIG))
            $this->bi->deleteEntity($group_id);
        else throw new \Exception("Not enough permissions", Constants::ERR_NO_PERMS);
    }

    public function setGroupPermissions($group_id, $user_id, $permissions)
    {
        $this->loginException();

        $group_perms = $this->getGroupPermissions($group_id);

        if (LeiloDB::checkPermission($group_perms, Constants::PERM_CONFIG))
            $this->bi->setGroupPermissions($user_id, $group_id, $permissions);
        else throw new \Exception("Not enough permissions", Constants::ERR_NO_PERMS);
    }

    public function setGroupPermission($group_id, $user_id, $permission, $value)
    {
        $this->loginException();

        $group_perms = $this->getGroupPermissions($group_id);
        if (LeiloDB::checkPermission($group_perms, Constants::PERM_CONFIG))
            $this->bi->setGroupPermission($user_id, $group_id, $permission, $value);
        else throw new \Exception("Not enough permissions", Constants::ERR_NO_PERMS);
    }

    public function setAtomPermissions($group_src, $atom_id, $group_dst, $permissions)
    {
        $this->loginException();

        $atom_perms = $this->getAtomPermissions($group_src, $atom_id);
        if (LeiloDB::checkPermission($atom_perms, Constants::PERM_CONFIG)) {
            $this->bi->setAtomPermissions($group_dst, $atom_id, $permissions);
        } else throw new \Exception("Not enough permissions", Constants::ERR_NO_PERMS);
    }

    public function setAtomPermission($group_src, $atom_id, $group_dst, $permission, $value)
    {
        $this->loginException();

        $atom_perms = $this->getAtomPermissions($group_src, $atom_id);
        if (LeiloDB::checkPermission($atom_perms, Constants::PERM_CONFIG)) {
            $this->bi->setAtomPermission($group_dst, $atom_id, $permission, $value);
        } else throw new \Exception("Not enough permissions", Constants::ERR_NO_PERMS);
    }

    public function createAtom($group_id, $init_val = null, $name = null)
    {
        $this->loginException();

        $group_perms = $this->getGroupPermissions($group_id);
        if (LeiloDB::checkPermission($group_perms, Constants::PERM_CONFIG)) {
            $atom_id = $this->bi->createAtom($init_val, $name);
            $this->bi->setAtomPermissions($group_id, $atom_id, Constants::PERMS_ALL);
            return $atom_id;
        } else throw new \Exception("Not enough permissions", Constants::ERR_NO_PERMS);

    }

    public function setAtomName($group_id, $atom_id, $name)
    {
        $this->loginException();

        $atom_perms = $this->getAtomPermissions($group_id, $atom_id);
        if (LeiloDB::checkPermission($atom_perms, Constants::PERM_CONFIG)) {
            $this->bi->setAtomName($atom_id, $name);
        } else throw new \Exception("Not enough permissions", Constants::ERR_NO_PERMS);
    }

    public function setGroupName($group_id, $name)
    {
        $this->loginException();

        $group_perms = $this->getGroupPermissions($group_id);
        if (LeiloDB::checkPermission($group_perms, Constants::PERM_CONFIG)) {
            $this->bi->setGroupName($group_id, $name);
        } else throw new \Exception("Not enough permissions", Constants::ERR_NO_PERMS);
    }

    public function getWidget($widget_id)
    {
        $this->loginException();
        if ($this->bi->getWidgetOwner($widget_id) == $this->user_id) {
            return $this->bi->getWidget($widget_id);
        } else throw new \Exception("Not enough permissions", Constants::ERR_NO_PERMS);
    }

    public function writeWidget($widget_id, $config)
    {
        $this->loginException();
        if ($this->bi->getWidgetOwner($widget_id) == $this->user_id) {
            $this->bi->writeWidget($widget_id, $config);
        } else throw new \Exception("Not enough permissions", Constants::ERR_NO_PERMS);
    }

    public function createWidget($config)
    {
        $this->loginException();
        $UUID = $this->bi->createWidget($config);
        $this->bi->setWidgetOwner($this->user_id, $UUID);
        return $UUID;
    }

    public function deleteWidget($widget_id)
    {
        $this->loginException();
        $this->bi->deleteWidget($widget_id);
    }
}