<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
if (PHP_SAPI == 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}
require __DIR__ . '/../vendor/autoload.php';
date_default_timezone_set('UTC');

//session_start();
$config['displayErrorDetails'] = true; //get informaton about error
$config['addContentLengthHeader'] = false;
/*$config['db']['host']   = "localhost";
$config['db']['user']   = "dty-orange";
$config['db']['pass']   = "dty";
$config['db']['dbname'] = "appOrange";*/

// Instantiate the app
$settings = require __DIR__ . '/../src/settings.php';
$app = new \Slim\App(["settings"=>$config]);

// Set up dependencies
//require __DIR__ . '/../src/dependencies.php';

// Register middleware
//require __DIR__ . '/../src/middleware.php';

// Register routes
//require __DIR__ . '/../src/routes.php';
require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/Constants.php';
require __DIR__ . '/../src/DbConnect.php';
require __DIR__ . '/../src/DbHandler.php';
require __DIR__ . '/../src/FileHandler.php';
require __DIR__ . '/../src/PassHash.php';
require __DIR__ . '/../src/tokenHandler.php';



//=============Dependency Injection Container============
//=======================================================
$container = $app->getContainer(); //this gathers and holds all the dependencies

//Use Monolog to do logging
$container['log'] = function($c) {
    //set up the dependency when it is called for the first time
    $logger = new \Monolog\Logger('Main');
    $logger->pushHandler(new \Monolog\Handler\StreamHandler("../logs/app.log"));
    return $logger;
};

$container['fileLog'] = function($c) {
    //set up the dependency when it is called for the first time
    $logger = new \Monolog\Logger('FileHandler');
    $logger->pushHandler(new \Monolog\Handler\StreamHandler("../logs/app.log"));//log all error to a file called logs/app.log
    return $logger;
};

$container['dbLog'] = function($c) {
    //set up the dependency when it is called for the first time
    $logger = new \Monolog\Logger('DbHandler');
    $logger->pushHandler(new \Monolog\Handler\StreamHandler("../logs/app.log"));//log all error to a file called logs/app.log
    return $logger;
};


//===========Helper functions=================


//==========================ROUTES=========================
//=========================================================

$app->get('/', function (Request $request, Response $response) {
    return $response->withHeader(200, "hello");
});

$app->get('/hello/{name}', function (Request $request, Response $response) {
    $name = $request->getAttribute('name');
    $message = "Hello, $name";
    $data=array();
    $data['message']= $message;
    $this->log->addInfo("Hello world is called");
    $response = $response->withJson($data);
    return $response;
});

$app->get('/user', function(Request $req, Response $res){
    $this->log->addInfo("/user headers ". implode(",", $req->getHeader('Authorization')));
    $jwt = extractTokenFromHeader($req);
    $this->log->addInfo("jwt ". $jwt);
    if ($jwt){
        $userId = getUserIdFromToken($jwt);

        if (!$userId){
            $res = $res->withStatus(400, 'Bad request');
        }
        $db = new DbHandler($this->dbLog);
        $user = $db->getUserById($userId);
        $res = $res->withJson($user);
        $this->log->addInfo("User's info sent");
        return $res;
    } else {
        $res = $res->withStatus(400, 'Request without authorization header');
        $this->log->addInfo("Request user's info without authorization header");
        return $res;
    }

});

$app->get('/user/tel/{tel}', function(Request $req, Response $res){
    $tel = $req->getAttribute('tel');
    $db = new DbHandler($this->dbLog);
    $user = $db->getUserByTel($tel);
    $res->withHeader('Content-Type', 'application/json')->withJson($user);
});

//==========Authentication=============
$app->post('/signin', function(Request $req, Response $res){
    $this->log->addInfo("User sign in ");
    $data = $req->getParsedBody();
    $tel = filter_var($data['tel'], FILTER_SANITIZE_STRING);
    $password = filter_var($data['password'], FILTER_SANITIZE_STRING);
    $db = new DbHandler($this->dbLog);
    $auth = $db->signIn($tel,$password);
    if ( $auth ==AUTHENTICATE_SUCCESS){
        $id = $db->getUserByTel($tel)['id'];
        $auth = array();
        $auth['auth']='success';
        $this->log->addInfo("Create and add token to user with id ".$id);
        $token = createToken($id);
        $auth['token']= $token;
        /*if ($db->updateToken($id,$token)){
            $res = $res->withJson($auth);
            $this->log->addInfo("Token sent. Finish sign in");
        } else {
            $res = $res->withHeader(500, 'Fail to write token to database');
        }*/
        return $res = $res->withJson($auth);
    } else if ($auth ==WRONG_PASSWORD){
        return $res->withHeader(401, 'Wrong password');
    } else if($auth==USER_NON_EXIST){
        return $res->withHeader(400, 'User non exist');
    }

});

