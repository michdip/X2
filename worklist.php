<?php

require_once 'conf.d/base.conf';
require_once( ROOT_DIR . '/lib/class/mySmarty.class.php' );
require_once( ROOT_DIR . '/lib/class/permission.class.php' );
require_once( ROOT_DIR . '/lib/class/dbLink.class.php' );
require_once( ROOT_DIR . '/lib/class/pageID.class.php' );
require_once( ROOT_DIR . '/lib/class/templateFunctions.class.php' );
require_once( ROOT_DIR . '/lib/class/jobFunctions.class.php' );
require_once( ROOT_DIR . '/lib/class/description.class.php' );
require_once( ROOT_DIR . '/lib/class/modulFunctions.class.php' );
require_once( ROOT_DIR . '/lib/class/jobState.class.php' );
require_once( ROOT_DIR . '/lib/class/workFunctions.class.php' );

function prepareEdit( $db, $get, &$smarty, &$css )
{
    $eJob = workFunctions::getWorkJob4Edit( $db, $get['edit'] );

    // den Job durch das Modul anreichern
    $modul = modulFunctions::getModulInstance( $eJob['JOB_TYPE'] );
    $modul->getWorkJob4Edit( $db, $get, $eJob, $smarty );

    $smarty->assign( 'edit', true );
    $smarty->assign( 'eJob', $eJob );
    $smarty->assign( 'MODULES', MODULES );
    $smarty->assign( 'jobTemplate', MODULES[ $eJob['JOB_TYPE'] ]['tplFile'] );

    array_push( $css, 'dialog.css' );
}

$db = new dbMysql( 'X2', false );
$smarty = new mySmarty( );
$perm = new permission( );

