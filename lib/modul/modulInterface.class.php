<?php

    interface modulInterface
    {
        public function duplicateJob( $db, $tid, $origId, $newId, $user );
        public function duplicateWorkJob( $db, $tid, $origId, $newId, $user );
        public function deleteJob( $db, $oid );
        public function deleteWorkJob( $db, $oid );
        public function archiveWorkJob( $db, $wid );
        public function createWorkCopy( $db, $jid, $wid, $exeid, &$logger );
        public function exportJob( $db, $oid, &$mySelf, &$refMap );
        public function importJob( $db, $tid, $oid, $job, $user );
        public function getJobLabel( $db, $template, $oid, $writeable, $pageID );   // return ''
        public function getWorkJobLabel( $db, $oid );                               // return ''
        public function getJob4Edit( $db, $get, &$eJob, &$smarty );
        public function getWorkJob4Edit( $db, $get, &$eJob, &$smarty );
        public function processJobChanges( $db, $post, $user );                     // return array( )
        public function processWorkJobChanges( $db, $post, $user );                 // return array( )
        public function runJob( $db, $wid, &$logger );
        public function processDeamonMessages( $db, &$logger );
        public function finishWorkJob( $db, $wid, $message, &$logger );
    }

?>