$app->post('/signup', function(Request $req, Response $res){
    $this->log->addInfo("User sign up ");
    //get params
    $data = $req->getParsedBody();
    $firstName = filter_var($data['firstName'], FILTER_SANITIZE_STRING);
    $lastName = filter_var($data['lastName'], FILTER_SANITIZE_STRING);
    $tel = filter_var($data['tel'], FILTER_SANITIZE_STRING);
    $password = filter_var($data['password'], FILTER_SANITIZE_STRING);

    $db = new DbHandler($this->dbLog);
    $reg = $db->signUp($firstName,$lastName,$tel,$password);
    if ( $reg== USER_CREATED_SUCCESSFULLY){
        $this->log->addInfo("Create user succeed");
        $id = $db->getUserByTel($tel)['id'];
        $auth = array();
        $auth['auth']='success';
        $this->log->addInfo("Create and add token to user with id ".$id);
        $token = createToken($id);
        $auth['token']= $token;
        /*if ($db->updateToken($id,$token)){
            $res = $res->withJson($auth);
            $this->log->addInfo("Token sent. finish sign up");
        } else {
            $res = $res->withHeader(500, 'Fail to write token to database');
        }*/
        $res = $res->withJson($auth);
    } else if ($reg ==USER_ALREADY_EXISTED){
        $res = $res->withHeader(400, 'User already existed');
    } else if($reg == USER_CREATE_FAILED){
        $res = $res->withHeader(500, 'Fail to create user');
    }
    return $res;
});

$app->get('/logout', function(Request $req, Response $res){});

//===========Upload and get media=======



$app->get('/getMedia', function (Request $req, Response $res){
    $id = $req->getQueryParam("id");
    $db = new DbHandler($this->dbLog);
    $path = $db->getMediaPath($id);
    $this->log->addInfo("/getMedia sending media from path ".$path);
    $image=file_get_contents($path);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $res = $res->write($image);
    $res = $res->withHeader('Content-Type', $finfo->buffer($image));
    echo $image;
    return $res;
});

//============Global app action=========

$app->post('/createApp', function(Request $req, Response $res){
    $this->log->addInfo("/createApp is called");
    $db = new DbHandler($this->dbLog);
    $jwt = extractTokenFromHeader($req);
    $creatorId = getUserIdFromToken($jwt);
    $name = $_POST["name"];
    $category = $_POST["category"];
    $font = $_POST['font'];
    $theme = $_POST['theme'];
    $layout = $_POST['layout'];
    $description = $_POST["description"];
    $appId = $db->createAppWithoutMedia($name,$creatorId,$category,$font,$theme,$layout,$description);
    $db->addAdminToApp($appId, $creatorId);
    $this->log->addInfo("Created app without media");
    $fileHandler = new FileHandler($this->fileLog);
    if ($_POST["icon"]=="default"){
        $iconPath = DEFAULT_ICON_PATH;
    } else {
        $iconPath = $fileHandler->saveImageToApp($appId,"iconFile");
    }
    $db->saveFileToApp($appId, $iconPath, "image", "icon");
    $this->log->addInfo("saved icon to ". $iconPath);
    if ($_POST["background"]=="default"){
        $backgroundPath = DEFAULT_BACKGROUND_PATH;
    } else {
        $backgroundPath = $fileHandler->saveImageToApp($appId, "backgroundFile");
    }
    $db->saveFileToApp($appId, $backgroundPath,"image", "background");
    $this->log->addInfo("Saved background to ".$backgroundPath);
    return $res->withJson(array("appId"=>$appId));
});


$app->get('/deleteApp', function(Request $req, Response $res){

});

$app->get('/loadApp', function(Request $req, Response $res){
    $appId = $req->getQueryParam("appId");
    $this->log->addInfo("loading App ".$appId);
    $db = new DbHandler($this->dbLog);
    $app =$db->getApp($appId);
    return $res->withJson($app);
});

//=============Inside app action========
/*$app->get('/app/addUser',function(Request $req, Response $res){
    $userId = $req->getQueryParam('userId');
    $appId = $req->getQueryParam('appId');
    $this->log->addInfo("add user ".$userId." to app ".$appId);
    $db = new DbHandler($this->dbLog);
    if ($db->addUserToApp($appId, $userId)){
        $res->withStatus(200, "action success");
    } else {
        $res->withStatus(500, "action fail");
    }
    return $res;
});*/

