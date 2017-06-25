<?php
/**
 * Created by PhpStorm.
 * User: Sunny
 * Date: 2017-04-29
 * Time: 10:20 AM
 */

namespace Leilo;

require_once "backend/leilodb.php";
require_once "backend/session.php";

function p($param)
{
    if (!isset($param))
        throw new \Exception("Invalid parameter input", Constants::ERR_INVALID_ARGS);
    return $param;
}

function po($param)
{ 
    if (!isset($param))
        return null;
    return $param;
}

function pp($arr, $name)
{
    $arr = p($arr);
    if (!is_array($arr) || !isset($arr[$name]))
        throw new \Exception("Invalid parameter list", Constants::ERR_INVALID_ARGS);
    return $arr[$name];
}

function ppo($arr, $name)
{
    $arr = po($arr);
    if (!is_array($arr) || !isset($arr[$name]))
        return null;
    return $arr[$name];
}

function db()
{
    global $db;
    if (isset($db))
        return $db;
    $db = new LeiloDB(Util::db_connect());
    return $db;
}

//lazy loading for manager - prevents making db connection when uneeded
function m()
{
    global $mgr;
    if (isset($mgr))
        return $mgr;
    $mgr = new LeiloSessionManager(db());
    return $mgr;
}

$returnData = null;

