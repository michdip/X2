<?php

    class dbOracle implements dbLink
    {
        private $dbLink;
        private $autocommit;

        private $commit_states = array( false => OCI_NO_AUTO_COMMIT,
                                        true  => OCI_COMMIT_ON_SUCCESS );

        private function throwError( $obj, $throw )
        {
            $err = oci_error( $obj );

            if( $throw )
                throw new Exception( $err['message'] );
            else
                print_r( $err );
        }

        // DB-Verbindungsaufbau
        public function __construct( $dbName, $autocommit )
        {
            $this->autocommit = $autocommit;
            $this->dbLink = oci_new_connect( DATABASES[ $dbName ]['DB_USER'],
                                             DATABASES[ $dbName ]['DB_PWD'],
                                             DATABASES[ $dbName ]['DB_HOST'],
                                             'AL32UTF8' );

            if( !$this->dbLink )
                print "Es konnte keine Verbindung zur Datenbank aufgebaut werden";
        }

        public function __destruct()
        {
            if( $this->dbLink )
                oci_close( $this->dbLink );
        }

        public function dbRequest( $query, $params = null, $throw = null )
        {
            if( !$this->dbLink )
                return null;

            // Statement parsen
            $stmt = oci_parse( $this->dbLink, $query );

            if( $stmt === false )
                $this->throwError( $this->dbLink, $throw );

            // binds setzen, wenn vorhanden
            if( count( $params ))
                foreach( $params as $key => &$value )
                    oci_bind_by_name( $stmt, $key, $value );

            // Query ausführen
            if( !oci_execute( $stmt, $this->commit_states[ $this->autocommit ] ))
                $this->throwError( $stmt, $throw );

            // Ergebnis aufbauen
            $result = new dbResult();

            if( oci_statement_type( $stmt ) == 'SELECT' )
            {
                $rows = array();

                if( oci_fetch_all( $stmt, $rows, null, null, OCI_FETCHSTATEMENT_BY_ROW ) === false )
                    $this->throwError( $stmt, $throw );

                $result->setResult( $rows );
            }

            // Statement freigeben
            oci_free_statement( $stmt );

            return $result;
        }

        public function getAutocommitState()
        {
            return $this->autocommit;
        }

        public function rollback()
        {
            oci_rollback( $this->dbLink );
        }

        public function commit()
        {
            oci_commit( $this->dbLink );
        }
    }

?>