$app->get('/app/addUser',function(Request $req, Response $res) {
    $tel = $req->getQueryParam('tel');
    $appId = $req->getQueryParam('appId');
    $db = new DbHandler($this->dbLog);
    $userId = $db->getUserIdByTel($tel);
    if ($userId) {
        $this->log->addInfo("add user ".$userId." to app ".$appId);
        if ($db->addUserToApp($appId, $userId)){
            $res = $res->withStatus(200, "Action success");
        } else {
            $res = $res->withStatus(500, "Action fail");
        }
    } else {
        $res = $res->withStatus(400, "Tel not found");
    }
    return $res;
});

$app->get('/app/removeUser',function(Request $req, Response $res){
    $userId = $req->getQueryParam('userId');
    $appId = $req->getQueryParam('appId');
    $this->log->addInfo("remove user ".$userId." from app ".$appId);
    $db = new DbHandler($this->dbLog);
    if ($db->removeUserFromApp($appId, $userId)){
        $res->withStatus(200, "action success");
    } else {
        $res->withStatus(500, "action fail");
    }
    return $res;

});

$app->get('/app/createContainer', function(Request $req, Response $res){
    $appId = $req->getQueryParam("appId",0);
    $name = $req->getQueryParam("name","");
    $type = $req->getQueryParam("type","");
    $db = new DbHandler($this->dbLog);
    $containerId = $db->createContainer($appId, $name, $type);
    if ($containerId){
        return $res->withJson(array("containerId"=>$containerId));
    }
    return $res->withHeader(401);

});

$app->get('/app/removeContainer', function(Request $req, Response $res){

});

$app->get('/app/addModule', function(Request $req, Response $res){

});

$app->get('/app/removeModule', function(Request $req, Response $res){

});

$app->post('/app/upload', function(Request $req, Response $res){
    $this->log->addInfo("Uploading file");
    $appId = $_POST["appId"];
    $type = $_POST["type"];
    $fileHandler  = new FileHandler($this->fileLog);
    $filePath = $fileHandler->saveImageToApp($appId, "fileToUpload");
    echo $filePath;
    //TODO update icon or background in table apps
    return $res->withJson(["path"=>$filePath]);
});


//==========Container====================
$app->get('/container/addModule/media', function(Request $req, Response $res){
    $containerId = $req->getQueryParam("containerId",0);
    $name = $req->getQueryParam("name","");
    $type=$req->getQueryParam("type","");
    $db = new DbHandler($this->dbLog);
    $moduleId = $db->addModuleMedia($containerId, $name, $type);
    if($moduleId){
        return $res->withJson(["id"=>$moduleId]);
    }
    return $res->withHeader(401);
});

$app->get('/container/loadDetails', function(Request $req, Response $res){
    $containerId = $req->getQueryParam("containerId",0);
    $this->log->addInfo("load Details for container ".$containerId);
    $db = new DbHandler($this->dbLog);
    $containerDetails = $db->getContainerDetails($containerId);
    if($containerDetails){
        return $res->withJson($containerDetails);
    } else {
        return $res->withHeader(401);
    }
});

$app->post('/container/update', function(Request $req, response $res){
    $containerId = $_POST["containerId"];
    $newName = $_POST["newName"];
    $this->log->addInfo("Update Details for container ".$containerId);
    $db = new DbHandler($this->dbLog);
    $done = $db->updateContainer($containerId, $newName);
    if($done){
        return $res->withHeader(200, "name updated to ". $newName);
    } else {
        return $res->withHeader(401);
    }
});

$app->get('/container/delete', function (Request $req, Response $res){
    $containerId = $req->getQueryParam("containerId", 0);
    $db = new DbHandler($this->dbLog);
    $type = $db->getContainerType($containerId);
    if ($type=="media"){
        $this->log->addInfo("Delete container number ".$containerId);
        if ($db->deleteContainerMedia($containerId)){
            $this->log->addInfo("Delete container number ".$containerId. " done");
            return $res = $res->withHeader(200, "delete success");
        }
        return $res->withHeader(500);
    } else if ($type=="poll"){
        $db->deleteContainerPoll($containerId);
        return $res = $res->withHeader(200, "delete success");
    } else {
        $db->deleteContainer($containerId);
        return $res = $res->withHeader(200, "delete success");
    }
});

//==========Module media sharing=========

