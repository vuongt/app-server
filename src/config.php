<?php
/**
 *  configuration de la base des donnée
 */
define('DB_USERNAME', 'dty-orange');
define('DB_PASSWORD', 'dty');
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'appOrange');

define('USER_CREATED_SUCCESSFULLY', 0);
define('USER_CREATE_FAILED', 1);
define('USER_ALREADY_EXISTED', 2);
define('USER_NON_EXIST', 3);
define('WRONG_PASSWORD',4);
define('AUTHENTICATE_SUCCESS',5);
 define('SECRET_KEY_JWT', 'dty-orange');

define('TOKEN_EXPIRED',7*60*60*24); //7 days
?>