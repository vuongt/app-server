<?php
use Firebase\JWT\JWT;
include dirname(__FILE__) . '/config.php';
/**
 * Created by PhpStorm.
 * User: vuong
 * Date: 08/11/2016
 * Time: 09:48
 */

function extractTokenFromHeader($req){
    $authHeader = $req->getHeader('Authorization');
    if ($authHeader){
        //extract the jwt from the Bearer
        list($jwt)=sscanf($authHeader[0],'Authorization: Bearer %s');
        return $jwt;
    }
    return NULL;
}

function createToken($userId){
    //$tokenId    = base64_encode(mcrypt_create_iv(32));
    $issuedAt   = time();
    $notBefore  = $issuedAt;             //Adding 10 seconds
    $expire     = $notBefore + TOKEN_EXPIRED;            // Adding 7 day
    //Create the token as an array
    $data = [
        'iat'  => $issuedAt,         // Issued at: time when the token was generated
        //'jti'  => $tokenId,          // Json Token Id: an unique identifier for the token
        'nbf'  => $notBefore,        // the token is considered valid from this moment
        'exp'  => $expire,           // Expire
        'data' => [                  // Data related to the signer user
            'userId'   => $userId // userid from the users table
        ]
    ];

    /*
 * Extract the key, which is coming from the config file.
 *
 * Best suggestion is the key to be a binary string and
 * store it in encoded in a config file.
 *
 * Can be generated with base64_encode(openssl_random_pseudo_bytes(64));
 *
 * keep it secure! You'll need the exact key to verify the
 * token later.
 */
    $secretKey = SECRET_KEY_JWT; //TODO encoded key

    /*
 * Encode the array to a JWT string.
 * Second parameter is the key to encode the token.
 */
    $jwt = JWT::encode(
        $data,      //Data to be encoded in the JWT
        $secretKey // The signing key
        //'HS256'     // Algorithm used to sign the token
    );
    return $jwt;
}

/**
 * Verify then extract data
 * @param $jwt
 * @return null
 */
function getUserIdFromToken($jwt){
    //TODO decode the json web token secret ?
    $secretKey = SECRET_KEY_JWT;
    try{
        $token = JWT::decode($jwt, $secretKey, array('HS256'));
        return $token->data->userId;

    } catch (Error $e){
        //TODO error handler
        return NULL;
    }

}
?>