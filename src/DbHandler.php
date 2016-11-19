 <?php
 use Firebase\JWT\JWT;

 /**
 * Classe pour gérer toutes les opérations de db
 * Cette classe aura les méthodes CRUD pour les tables de base de données
 *

 */
class DbHandler {

    private $conn;
    private $log;
    function __construct($logger) {
        require_once dirname(__FILE__) . '/DbConnect.php';
        include_once dirname(__FILE__) . '/config.php';
        include_once dirname(__FILE__) . '/tokenHandler.php';
        //Open connexion db
        $db = new DbConnect();
        $this->conn = $db->connect();
        // create a log channel
        $this->log = $logger;
    }

    public function signUp($firstName, $lastName, $tel, $password) {
        require_once 'PassHash.php';

        if (!$this->isUserExists($tel)) {
            //Generate a hashed password
            $password_hash = PassHash::hash($password);

            //insert user
            $stmt = $this->conn->prepare("INSERT INTO users(first_name, last_name, tel, password, status) values(?, ?, ?, ?, 1)");
            $stmt->bind_param('ssss', $firstName, $lastName, $tel, $password_hash);
            $result = $stmt->execute();
            $stmt->store_result();
            $userId = $stmt->insert_id;
            $stmt->close();

            //Verify if the insertion is succeeded
            //see config.php
            if ($result) {
                return USER_CREATED_SUCCESSFULLY;
            } else {
                return USER_CREATE_FAILED;
            }
        } else {
            return USER_ALREADY_EXISTED;
        }

    }

    public function updateToken($userId, $newToken){
        $stmt = $this->conn->prepare("UPDATE users SET token = ? WHERE id = ?");
        $stmt->bind_param('bi', $foo, $userId);
        $stmt->send_long_data(0, $newToken);
        $result = $stmt->execute();
        $stmt->close();
        if ($result) {return TRUE;}
        else{
            echo "Error updating record: " . mysqli_error($this->conn);
            return FALSE;}
    }

    private function isUserExists($tel) {
        $stmt = $this->conn->prepare("SELECT id from users WHERE tel = ?");
        $stmt->bind_param("s", $tel);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    public function signIn($tel, $password) {
        // Obtention de l'utilisateur par tel
        $stmt = $this->conn->prepare("SELECT password FROM users WHERE tel = ?");

        $stmt->bind_param("s", $tel);

        $stmt->execute();

        $stmt->bind_result($password_hash);

        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // Found user with tel
            // verify password
            $stmt->fetch();

            $stmt->close();

            if (PassHash::check_password($password_hash, $password)) {
                return AUTHENTICATE_SUCCESS;
            } else {
                return WRONG_PASSWORD;
            }
        } else {
            $stmt->close();
            //user doesn't exist
            return USER_NON_EXIST;
        }
    }

    public function getUserIdByTel($tel){
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE tel = ?");
        $stmt->bind_param("s", $tel);
        if ($stmt->execute()) {
            $stmt->bind_result($user_id);
            $stmt->fetch();
            $stmt->close();
            return $user_id;
        } else {
            return NULL;
        }
    }