// die Session prüfen
if( $perm->isValidSession( ))
{
    // arrays für die Includes im HTML
    $css = array( 'basic.css', 'icons.css' );
    $js = array( );

    // JOB_STATES laden
    jobState::getJobStates( $db );
    jobState::getReverseJobStates( $db );

    // Das Template auslesen
    if( isset( $_GET['wid' ] ))
        $exeID = $_GET['wid' ];

    else if( isset( $_POST['exeID'] ))
        $exeID = $_POST['exeID'];

    // die Informationen des Templates laden
    $template = templateFunctions::getTemplate2GraphByWID( $db, $exeID );

    $smarty->assign( 'template', $template );
    $smarty->assign( 'exeID', $exeID );

    // wurde die pageID verwendet && ist das Template editierbar
    if(( isset( $_POST['pageID'] ) && pageID::usePageID( $db, $_POST['pageID'], $perm->getMyUserName( ))) ||
        ( isset( $_GET['pageID'] ) && pageID::usePageID( $db, $_GET['pageID'], $perm->getMyUserName( ))))
    {
        // OK By User
        if( isset( $_GET['setOk'] ) &&
            $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $template['OID'], PERM_EXE ))
            workFunctions::setOkByUser( $db, $exeID, $_GET['setOk'], $perm->getMyUserName( ));

        // OK By Admin setzen
        else if( isset( $_GET['setAOk'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $template['OID'], PERM_ADMIN ))
            workFunctions::setOkByUser( $db, $exeID, $_GET['setAOk'], $perm->getMyUserName( ), true );

        // einen Breakpoint setzen
        else if( isset( $_GET['setBp'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $template['OID'], PERM_EXE ))
            workFunctions::setBreakpoint( $db, $exeID, $_GET['setBp'], $perm->getMyUserName( ), 1 );

        // einen Breakpoint entfernen
        else if( isset( $_GET['unsetBp'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $template['OID'], PERM_EXE ))
            workFunctions::setBreakpoint( $db, $exeID, $_GET['unsetBp'], $perm->getMyUserName( ), null );

        // Ok By User Gruppe
        else if( isset( $_GET['setOkG'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $template['OID'], PERM_EXE ))
            workFunctions::setOkByUserGroup( $db, $exeID, $_GET['setOkG'], $_GET['state'], $perm->getMyUserName( ));

        // einen Breakpoint für eine Gruppe setzen
        else if( isset( $_GET['setBpG'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $template['OID'], PERM_EXE ))
            workFunctions::setBreakpointGroup( $db, $exeID, $_GET['setBpG'], $perm->getMyUserName( ), 1 );

        // einen Breakpoint für eine Gruppe entfernen
        else if( isset( $_GET['unsetBpG'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $template['OID'], PERM_EXE ))
            workFunctions::setBreakpointGroup( $db, $exeID, $_GET['unsetBpG'], $perm->getMyUserName( ), null );

        // Retry
        else if( isset( $_GET['restart'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $template['OID'], PERM_EXE ))
            workFunctions::retryWorkJob( $db, $exeID, $_GET['restart'], $perm->getMyUserName( ));

        // einen Folgejob erstellen
        else if( isset( $_GET['newJob'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $template['OID'], PERM_WRITE ))
            workFunctions::createWorkJob( $db, $exeID, $_GET['newJob'], $perm->getMyUserName( ));

        // einen Job löschen
        else if( isset( $_GET['remJob'] ) &&
            $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $template['OID'], PERM_WRITE ))
            workFunctions::deleteWorkJob( $db, $exeID, $_GET['remJob'], $perm->getMyUserName( ));

        // einen Job bearbeiten
        else if( isset( $_GET['edit'] ) &&
            $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $template['OID'], PERM_WRITE ))
            prepareEdit( $db, $_GET, $smarty, $css );

        // einen Link erstellen
        else if( isset( $_GET['link'] ) && isset( $_GET['linkTo'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $template['OID'], PERM_WRITE ))
            workFunctions::linkWorkJob( $db, $exeID, $_GET['link'], $_GET['linkTo'], $perm->getMyUserName( ));

        // einen Link löschen
        else if( isset( $_GET['remLink'] ) && isset( $_GET['linkTo'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $template['OID'], PERM_WRITE ))
            workFunctions::unlinkWorkJob( $db, $exeID, $_GET['remLink'], $_GET['linkTo'], $perm->getMyUserName( ));

        // den Typen des Jobs wandeln
        else if( isset( $_POST['changeJobType'] ) && isset( $_POST['currentJobType'] ) &&
                 $_POST['changeJobType'] != $_POST['currentJobType'] &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $template['OID'], PERM_WRITE ))
        {
            workFunctions::changeWorkJobType( $db, $exeID, $_POST['jobID'], $_POST['changeJobType'], $perm->getMyUserName( ));
            prepareEdit( $db,
                         array( 'edit' => $_POST['jobID'],
                                'wid'  => $_POST['exeID'] ),
                         $smarty,
                         $css );
        }

        // Antwort dem Modul zur Verfügung stellen
        else if( isset( $_POST['jobID'] ) &&
            $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $template['OID'], PERM_WRITE ))
        {
            $eJob = workFunctions::getWorkJob4Edit( $db, $_POST['jobID'] );

            // ggf den Namen des Jobs ändern
            if( isset( $_POST['jobName'] ) && $_POST['jobName'] != '' && $_POST['jobName'] != $eJob['JOB_NAME'] )
                workFunctions::renameWorkJob( $db, $exeID, $_POST['jobID'], $_POST['jobName'], $perm->getMyUserName( ));

            $modul = modulFunctions::getModulInstance( $eJob['JOB_TYPE'] );

            $get = $modul->processWorkJobChanges( $db, $_POST, $perm->getMyUserName( ));

            if( count( $get ) > 0 )
                prepareEdit( $db, $get, $smarty, $css );
        }

        // GET für das Modul
        if( isset( $_GET['getModul'] ) &&
            $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $template['OID'], PERM_EXE ))
        {
            $eJob = workFunctions::getWorkJob4Edit( $db, $_GET['getModul'] );

            $modul = modulFunctions::getModulInstance( $eJob['JOB_TYPE'] );

            $_GET['exeID'] = $_GET['wid'];
            $get = $modul->processWorkJobChanges( $db, $_GET, $perm->getMyUserName( ));

            if( count( $get ) > 0 )
                prepareEdit( $db, $get, $smarty, $css );
        }
    }

    // eine pageID holen
    $pageID = pageID::getMyPageID( $db, $perm->getMyUserName( ));
    $smarty->assign( 'pageID', $pageID );

    // _GET weitergeben
    $imgaeGet = 'wid=' . $exeID . '&pageID=' . $pageID . '&tid=' . $template['OID'] . '&tStat=' . $template['STATE'];

    if( isset( $_GET['showRT'] ))
        $imgaeGet .= '&showRT=' . $_GET['showRT'];

    if( isset( $_GET['showERT'] ))
        $imgaeGet .= '&showERT=' . $_GET['showERT'];

    if( isset( $_GET['ungroup'] ))
        $imgaeGet .= '&ungroup=' . $_GET['ungroup'];

    $smarty->assign( 'get', $imgaeGet );

    // copyToCliboard aufnehmen
    array_push( $js, 'copyToClipboard.php?bo=wl&oid=' . $exeID );

    // die CSS laden
    $smarty->assign( 'ROOT_URL', ROOT_URL );
    $smarty->assign( 'css', $css );
    $smarty->assign( 'js', $js );

    $smarty->display( 'worklist.tpl' );
}
else
{
    $smarty->display( 'noSession.tpl' );
}

?>
