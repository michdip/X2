<?php

require_once( ROOT_DIR . '/conf.d/databases.conf' );
require_once( ROOT_DIR . '/lib/class/dbResult.class.php' );
require_once( ROOT_DIR . '/lib/class/dbMySQL.class.php' );
require_once( ROOT_DIR . '/lib/class/dbOracle.class.php' );

    interface dbLink
    {
        public function __construct( $dbName, $autocommit );
        public function __destruct();
        public function dbRequest( $query, $params, $throw );
        public function getAutocommitState();
        public function rollback();
        public function commit();
    }

?>
