<?php

    class dbMySQL implements dbLink
    {
        private $dbLink;
        private $aktiv;

        // DB-Verbindungsaufbau
        public function __construct( $dbName, $autocommit )
        {
            $this->aktiv = false;
            $this->dbLink = new mysqli( DATABASES[ $dbName ]['DB_HOST'],
                                        DATABASES[ $dbName ]['DB_USER'],
                                        DATABASES[ $dbName ]['DB_PWD'],
                                        DATABASES[ $dbName ]['DB_NAME'] );

            if( mysqli_connect_errno())
                print mysqli_connect_errno();

            $this->dbLink->set_charset( "utf8" );
            $this->dbLink->autocommit( $autocommit );
            $this->aktiv = true;
        }

        // DB-Verbindung beenden
        public function __destruct()
        {
            if( $this->dbLink )
                $this->dbLink->close();
        }

        // DB-Anfrage
        public function dbRequest( $query, $params = null, $throw = false )
        {
            if( !$this->aktiv )
                return null;

            // Das Prepared-Statement erstellen
            $stmt = $this->dbLink->prepare( $query );

            /* die Variablen binden
             * Das Array muss im ersten Teil die Typen der Variablen besitzen, in den darauffolgenden Referenzen auf die Werte
             * Um Referenzen auf die Werte zu bekommen müssen diese vorher in Variablen abgelegt werden.
             * Beispiel 
             * $bind0 = 0
             * $bind1 = 'a'
             * array( [0] => 'is' [1] => &$bind0 [2] => &$bind1 )
             *
             * Die Eingabe erwartet die params als verschachteltes array
             * Beispiel array( array( 'i', 0 ), array( 's', 'a' ))
             */
            if( $params )
            {
                $binds = array( '' );

                for( $i = 0; $i < count( $params ); $i++ )
                {
                    $binds[0] .= $params[ $i ][0];

                    $bind_var_name = 'bind' . $i;
                    $$bind_var_name = $params[ $i ][1];                   // indirekte Addressierung

                    $binds[ $i + 1 ] = &$$bind_var_name;
                }

                // der Aufruf wird umgesetzt nach $stmt->bind_param( $bind0, $bind1, .... );
                call_user_func_array( array( $stmt, 'bind_param' ), $binds );
            }

            // Query ausführen
            $stmt->execute();

            // Fehler ausgeben
            if( $stmt->error )
            {
                $err = $query . "\n" . var_export( $params, true ) . "\n" . $stmt->error;

                if( $throw )
                    throw new Exception( $err );
                else
                    print $err;
            }

            /* die Meta-Daten des Ergebnisses Laden
             * danach stehen in den Meta-Daten die Anzahl der Spalten des Results
             */
            $meta = $stmt->result_metadata();

            // Ergebnis erstellen
            $result = new dbResult();

            // Wenn Spalten im Ergebnis sind, dann auswerten
            if( $meta )
            {
                /* Definition einer Zeile aufbauen
                 * diese wird als array von Spaltennamen auf Referenzen von Werten erstellt.
                 * In die Referenzen werden dann beim auslesen der Ergebniszeile die Werte geschrieben
                 *
                 * mit PHP8 werden alphanumerische Arrays sortiert. Daher darf das Ergebnis-Array nur noch numerische
                 * Arrays übergeben bekommen. Desshalb wird ein seperates Array mit dem Mapping zum Feldnamen erstellt
                 * das im Resultset ausgewertet wird
                 */
                $zeile = array();
                $feldMap = array();
                $spalte = 0;

                while( $feld = $meta->fetch_field())
                {
                    $feldName = $feld->name;

                    $zeile[ $spalte ] = &$$feldName;
                    $feldMap[ $spalte ] = $feldName;

                    $spalte++;
                }

                // Das Result in die Zeilendefinition binden
                // $stmt->bind_result( $FELD1, $FELD2, ... );
                call_user_func_array( array( $stmt, 'bind_result' ), $zeile );

                // jede Zeile aus dem Ergebnis auslesen und ins dbResult schreiben
                while( $stmt->fetch())
                    $result->appendResult( $feldMap, $zeile );
            }

            // affecetd Rows setzen
            $result->affectedRows = $stmt->affected_rows;

            // autoincrementid setzen
            $result->autoincrement = $this->dbLink->insert_id;

            // Query freigeben
            $stmt->close();

            return $result;
        }

        // den Autocommit-status abfragen
        public function getAutocommitState()
        {
            $state = $this->dbRequest( "select @@autocommit" );

            if( $state->resultset[0]['@@autocommit'] == 1 )
                return true;

            return false;
        }

        // rollback
        public function rollback()
        {
            $this->dbLink->rollback();
        }

        // commit
        public function commit()
        {
            $this->dbLink->commit();
        }
    }

?>
