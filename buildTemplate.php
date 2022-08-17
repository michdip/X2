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

function prepareEdit( $db, $get, &$smarty, &$css )
{
    $eJob = jobFunctions::getJob4Edit( $db, $get['edit'] );
            
    // den Job durch das Modul anreichern
    $modul = modulFunctions::getModulInstance( $eJob['JOB_TYPE'] );
    $modul->getJob4Edit( $db, $get, $eJob, $smarty );
                 
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

    // Das Template auslesen
    if( isset( $_GET['tid' ] ))
        $template = $_GET['tid' ];

    else if( isset( $_POST['templateID' ] ))
        $template = $_POST['templateID' ];

    // varsOnly setzen
    if( isset( $_GET['varsOnly'] ))
        $smarty->assign( 'varsOnly', $_GET['varsOnly'] );

    else if( isset( $_POST['varsOnly'] ))
        $smarty->assign( 'varsOnly', $_POST['varsOnly'] );

    else
        $smarty->assign( 'varsOnly', 0 );

    // wurde die pageID verwendet && ist das Template editierbar
    if((( isset( $_POST['pageID'] ) && pageID::usePageID( $db, $_POST['pageID'], $perm->getMyUserName( ))) ||
        ( isset( $_GET['pageID'] ) && pageID::usePageID( $db, $_GET['pageID'], $perm->getMyUserName( ))))
      )
    {
        // Variablen sind immer bearbeitbar
        // eine Variable löschen
        if( isset( $_GET['remVar'] ) &&
            $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['tid' ], PERM_EXE ))
            templateFunctions::removeVar( $db, $_GET['remVar' ], $perm->getMyUserName( ));

        // die Variablen verändern
        else if(( isset( $_POST['maxVars'] ) || isset( $_POST['varValue_0'] )) && 
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_POST['templateID' ], PERM_EXE ))
            templateFunctions::editVars( $db, $_POST, $perm->getMyUserName( ));

        // Variablen zum Editieren öffnen
        else if( isset( $_GET['editVars'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['tid' ], PERM_EXE ))
        {
            $smarty->assign( 'editVars', true );
            $smarty->assign( 'tplVars' , templateFunctions::getVariables( $db, $_GET['tid' ] ));

            array_push( $css, 'dialog.css' );
        }

        // sonst muss das Template editierbar sein
        else if( !isset( $_GET['varsOnly'] ) || $_GET['varsOnly'] == 0 )
        {
            if( templateFunctions::isEditable( $db, $template ))
            {
                // einen neuen Job erstellen
                if( isset( $_GET['newJob'] ) &&
                    $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['tid' ], PERM_WRITE ))
                    jobFunctions::createJob( $db, $_GET['tid' ], $_GET['newJob'], $perm->getMyUserName( ));

                // einen Job löschen
                else if( isset( $_GET['remove'] ) &&
                         $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['tid' ], PERM_WRITE ))
                    jobFunctions::removeJob( $db, $_GET['remove'], $perm->getMyUserName( ));

                // einen Breakpoint setzen
                else if( isset( $_GET['setBp'] ) &&
                         $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['tid' ], PERM_WRITE ))
                    jobFunctions::setBreakpoint( $db, $_GET['setBp'], $perm->getMyUserName( ), 1 );

                // einen Breakpoint entfernen
                else if( isset( $_GET['unsetBp'] ) &&
                         $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['tid' ], PERM_WRITE ))
                    jobFunctions::setBreakpoint( $db, $_GET['unsetBp'], $perm->getMyUserName( ), null );

                // einen Link erstellen
                else if( isset( $_GET['link'] ) && isset( $_GET['linkTo'] ) &&
                         $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['tid' ], PERM_WRITE ))
                    jobFunctions::linkJob( $db, $_GET['linkTo'], $_GET['link'], $perm->getMyUserName( ));

                // einen Link löschen
                else if( isset( $_GET['remlink'] ) && isset( $_GET['linkTo'] ) &&
                         $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['tid' ], PERM_WRITE ))
                    jobFunctions::unlinkJob( $db, $_GET['linkTo'], $_GET['remlink'], $perm->getMyUserName( ));

                // einen Job zum editieren öffnen
                else if( isset( $_GET['edit'] ) && $_GET['edit'] != '' &&
                         $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['tid' ], PERM_WRITE ))
                    prepareEdit( $db, $_GET, $smarty, $css );

                // eine Beschreibung setzen
                else if( isset( $_POST['descOID'] ) &&
                         $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_POST['templateID' ], PERM_WRITE ))
                    description::setDescription( $db,
                                                 'JOB',
                                                 $_POST['descOID'],
                                                 $_POST['setOwner'],
                                                 $_POST['setDescription'],
                                                 $perm->getMyUserName( ));

                // eine Beschreibung zum Editieren öffnen
                else if( isset( $_GET['editDesc'] ) &&
                         $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['tid' ], PERM_WRITE ))
                {
                    $smarty->assign( 'showDesc', true );
                    $smarty->assign( 'descOTpe', 'JOB' );
                    $smarty->assign( 'descOID', $_GET['editDesc'] );
                    $smarty->assign( 'oldDesc', description::getDescription( $db, 'JOB', $_GET['editDesc'] ));

                    array_push( $css, 'dialog.css' );
                }

                // den Typen des Jobs wandeln
                else if( isset( $_POST['changeJobType'] ) && isset( $_POST['currentJobType'] ) &&
                         $_POST['changeJobType'] != $_POST['currentJobType'] &&
                         $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_POST['templateID' ], PERM_WRITE ))
                {
                    jobFunctions::changeJobType( $db, $_POST['jobID'], $_POST['changeJobType'], $perm->getMyUserName( ));
                    prepareEdit( $db, array( 'edit' => $_POST['jobID'] ), $smarty, $css );
                }

                // Antwort dem Modul zur Verfügung stellen
                else if( isset( $_POST['jobID'] ) &&
                         $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_POST['templateID' ], PERM_WRITE ))
                {
                    $eJob = jobFunctions::getJob4Edit( $db, $_POST['jobID'] );

                    // ggf den Namen des Jobs ändern
                    if( isset( $_POST['jobName'] ) && $_POST['jobName'] != '' && $_POST['jobName'] != $eJob['JOB_NAME'] )
                        jobFunctions::renameJob( $db, $_POST['jobID'], $_POST['jobName'], $perm->getMyUserName( ));

                    $modul = modulFunctions::getModulInstance( $eJob['JOB_TYPE'] );
                    $get = $modul->processJobChanges( $db, $_POST, $perm->getMyUserName( ));

                    if( count( $get ) > 0 )
                        prepareEdit( $db, $get, $smarty, $css );
                }

                // direkt-Link dem Modul zur Verfügung stellen
                else if( isset( $_GET['jobID'] ) &&
                         $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['tid' ], PERM_WRITE ))
                {
                    $eJob = jobFunctions::getJob4Edit( $db, $_GET['jobID'] );

                    $modul = modulFunctions::getModulInstance( $eJob['JOB_TYPE'] );
                    $modul->processJobChanges( $db, $_GET, $perm->getMyUserName( ));
                }
            }
            else
                print "Das Template ist nicht editierbar";
        }
    }

    // eine pageID holen
    $smarty->assign( 'pageID', pageID::getMyPageID( $db, $perm->getMyUserName( )));

    // die Informationen des Templates laden
    $smarty->assign( 'template', templateFunctions::getTemplate2Graph( $db, $template ));

    // copyToCliboard aufnehmen
    array_push( $js, 'copyToClipboard.php?bo=bt&oid=' . $template );

    // die CSS laden
    $smarty->assign( 'ROOT_URL', ROOT_URL );
    $smarty->assign( 'css', $css );
    $smarty->assign( 'js', $js );

    $smarty->display( 'buildTemplate.tpl' );
}
else
{
    $smarty->display( 'noSession.tpl' );
}

?>