    public function getUserByTel($tel) {
        $user = array();
        $this->conn->autocommit(FALSE);
        $stmt = $this->conn->prepare("SELECT id, first_name, last_name, tel, token, status, created_at FROM users WHERE tel = ?");
        $stmt->bind_param("s", $tel);
        if ($stmt->execute()) {
            $stmt->bind_result($id, $firstName, $lastName, $tel, $token, $status, $created_at);
            $stmt->fetch();
            $user["id"]= $id;
            $user["firstName"] = $firstName;
            $user["lastName"] = $lastName;
            $user["tel"] = $tel;
            $user["token"] = $token;
            $user["status"] = $status;
            $user["created_at"] = $created_at;
            $stmt->close();
        } else {
            return NULL;
        }

        $stmt_2= $this->conn->prepare("SELECT apps.id, apps.app_name, apps.icon FROM apps_users INNER JOIN apps ON apps_users.app_id = apps.id WHERE apps_users.user_id = ?");
        $stmt_2->bind_param("s", $user["id"]);
        if($stmt_2->execute()){
            $apps_users = array();
            $stmt_2->bind_result($app_id, $app_name,$app_icon);
            while($stmt_2->fetch()){
                $apps_users[] = array("id"=>$app_id, "name" =>$app_name, "icon"=>$app_icon);
            }
            $user["sharedApps"] = $apps_users;
            $stmt_2->close();
        } else {
            return $user;
        }

        $stmt_1= $this->conn->prepare("SELECT apps.id, apps.app_name, apps.icon FROM apps_admins INNER JOIN apps ON apps_admins.app_id = apps.id WHERE apps_admins.admin_id = ?");
        $stmt_1->bind_param("s", $user["id"]);
        if($stmt_1->execute()){
            $apps_admins = array();
            $stmt_1->bind_result($app_id, $app_name, $app_icon);
            while($stmt_1->fetch()){
                $apps_admins[] = array("id"=>$app_id, "name" =>$app_name, "icon"=>$app_icon);
            }
            $user["createdApps"] = $apps_admins;
            $stmt_1->close();
        } else {
            return $user;
        }
        return $user;
    }

    public function getUserById($id) {
        $user = array();
        $this->conn->autocommit(FALSE);
        $stmt = $this->conn->prepare("SELECT id, first_name, last_name, tel, token, status, created_at FROM users WHERE id = ?");
        $stmt->bind_param("s", $id);
        if ($stmt->execute()) {
            $stmt->bind_result($id, $firstName, $lastName, $tel, $token, $status, $created_at);
            $stmt->fetch();
            $user["id"]= $id;
            $user["firstName"] = $firstName;
            $user["lastName"] = $lastName;
            $user["tel"] = $tel;
            $user["token"] = $token;
            $user["status"] = $status;
            $user["created_at"] = $created_at;
            $stmt->close();
        } else {
            return NULL;
        }

        $stmt_2= $this->conn->prepare("SELECT apps.id, apps.app_name, apps.icon FROM apps_users INNER JOIN apps ON apps_users.app_id = apps.id WHERE apps_users.user_id = ?");
        $stmt_2->bind_param("s", $user["id"]);
        if($stmt_2->execute()){
            $apps_users = array();
            $stmt_2->bind_result($app_id, $app_name,$app_icon);
            while($stmt_2->fetch()){
                $apps_users[] = array("id"=>$app_id, "name" =>$app_name, "icon"=>$app_icon);
            }
            $user["sharedApps"] = $apps_users;
            $stmt_2->close();
        } else {
            return $user;
        }

        $stmt_1= $this->conn->prepare("SELECT apps.id, apps.app_name, apps.icon FROM apps_admins INNER JOIN apps ON apps_admins.app_id = apps.id WHERE apps_admins.admin_id = ?");
        $stmt_1->bind_param("s", $user["id"]);
        if($stmt_1->execute()){
            $apps_admins = array();
            $stmt_1->bind_result($app_id, $app_name,$app_icon);
            while($stmt_1->fetch()){
                $apps_admins[] = array("id"=>$app_id, "name" =>$app_name, "icon"=>$app_icon);
            }
            $user["createdApps"] = $apps_admins;
            $stmt_1->close();
        } else {
            return $user;
        }
        return $user;
    }

    public function getUserId($token) {
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE token = ?");
        $stmt->bind_param("s", $token);
        if ($stmt->execute()) {
            $stmt->bind_result($user_id);
            $stmt->fetch();

            $stmt->close();
            return $user_id;
        } else {
            return NULL;
        }
    }