try {
    if (strcasecmp($_SERVER['REQUEST_METHOD'], 'POST') != 0)
        throw new \Exception("Invalid request method", Constants::ERR_INVALID_REQ);

    //Receive the RAW post data.
    $content = trim(file_get_contents("php://input"));

    //Attempt to decode the incoming RAW post data from JSON.
    $req = json_decode($content, true);

    //If json_decode failed, the JSON is invalid.
    if (!is_array($req)) {
        throw new \Exception('Invalid request format', Constants::ERR_INVALID_REQ);
    }

    $ver = pp($req, "version");
    if ($ver != Constants::PROTOCOL_VER)
        throw new \Exception("Version mismatch", Constants::ERR_VERSION_MISMATCH);


    $name = pp($req, "call");
    $params = pp($req, "params");

    //todo delete this part when done testing
    if (isset($req["testing"]) && $req["testing"] == true) {
        if ($name == "_createUser") {
            $user = pp($params, "username");
            $pass = pp($params, "password");
            $returnData = db()->createUser($user, password_hash($pass, Constants::PASS_HASH));
        } else if ($name == "_createGroup") {
            $name = ppo($params, "name");
            $returnData = db()->createGroup($name);
        } else if ($name == "_deleteEntity") {
            $id = ppo($params, "id");
            db()->deleteEntity($id);
        } else if ($name == "_setGroupPermissions") {
            $user = pp($params, "user_id");
            $group = pp($params, "group_id");
            $perms = pp($params, "permissions");
            db()->setGroupPermissions($user, $group, $perms);
        } else if ($name == "_createAtom") {
            $init_val = pp($params, "init_val");
            $name = ppo($params, "name");
            $returnData = db()->createAtom($init_val, $name);
        } else if ($name == "_setAtomPermissions") {
            $group_id = pp($params, "group_id");
            $atom = pp($params, "atom_id");
            $perms = pp($params, "permissions");
            db()->setAtomPermissions($group_id, $atom, $perms);
        }

    } else {

        if ($name == "login") {
            if (isset($params["user_id"])) {
                $uid = pp($params, "user_id");
            } else {
                $username = pp($params, "username");
                $uid = db()->getUserUUID($username);
            }

            $pass = pp($params, "password");
            if (db()->checkHashMatch($uid, $pass)) {
                m()->tryActivateSession($uid);
            } else {
                throw new \Exception("Invalid login credentials", Constants::ERR_INVALID_LOGIN);
            }
        } else if ($name == "isLoggedIn") {
            try {
                m()->tryActivateSession();
                $returnData = m()->isLoggedIn();
            } catch (\Exception $e) {
                if ($e->getCode() == Constants::ERR_NO_PERMS)
                    $returnData = false;
                else
                    throw $e;
            }
        } else {
            m()->tryActivateSession();
            if ($name == "killSession")
                m()->killSession();
            else if ($name == "getUserID")
                $returnData = m()->getUserID();
            else if ($name == "getUserName")
                $returnData = m()->getUserName();
            else if ($name == "listGroups")
                $returnData = m()->listGroups();
            else if ($name == "getGroupName") {
                $group_id = pp($params, "group_id");
                $returnData = m()->getGroupName($group_id);
            } else if ($name == "getGroupPermissions") {
                $group_id = pp($params, "group_id");
                $returnData = m()->getGroupPermissions($group_id);
            } else if ($name == "listAtoms") {
                $group_id = pp($params, "group_id");
                $returnData = m()->listAtoms($group_id);
            } else if ($name == "readAtom") {
                $group_id = pp($params, "group_id");
                $atom_id = pp($params, "atom_id");
                $returnData = m()->readAtom($group_id, $atom_id);
            } else if ($name == "getAtomPermissions") {
                $group_id = pp($params, "group_id");
                $atom_id = pp($params, "atom_id");
                $returnData = m()->getAtomPermissions($group_id, $atom_id);
            } else if ($name == "getAtomName") {
                $group_id = pp($params, "group_id");
                $atom_id = pp($params, "atom_id");
                $returnData = m()->getAtomName($group_id, $atom_id);
            } else if ($name == "writeAtom") {
                $group_id = pp($params, "group_id");
                $atom_id = pp($params, "atom_id");
                $value = pp($params, "value");
                m()->writeAtom($group_id, $atom_id, $value);
            } else if ($name == "setUsername") {
                $username = pp($params, "username");
                m()->setUsername($username);
            } else if ($name == "deleteUser") {
                m()->deleteUser();
            } else if ($name == "createGroup") {
                $name1 = ppo($params, "name");
                $returnData = m()->createGroup($name1);
            } else if ($name == "deleteGroup") {
                $group_id = pp($params, "group_id");
                m()->deleteGroup($group_id);
            } else if ($name == "setGroupPermissions") {
                $group_id = pp($params, "group_id");
                $user_id = pp($params, "user_id");
                $perms = pp($params, "permissions");
                m()->setGroupPermissions($group_id, $user_id, $perms);
            } else if ($name == "setGroupPermission") {
                $group_id = pp($params, "group_id");
                $user_id = pp($params, "user_id");
                $perm = pp($params, "permission");
                $value = pp($params, "value");
                m()->setGroupPermission($group_id, $user_id, $perm, $value);
            } else if ($name == "setAtomPermissions") {
                $group_src = pp($params, "group_src");
                $group_dst = pp($params, "group_dst");
                $atom_id = pp($params, "atom_id");
                $perms = pp($params, "permissions");
                m()->setAtomPermissions($group_src, $atom_id, $group_dst, $perms);
            } else if ($name == "setAtomPermission") {
                $group_src = pp($params, "group_src");
                $group_dst = pp($params, "group_dst");
                $atom_id = pp($params, "atom_id");
                $perm = pp($params, "permission");
                $value = pp($params, "value");
                m()->setAtomPermission($group_src, $atom_id, $group_dst, $perm, $value);
            } else if ($name == "lookupUsers") {
                $group_id = pp($params, "group_id");
                $returnData = m()->lookupUsers($group_id);
            } else if ($name == "lookupGroups") {
                $group_id = pp($params, "group_id");
                $atom_id = pp($params, "atom_id");
                $returnData = m()->lookupGroups($group_id, $atom_id);
            } else if ($name == "createAtom") {
                $group_id = pp($params, "group_id");
                $init_val = ppo($params, "init_val");
                $name1 = ppo($params, "name");
                $returnData = m()->createAtom($group_id, $init_val, $name1);
            } else if ($name == "setAtomName") {
                $group_id = pp($params, "group_id");
                $atom_id = pp($params, "atom_id");
                $name1 = pp($params, "name");
                m()->setAtomName($group_id, $atom_id, $name1);
            } else if ($name == "setGroupName"){
                $group_id = pp($params, "group_id");
                $name1 = pp($params, "name");
                m()->setGroupName($group_id, $name1);
            }
            else {
                throw new \Exception("Invalid call", Constants::ERR_INVALID_CALL);
            }
        }
    }

    $retcode = 0;
} catch (\Exception $ex) {
    $retcode = $ex->getCode();
    $returnData = $ex->getMessage();
}

echo json_encode([
    "returnCode" => $retcode,
    "returnData" => $returnData
]);