<?php

    class dbResult
    {
        public $resultset;
        public $affectedRows;
        public $numRows;
        public $autoincrement;

        public function __construct( )
        {
            $this->resultset = array( );
            $this->affectedRows = 0;
            $this->numRows = 0;
        }

        public function appendResult( $feldMap, $result )
        {
            $tmp = array( );

            foreach( $result as $key => $value )
                $tmp[ $feldMap[ $key ]] = $value;

            $this->resultset[ $this->numRows ] = $tmp;
            $this->numRows++;
        }

        public function setResult( $result )
        {
            $this->resultset = $result;
            $this->numRows = count( $this->resultset );
        }
    }

?>