    public function createAppWithoutMedia($name, $creatorId,$category,$font,$theme,$layout,$description){
        $stmt = $this->conn->prepare("INSERT INTO apps (app_name,creator_id,category,font,theme,layout,description) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("sisssss", $name,$creatorId,$category,$font,$theme,$layout,$description);
        if ($stmt->execute()) {
            $appId = $stmt->insert_id;
            $stmt->close();
        } else {
            return NULL;
        }
        return $appId;
    }

    public function saveFileToApp($appId, $path, $type, $role){
        $this->log->addInfo("addAppRes of role ".$role." for app number ".$appId.", path: ".$path);
        $stmt = $this->conn->prepare("INSERT INTO media_contents (path, content_type, app_id) VALUES (?,?,?)");
        $stmt->bind_param("ssi",$path, $type,$appId);
        if ($stmt->execute()) {
            $id = $stmt->insert_id;
            $stmt->close();
        } else {
            return NULL;
        }
        if ($role=="icon"){
            $stmt1 = $this->conn->prepare('UPDATE apps SET icon = ? WHERE id = ?');
            $stmt1->bind_param("ii", $id, $appId);
            if ($stmt1->execute()) {
                $stmt1->close();
            } else {
                return NULL;
            }
        }
        if ($role=="background"){
            $stmt2 = $this->conn->prepare('UPDATE apps SET background = ? WHERE id = ?');
            $stmt2->bind_param("ii", $id, $appId);
            if ($stmt2->execute()) {
                $stmt2->close();
            } else {
                return NULL;
            }
        }
        return $id;
    }

    public function addUserToApp($appId, $userId){
        //Verify if this user has already been added
        $stmt1 = $this->conn->prepare("SELECT * FROM apps_users WHERE app_id = ? AND user_id = ?");
        $stmt1->bind_param("ii", $appId, $userId);
        $stmt1->execute();
        $stmt1->store_result();
        if($stmt1->num_rows>0){
            return FALSE;
        }
        $stmt1->close();

        $stmt2 = $this->conn->prepare("INSERT INTO apps_users (user_id, app_id) VALUES (?,?)");
        $stmt2->bind_param("ii",$userId, $appId);
        if($stmt2->execute()){
            $stmt2->close();
            return TRUE;
        }
        return FALSE;
    }

    public function removeUserFromApp($appId, $userId){
        //Verify if this user is in the app's list
        $this->log->addInfo("Call function removeUserFromApp");
        $stmt1 = $this->conn->prepare("SELECT * FROM apps_users WHERE app_id = ? AND user_id = ?");
        $stmt1->bind_param("ii", $appId, $userId);
        $stmt1->execute();
        $stmt1->store_result();
        if($stmt1->num_rows==0){
            $this->log->addInfo("User not found from app's list");
            return FALSE;
        }
        $stmt1->close();
        $stmt2 = $this->conn->prepare("DELETE FROM apps_users WHERE app_id = ? AND user_id = ?");
        $stmt2->bind_param("ii",$appId, $userId);
        if($stmt2->execute()){
            $stmt2->close();
            $this->log->addInfo("Deleted user from app's list");
            return TRUE;
        }
        $this->log->addInfo("Failed to delete");
        return FALSE;
    }

    public function addAdminToApp($appId, $adminId){
        //Verify if this user has already been added
        $stmt1 = $this->conn->prepare("SELECT * FROM apps_admins WHERE app_id = ? AND admin_id = ?");
        $stmt1->bind_param("ii", $appId, $adminId);
        $stmt1->execute();
        $stmt1->store_result();
        if($stmt1->num_rows>0){
            return FALSE;
        }
        $stmt1->close();

        $stmt2 = $this->conn->prepare("INSERT INTO apps_admins (admin_id, app_id) VALUES (?,?)");
        $stmt2->bind_param("ii",$adminId, $appId);
        if($stmt2->execute()){
            $stmt2->close();
            return TRUE;
        }
        return FALSE;
    }

    public function getApp($appId){
        $stmt = $this->conn->prepare("SELECT app_name,creator_id,category,font,theme,background, icon,layout,description FROM apps WHERE id = ?");
        $stmt->bind_param("i", $appId);
        if($stmt->execute()){
            $stmt->bind_result($name, $creatorId,$category,$font,$theme,$background, $icon,$layout,$description);
            $stmt->fetch();
            $app["id"]=$appId;
            $app["name"]=$name;
            $app["creatorId"]=$creatorId;
            $app["category"]=$category;
            $app["font"]=$font;
            $app["theme"]=$theme;
            $app["background"]=$background;
            $app["icon"]=$icon;
            $app["layout"]=$layout;
            $app["description"]=$description;
            $stmt->close();
        } else return null;
        //get user of this app
        $stmt1 = $this->conn->prepare("SELECT apps_users.user_id, users.first_name, users.last_name, users.tel FROM apps_users INNER JOIN users  ON apps_users.user_id = users.id WHERE apps_users.app_id = ?");
        $stmt1->bind_param("i", $appId);
        $users = array();
        if($stmt1->execute()){
            $stmt1->bind_result($userId, $user_first, $user_last, $user_tel);
            while ($stmt1->fetch()){
                $users[] = array("id"=>$userId, "firstName" =>$user_first, "lastName"=>$user_last, "tel"=>$user_tel);
            }
            $stmt1->close();
            $app["users"] = $users;
        } else return null;

        //get admin of this app
        $stmt2 = $this->conn->prepare("SELECT apps_admins.admin_id, users.first_name, users.last_name, users.tel FROM apps_admins INNER JOIN users  ON apps_admins.admin_id = users.id WHERE apps_admins.app_id = ?");
        $stmt2->bind_param("i", $appId);
        $admins = array();
        if ($stmt2->execute()){
            $stmt2->bind_result($adminId, $admin_first, $admin_last, $admin_tel);
            while ($stmt2->fetch()){
                $admins[] = array("id"=>$adminId, "firstName" =>$admin_first, "lastName"=>$admin_last, "tel"=>$admin_tel);
            }
            $stmt2->close();
            $app["admins"] = $admins;
        } else return NULL;

        //get list of containers

        $stmt3 = $this->conn->prepare("SELECT m.id, m.name, m.type FROM module_containers as m WHERE app_id = ?");
        $stmt3->bind_param("i", $appId);
        $containers = array();
        if ($stmt3->execute()){
            $stmt3->bind_result($cId, $cName, $cType);
            while ($stmt3->fetch()){
                $containers[] = array("id"=>$cId, "name" =>$cName, "type"=>$cType);
            }
            $stmt3->close();
            $app["containers"] = $containers;
        } else return NULL;
        return $app;
    }

    public function getMediaPath($id){
        $this->log->addInfo("getMediaPath of ".$id);
        $stmt = $this->conn->prepare("SELECT path FROM media_contents WHERE id = ?");
        $stmt->bind_param("i",$id);
        if($stmt->execute()){
            $stmt->bind_result($path);
            $stmt->fetch();
            $stmt->close();
            return $path;
        }
        return FALSE;
    }

    public function getListIdFromModule($moduleId){
        $this->log->addInfo("Get list of content id of module ". $moduleId);
        $stmt = $this->conn->prepare("SELECT id FROM media_contents WHERE module_id = ?");
        $stmt->bind_param("i",$moduleId);
        if($stmt->execute()){
            $listId = array();
            $stmt->bind_result($id);
            while($stmt->fetch()){
                $listId[] = $id;
            }
            return $listId;
        }
        return null;
    }

    public function saveFileToModule($moduleId, $path, $type){
        $this->log->addInfo("save file of type ".$type." to module content sharing number ".$moduleId.", path: ".$path);
        $stmt = $this->conn->prepare("INSERT INTO media_contents (path, content_type, module_id) VALUES (?,?,?)");
        $stmt->bind_param("ssi",$path, $type, $moduleId);
        if ($stmt->execute()) {
            $id = $stmt->insert_id;
            $stmt->close();
        } else {
            return NULL;
        }
        return $id;
    }

    public function deleteFile($id){
        $stmt1 = $this->conn->prepare("DELETE FROM media_contents WHERE id = ?");
        $stmt1->bind_param("i", $id);
        if ($stmt1->execute()) {
            $stmt1->close();
            $this->log->addInfo("Deleted file with id ".$id. " from media_contents");
        } else {
            return false;
        }
        return true;
    }

    public function addModuleMedia($containerId, $name, $type){
        $stmt = $this->conn->prepare("INSERT INTO content_sharing_module (container_id, name, content_type) VALUES (?,?,?)");
        $stmt->bind_param("iss", $containerId, $name, $type);
        if ($stmt->execute()) {
            $moduleId = $stmt->insert_id;
            $stmt->close();
        } else {
            return NULL;
        }
        return $moduleId;
    }

    public function deleteModuleMedia($moduleId){
        $stmt = $this->conn->prepare("DELETE FROM content_sharing_module WHERE id = ?");
        $stmt->bind_param("i", $moduleId);
        if ($stmt->execute()) {
            $stmt->close();
            $this->log->addInfo("Deleted module number ".$moduleId. " from content_sharing_module");
        } else {
            return false;
        }
        $stmt1 = $this->conn->prepare("DELETE FROM media_contents WHERE module_id = ?");
        $stmt1->bind_param("i", $moduleId);
        if ($stmt1->execute()) {
            $stmt1->close();
            $this->log->addInfo("Deleted module number ".$moduleId. " from media_contents");
        } else {
            return false;
        }
        return true;
    }

    public function updateModuleMedia($moduleId, $newName){
        $stmt = $this->conn->prepare("UPDATE content_sharing_module SET name = ? WHERE id = ?");
        $stmt->bind_param("si", $newName, $moduleId);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            return false;
        }
    }

