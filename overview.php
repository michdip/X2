<?php

require_once 'conf.d/base.conf';
require_once( ROOT_DIR . '/lib/class/mySmarty.class.php' );
require_once( ROOT_DIR . '/lib/class/permission.class.php' );
require_once( ROOT_DIR . '/lib/class/dbLink.class.php' );
require_once( ROOT_DIR . '/lib/class/pageID.class.php' );
require_once( ROOT_DIR . '/lib/class/viewOption.class.php' );
require_once( ROOT_DIR . '/lib/class/templateFunctions.class.php' );
require_once( ROOT_DIR . '/lib/class/description.class.php' );
require_once( ROOT_DIR . '/lib/class/maintenance.class.php' );
require_once( ROOT_DIR . '/lib/class/jobState.class.php' );

function prepareEditRights( $db, $tid, $perm, &$css, &$smarty )
{
    $smarty->assign( 'setRights', $tid );
    $smarty->assign( 'editable', $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $tid, PERM_WRITE ));
    $smarty->assign( 'allGroups', templateFunctions::getTemplatePermission( $db, $tid, $perm ));
    $smarty->assign( 'PERM_READ', PERM_READ );
    $smarty->assign( 'PERM_WRITE', PERM_WRITE );
    $smarty->assign( 'PERM_EXE', PERM_EXE );

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

    // meine Position im Baum festlegen
    $parentID = 0;

    // die JOB_STATES laden
    jobState::getJobStates( $db );
    jobState::getReverseJobStates( $db );

    if( isset( $_POST['parentID'] ))
        $parentID = $_POST['parentID'];

    else if( isset( $_GET['parentID'] ))
        $parentID = $_GET['parentID'];

    // wurde die pageID verwendet
    if(( isset( $_POST['pageID'] ) && pageID::usePageID( $db, $_POST['pageID'], $perm->getMyUserName( ))) ||
       ( isset( $_GET['pageID'] ) && pageID::usePageID( $db, $_GET['pageID'], $perm->getMyUserName( ))))
    {
        // ein Template manuell starten
        if( isset( $_GET['play'] ) &&
            $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['play'], PERM_EXE ))
            jobFunctions::start( $db, $_GET['play'], $perm->getMyUserName( ));

        // ein Template auswerfen
        else if( isset( $_GET['eject'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['eject'], PERM_EXE ))
            jobFunctions::ejectJobs( $db, $_GET['eject'], $perm->getMyUserName( ));

        // ein Template anhalten
        else if( isset( $_GET['pause'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['pause'], PERM_EXE ))
            templateFunctions::pause( $db, $_GET['pause'], $perm->getMyUserName( ));

        // ein Template fortführen
        else if( isset( $_GET['resume'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['resume'], PERM_EXE ))
            templateFunctions::resume( $db, $_GET['resume'], $perm->getMyUserName( ));

        // ein Template einschalten
        else if( isset( $_GET['powerOn'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['powerOn'], PERM_EXE ))
            templateFunctions::powerUp( $db, $_GET['powerOn'], $perm->getMyUserName( ));

        // ein Template ausschalten
        else if( isset( $_GET['powerOff'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['powerOff'], PERM_EXE ))
            templateFunctions::powerOff( $db, $_GET['powerOff'], $perm->getMyUserName( ));

        // ein Template schedulen
        else if( isset( $_GET['shedule'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['shedule'], PERM_EXE ))
            templateFunctions::shedule( $db, $_GET['shedule'], $perm->getMyUserName( ));

        // setzen von viewOption
        else if( isset( $_POST['setUserViewOption'] ))
            viewOption::setUserView( $db,  $perm->getMyUserName( ), $_POST );

        // ein neues Template anlegen
        else if( isset( $_POST['newTemplate'] ) && $_POST['newTemplate'] != '' &&
            $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $parentID, PERM_WRITE ))
            templateFunctions::createTemplate( $db,
                                               $_POST['oType'],
                                               $_POST['newTemplate'],
                                               $parentID,
                                               $perm->getMyUserName( ),
                                               $perm->getRootTreeRights( ));

        // ein Template zum editieren des Namens kennzeichnen
        else if( isset( $_GET['edit'] ))
            $smarty->assign( 'editTplName', $_GET['edit'] );

        // ein Template umbenennen
        else if( isset( $_POST['templateID'] ) &&
                 isset( $_POST['renameTemplate'] ) && $_POST['renameTemplate'] != '' &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_POST['templateID'], PERM_WRITE ))
            templateFunctions::renameTemplate( $db, $_POST['templateID'], $_POST['renameTemplate'], $perm->getMyUserName( ));

        // die Beschreibung eines Templates ändern
        else if( isset( $_POST['descOID'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_POST['descOID'], PERM_READ ))
            description::setDescription( $db,
                                        'TEMPLATE',
                                        $_POST['descOID'],
                                        $_POST['setOwner'],
                                        $_POST['setDescription'],
                                        $perm->getMyUserName( ));

        // die Rechte eines Templates öffnen
        else if( isset( $_GET['setRights'] ))
            prepareEditRights( $db, $_GET['setRights'], $perm, $css, $smarty );

        // ein Template duplizieren
        else if( isset( $_GET['duplicate'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['duplicate'], PERM_READ ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['parentID'], PERM_WRITE ))
            templateFunctions::duplicateTemplate( $db, $_GET['duplicate'], $perm->getMyUserName( ));

        // eine Notification speichern
        else if( isset( $_POST['templateID'] ) &&
                 isset( $_POST['nMail_0'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_POST['templateID'], PERM_EXE ))
            templateFunctions::editNotifier( $db, $_POST['templateID'], $_POST, $perm->getMyUserName( ));

        // ein Template verschieben
        else if( isset( $_GET['moveSrc'] ) &&
                 isset( $_GET['moveTid'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['parentID'], PERM_WRITE ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['moveSrc'], PERM_WRITE ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['moveTid'], PERM_WRITE ))
            templateFunctions::moveTemplate( $db, $_GET['moveTid'], $_GET['parentID'], $perm->getMyUserName( ));

        // einen Mutex zurücksetzen
        else if( isset( $_GET['mutex'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['mutex'], PERM_ADMIN ))
        {
            $logger = new logger( $db, 'overview.php?mutex( ' . $_GET['mutex'] . ', ' . $perm->getMyUserName( ) . ' )' );

            mutex::releaseMutex( $db, $logger, 'TEMPLATE', $_GET['mutex'] );
        }

        // die Beschreibung eines Templates öffnen
        else if( isset( $_GET['editDesc'] ))
        {
            $smarty->assign( 'showDesc', 1 );
            $smarty->assign( 'descOTpe', 'TEMPLATE' );
            $smarty->assign( 'descOID', $_GET['editDesc'] );
            $smarty->assign( 'oldDesc', description::getDescription( $db, 'TEMPLATE', $_GET['editDesc'] ));

            array_push( $css, 'dialog.css' );
        }

        // ein Recht entfernen
        else if( isset( $_POST['templateID'] ) &&
                 isset( $_POST['method'] ) && $_POST['method'] == 'revoke' &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_POST['templateID'], PERM_WRITE ))
        {
            templateFunctions::revokeTemplatePermission( $db,
                                                         $_POST['templateID'],
                                                         $_POST['grpname'],
                                                         $_POST['permission'],
                                                         $perm->getMyUserName( ));

            prepareEditRights( $db, $_POST['templateID'], $perm, $css, $smarty );
        }

        // ein Recht vergeben
        else if( isset( $_POST['templateID'] ) &&
                 isset( $_POST['method'] ) && $_POST['method'] == 'grant' &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_POST['templateID'], PERM_WRITE ))
        {
            templateFunctions::setTemplatePermission( $db,
                                                      $_POST['templateID'],
                                                      $_POST['grpname'],
                                                      $_POST['permission'],
                                                      $perm->getMyUserName( ));

            prepareEditRights( $db, $_POST['templateID'], $perm, $css, $smarty );
        }

        // die Notifications eines Templates öffnen
        else if( isset( $_GET['mail'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['mail'], PERM_EXE ))
        {
            $smarty->assign( 'showMail', $_GET['mail'] );
            $smarty->assign( 'mailStates', array( 'ERROR' => JOB_STATES['ERROR']['id'],
                                                  'OK'    => JOB_STATES['OK']['id'] ));

            $smarty->assign( 'notis', templateFunctions::getTemplateNotifier( $db, $_GET['mail'] ));

            array_push( $css, 'dialog.css' );
        }

        // ein Template löschen
        else if( isset( $_GET['rem'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['rem'], PERM_WRITE ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['parentID'] , PERM_WRITE ))
        {
            // das Template kann sofort gelöscht werden
            if( templateFunctions::isDeletable( $db, $_GET['rem'] ))
                templateFunctions::deleteTemplate( $db, $_GET['rem'], $perm->getMyUserName( ));

            // Force-Nachfrage
            else
            {
                $smarty->assign( 'askForceDelete', $_GET['rem'] );

                array_push( $css, 'dialog.css' );
            }
        }

        // ein Template im Force-Modus löschen
        else if( isset( $_POST['deleteTemplateForced'] ) &&
                 isset( $_POST['reallyDelete'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_POST['deleteTemplateForced'], PERM_WRITE ) &&
                 $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_POST['parentID'] , PERM_WRITE ))
            templateFunctions::deleteTemplate( $db, $_POST['deleteTemplateForced'], $perm->getMyUserName( ), true );

        // ein Wartungsfenster erstellen
        else if( isset( $_POST['newWDAY'] ) &&
                 isset( $_POST['newStart'] ) && $_POST['newStart'] != '' &&
                 isset( $_POST['newEnd'] ) && $_POST['newEnd'] != '' &&
                 $perm->canIDo( $db, PERM_OBJECT_SETTINGS, 0, PERM_ADMIN ))
            maintenance::createMaintenanceTime( $db, $_POST['newWDAY'], $_POST['newStart'], $_POST['newEnd'], $_POST['newDate' ] );

        // ein Wartungsfenster editieren
        else if( isset( $_GET['mID'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_SETTINGS, 0, PERM_ADMIN ))
            $smarty->assign( 'editMiD', $_GET['mID'] );

        // ein Wartungsfenster updaten
        else if( isset( $_POST['editMID'] ) && $_POST['editMID'] != '' &&
                 isset( $_POST['updateWDAY'] ) && $_POST['updateWDAY'] != '' &&
                 isset( $_POST['updateStart'] ) && $_POST['updateStart'] != '' &&
                 isset( $_POST['updateEnd'] ) && $_POST['updateEnd'] != '' &&
                 $perm->canIDo( $db, PERM_OBJECT_SETTINGS, 0, PERM_ADMIN ))
            maintenance::updateMaintenanceTime( $db,
                                                $_POST['editMID'],
                                                $_POST['updateWDAY'],
                                                $_POST['updateStart'],
                                                $_POST['updateEnd'],
                                                $_POST['updateDate'] );

        // ein Wartungsfenster löschen
        else if( isset( $_GET['rmID'] ) &&
                 $perm->canIDo( $db, PERM_OBJECT_SETTINGS, 0, PERM_ADMIN ))
            maintenance::deleteMaintenanceTime( $db, $_GET['rmID'] );
    }

    // bei Admin-Rechten das Wartungsfenster anzeigen
    if( $perm->canIDo( $db, PERM_OBJECT_SETTINGS, 0, PERM_ADMIN ))
    {
        $smarty->assign( 'showMaintenance', true );

        // Alle Wartungsfensetr laden
        $smarty->assign( 'maintenance', maintenance::getMaintenance( $db ));
        $smarty->assign( 'wDaySelect', maintenance::$weekDays );
    }

    // eine pageID holen
    $smarty->assign( 'pageID', pageID::getMyPageID( $db, $perm->getMyUserName( )));

    // die viewOption laden
    $viewOption = viewOption::getUserViewOption( $db, $perm->getMyUserName( ));
    $smarty->assign( 'viewOption', $viewOption );

    // Alle Templates laden
    $templates = array( );

    // nach Templates suchen
    if( $viewOption['X2_SEARCH_TEMPLATE'] )
    {
        $sPattern = '';
        $parentID = 0;

        if( isset( $_POST['sPattern'] ))
            $sPattern = $_POST['sPattern'];

        $smarty->assign('sPattern', $sPattern );

        templateFunctions::getTemplates2Display( $db, $parentID, $perm, $sPattern, $templates );
    }
    else
    {
        // den Baum meiner Position laden
        $smarty->assign( 'ttree', templateFunctions::getMyRootPath( $db, $parentID ));

        // schreibrechte auf den Baum
        $smarty->assign( 'writeTree', $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $parentID , PERM_WRITE ) );

        templateFunctions::getTemplates2Display( $db, $parentID, $perm, null, $templates );
    }

    // meine Position im Baum
    $smarty->assign( 'parentID', $parentID );

    $smarty->assign( 'templates', $templates );
    $smarty->assign( 'JOB_STATES', JOB_STATES );

    // die CSS laden
    $smarty->assign( 'ROOT_URL', ROOT_URL );
    $smarty->assign( 'css', $css );
    $smarty->assign( 'js', $js );

    $smarty->display( 'overview.tpl' );
}
else
{
    $smarty->display( 'noSession.tpl' );
}

?>