$app->post('/module/media/upload', function(Request $req, Response $res){
    $this->log->addInfo("Uploading file");
    $moduleId = $_POST["moduleId"];
    $type = $_POST["type"];
    $fileHandler  = new FileHandler($this->fileLog);
    $filePath="";
    if ($type=="image"){
        $filePath = $fileHandler->saveImageToModule($moduleId, "fileToUpload");
    } else if($type=="video"){
        $filePath = $fileHandler->saveVideoToModule($moduleId, "fileToUpload"); //TODO create in file Handler
    }
    echo $filePath;
    if ($filePath!=""){
        $db = new DbHandler($this->dbLog);
        $id = $db->saveFileToModule($moduleId, $filePath, $type);
        return $res->withStatus(200, "Image added to module")->withJson(["id"=>$id]);
    }
    return $res->withStatus(500);
});

$app->get('/module/media/load', function(Request $req, Response $res){
    $id = $req->getQueryParams("id")["id"];
    $db = new DbHandler($this->dbLog);
    $listId = $db->getListIdFromModule($id);
    return $res->withJson($listId);
});

$app->post('/module/media/update', function(Request $req, Response $res){
    $moduleId = $_POST["moduleId"];
    $newName = $_POST["newName"];
    $db = new DbHandler($this->dbLog);
    if ($db->updateModuleMedia($moduleId, $newName)){
        return $res->withHeader(200, "update success");
    }
    return $res->withHeader(500);

});

$app->get('/module/media/deleteModule', function (Request $req, Response $res){
    $moduleId = $req->getQueryParam("moduleId", 0);
    $this->log->addInfo("Delete module number ".$moduleId);
    $db = new DbHandler($this->dbLog);
    $fh = new FileHandler($this->fileLog);
    if ($db->deleteModuleMedia($moduleId)){
        $this->log->addInfo("Will delete folder res of module number ".$moduleId);
        $fh->deleteDir(RES_MODULE_PATH.$moduleId); //Delete resources
        $this->log->addInfo("Delete module number ".$moduleId. " done");
        return $res = $res->withHeader(200, "delete success");
    }
    return $res->withHeader(500);
});

$app->get('/module/media/deleteFile', function (Request $req, Response $res){
    $id = $req->getQueryParam("id", 0);
    $this->log->addInfo("Delete file with id ". $id);
    $db = new DbHandler($this->dbLog);
    $fh = new FileHandler($this->fileLog);
    $path= $db->getMediaPath($id);
    if (!$fh->deleteFile($path)){return $res->withHeader(500); }//Delete resources
    if ($db->deleteFile($id)){
        $this->log->addInfo("Delete file with id ".$id. " done");
        return $res = $res->withHeader(200, "delete success");
    }
    return $res->withHeader(500);
});

//==========Module VOTE=========

$app->get('/module/vote/getVote', function(Request $req, Response $res){
    $containerId = $req->getQueryParam("containerId",0);
    $db = new DbHandler($this->dbLog);
    $listId = $db->getListIdVoteModule($containerId);
    if($listId){
        return $res->withJson($listId);
    }else{
        return $res->withHeader(401, "No vote found");
    }

});

$app->post('/module/vote/addVote', function(Request $req, Response $res){
    $this->log->addInfo("/addVote is called");
    $db = new DbHandler($this->dbLog);
    $title = $_POST["title"];
    $description = $_POST["description"];
    $container_id = $_POST["container_id"];
    //$start_date= $_POST["start_date"];
    //$end_date= $_POST["end_date"];
    $voteId = $db->createVote($title,$description,$container_id);
    if($voteId){
        $this->log->addInfo("Created vote");
        return $res->withJson(["voteId"=>$voteId]);
    }
    return $res->withStatus(500);
});

$app->post('/module/vote/uploadVote', function(Request $req, Response $res){
    $this->log->addInfo("uploadVote is called");
    $db = new DbHandler($this->dbLog);
    $id = $_POST["id"];
    $title = $_POST["title"];
    $description = $_POST["description"];
    if($db->updateVote($id,$title,$description)){
        $this->log->addInfo("Updated vote");
        return $res->withJson(["voteIdUpdated"=>$id]);
    }
    return $res->withStatus(404);
});

$app->get('/module/vote/deleteVote', function(Request $req, Response $res){
    $this->log->addInfo("deleteVote is called");
    $voteId = $req->getQueryParam("voteId",0);
    $db = new DbHandler($this->dbLog);
    if($db->deleteVoteModule($voteId)){
        $this->log->addInfo("Deleted vote");
        return $res->withHeader(200, "Deleted");
    }
    return $res->withHeader(404, "Not FOUND");
});


$app->run();