    public function createContainer($appId, $name, $type){
        $this->log->addInfo("creating container");
        $stmt = $this->conn->prepare("INSERT INTO module_containers (app_id, name, type) VALUES (?,?,?)");
        $stmt->bind_param("iss", $appId, $name, $type);
        if ($stmt->execute()) {
            $containerId = $stmt->insert_id;
            $stmt->close();
        } else {
            return NULL;
        }
        $this->log->addInfo("container created with id ".$containerId);
        return $containerId;
    }

    public function getContainerDetails($containerId){
        $details = array();
        $this->log->addInfo("Get details in module_containers for container no ".$containerId);
        $stmt = $this->conn->prepare("SELECT m.app_id, m.name, m.type FROM module_containers as m WHERE id=?");
        $stmt->bind_param("i", $containerId);
        if ($stmt->execute()) {
            $stmt->bind_result($appId, $name, $type);
            $stmt->fetch();
            $details["appId"] = $appId;
            $details["name"] = $name;
            $details["type"] = $type;
            $stmt->close();
        } else return null;
        if ($details["type"] =="media"){
            $this->log->addInfo("Get list of media modules");
            $modules = array();
            $stmt1 = $this->conn->prepare("SELECT c.id, c.name, c.content_type FROM content_sharing_module as c WHERE container_id=?");
            $stmt1->bind_param("i", $containerId);
            if ($stmt1->execute()) {
                $stmt1->bind_result($moduleId, $moduleName, $content_type);
                while ($stmt1->fetch()){
                    $module = array();
                    $module["id"]= $moduleId;
                    $module["name"] = $moduleName;
                    $module["content_type"]=$content_type;
                    $modules[] = $module;
                }
                $stmt1->close();
                $details["modules"] = $modules;
            } else return null;
        }
        //TODO for other type
        return $details;


    }

