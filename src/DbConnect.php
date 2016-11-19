<?php

/**
 * Manage the connexion to database
 *
 */
class DbConnect {

    private $conn;

    function __construct() {
    }

    /**
     * établissement de la connexion
     * @return mysqli
     */
    function connect() {
        include_once dirname(__FILE__) . '/config.php';

        // Connexion à la base de données mysql
        $this->conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

        // Vérifiez les erreurs de connexion àla base de données
        if (mysqli_connect_errno()) {
            echo "Impossible de se connecter à MySQL: " . mysqli_connect_error();
        }

        //retourner la ressource de connexion
        return $this->conn;
    }

}

?>