    public function updateContainer($containerId, $newName){
        $stmt = $this->conn->prepare("UPDATE module_containers SET name = ? WHERE id = ?");
        $stmt->bind_param("si", $newName, $containerId);
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            return false;
        }
    }

    public function deleteContainerMedia($containerId){
        $details = $this->getContainerDetails($containerId);
        $modules = $details["modules"];
        for ($i=0, $len=count($modules);$i<$len; $i++ ){
            $moduleId = $modules[$i]["id"];
            $this->deleteModuleMedia($moduleId);
        }
        $stmt = $this->conn->prepare("DELETE FROM module_containers WHERE id = ?");
        $stmt->bind_param("i", $containerId);
        if ($stmt->execute()) {
            $stmt->close();
            $this->log->addInfo("Deleted container number ".$containerId. " from content_sharing_module");
        } else {
            return false;
        }
        return true;
    }

    public function deleteContainer($containerId){ //TODO for testing other modules
        $stmt = $this->conn->prepare("DELETE FROM module_containers WHERE id = ?");
        $stmt->bind_param("i", $containerId);
        if ($stmt->execute()) {
            $stmt->close();
            $this->log->addInfo("Deleted container number ".$containerId. " from content_sharing_module");
        } else {
            return false;
        }
        return true;
    }

    public function deleteContainerPoll($containerId){
        $details = $this->getContainerDetails($containerId);
        $modules = $details["modules"];
        //TODO delete all module vote of that containers
        $stmt = $this->conn->prepare("DELETE FROM module_containers WHERE id = ?");
        $stmt->bind_param("i", $containerId);
        if ($stmt->execute()) {
            $stmt->close();
            $this->log->addInfo("Deleted container number ".$containerId. " from content_sharing_module");
        } else {
            return false;
        }
        return true;
    }

    public function getContainerType($containerId){
        $stmt = $this->conn->prepare("SELECT m.type FROM module_containers as m WHERE id=?");
        $stmt->bind_param("i", $containerId);
        if ($stmt->execute()) {
            $stmt->bind_result($type);
            $stmt->fetch();
            $stmt->close();
            return $type;
        } else return null;
    }

   // =========Module VOTE=========

        public function getListIdVoteModule($container_id){
        $this->log->addInfo("WOW Get list of vote id of container ". $container_id);
        $stmt = $this->conn->prepare("SELECT v.title,v.description FROM vote_module as v WHERE container_id =?");
        $stmt->bind_param("i",$container_id);
        $listsId = array();
        if($stmt->execute()){
            $stmt->bind_result($title,$description);
            while($stmt->fetch()){
                $listId = array();
                $this->log->addInfo("The title is". $title);
                $listId["title"]=$title;
                $listId["description"]=$description;
                $listsId[]=$listId;
            }
            $stmt->close();
            return $listsId;
        }
        return null;
        }


        public function createVote($title,$description,$container_id){
            $this->log->addInfo("creating a vote");
            $stmt = $this->conn->prepare("INSERT INTO vote_module (title,description,container_id) VALUES (?,?,?)");
            $stmt->bind_param('ssi',$title,$description,$container_id);
            if ($stmt->execute()) {
                $voteId = $stmt->insert_id;
                $this->log->addInfo("vote created");
                $stmt->close();
            }else {
                return NULL;
            }
            return $voteId;
        }

        public function updateVote($id,$title,$description){
            $stmt = $this->conn->prepare("UPDATE vote_module SET title=?,description=? WHERE id=?");
            $stmt->bind_param("ssi",$title,$description,$id);

            if($stmt->execute()){
                $this->log->addInfo("WOW". $id);
                $stmt->close();

            }else {
                return NULL;
            }

            return $id;
        }

        public function deleteVoteModule($id){
            $stmt = $this->conn->prepare("DELETE FROM vote_module WHERE id =?");
            $stmt->bind_param("i",$id);
            if($stmt->execute()){
                $stmt->close();
                $this->log->addInfo("Deleted vote");
                return TRUE;
            }
            $this->log->addInfo("Failed to delete");
            return FALSE;
        }

}

?>