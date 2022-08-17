<?php

require_once 'conf.d/base.conf';
require_once 'conf.d/x2_host.conf';
require_once( ROOT_DIR . '/lib/class/mySmarty.class.php' );
require_once( ROOT_DIR . '/lib/class/permission.class.php' );
require_once( ROOT_DIR . '/lib/class/dbLink.class.php' );
require_once( ROOT_DIR . '/lib/class/pageID.class.php' );
require_once( ROOT_DIR . '/lib/class/templateFunctions.class.php' );
require_once( ROOT_DIR . '/lib/class/jobFunctions.class.php' );
require_once( ROOT_DIR . '/lib/class/description.class.php' );

function checkRefHosts( &$import, $post )
{
    foreach( $import['hosts'] as $host => $value )
        if( isset( $post['host_' . $host ] ))
            $import['hosts'][ $host ] = $post['host_' . $host ];

        else
            throw new Exception( "Der Host " . $host . " wurde nicht gemappt" );
}

function checkRefTemplate( &$import, $post )
{
    foreach( $import['refTid'] as $rTid => $rName )
        if( isset( $import['tid'][ $rTid ] ))
            $import['refTid'][ $rTid ] = array( 'refTaget' => null,
                                                'refJobs'  => array( ));

        else if( isset( $post['ref_' . $rTid ] ))
            $import['refTid'][ $rTid ] = array( 'refTaget' => $post['ref_' . $rTid ],
                                                'refJobs'  => array( ));

        else
            throw new Exception( "Der Job ( " . $rTid . " ) " . $rName . " wurde nicht gemappt" );
}

function importJob( $db, $tid, $job, $user, $modules, &$refMap )
{
    // den Job erstellen
    $oid = jobFunctions::nativeCreateJob( $db, $tid, $job['type'], $job['name'], $user );

    // den Breakpoint setzen
    if( isset( $job['brkp'] ) && $job['brkp'] != '' )
        jobFunctions::setBreakpoint( $db, $oid, $user, 1, true );

    // das Modul importieren
    $modules[ $job['type']]->importJob( $db, $tid, $oid, $job, $user );

    // die Beschreibung importieren
    if( isset( $job['desc'] ))
        description::setDescription( $db, 'JOB', $oid, $job['desc']['owner'], $job['desc']['desc'], $user, true );

    // Referenzen bei START_TEMPLATE ablegen
    if( $job['type'] == 'START_TEMPLATE' )
        array_push( $refMap['refTid'][ $job['refTid']]['refJobs'], $oid );

    return $oid;
}

function importTemplate( $db, $template, &$refMap, $parent, $permission, $modules )
{
    $user = $permission->getMyUserName( );

    // das Template erstellen
    $tid = templateFunctions::nativeCreateTemplate( $db, $template['type'], $template['name'], $user );

    // die RefMap pflegen
    $refMap['tRefMap'][ $template['tid']] = $tid;

    if( $parent > 0 )
    {
        // meinen Vater als Parent eintragen
        templateFunctions::createParent( $db, $tid, $parent, $user );

        // die Rechte meines Vaters übernehmen
        $rights = templateFunctions::getTemplatePermission( $db, $parent );

        foreach( $rights as $grpName => $perms )
            foreach( $perms as $perm => $pValue )
                if( $pValue )
                    templateFunctions::setTemplatePermission( $db, $tid, $grpName, $perm, $user, true );
    }
    else
        // die Root-Tree-Rechte eintragen
        foreach( $permission->getRootTreeRights( ) as $grpName => $perms )
            foreach( $perms as $perm )
                templateFunctions::setTemplatePermission( $db, $tid, $grpName, $perm, $user, true );

    // Variablen anlegen
    if( isset( $template['var'] ))
        foreach( $template['var'] as $value )
            templateFunctions::setVar( $db, $tid, $value['name'], $value['value'], $user );

    // Beschreibung anlegen
    if( isset( $template['desc'] ))
        description::setDescription( $db, 'TEMPLATE', $tid, $template['desc']['owner'], $template['desc']['desc'], $user, true );

    // Notifier anlegen
    if( isset( $template['notis'] ))
        foreach( $template['notis'] as $value )
            templateFunctions::nativeCreateNotifier( $db, $tid, $value['state'], $value['recipient'], $user );

    // Kinder / Jobs erstellen
    switch( $template['type'] )
    {
                  // die Kind-Templates erstellen
        case 'G': if( isset( $template['childs'] ))
                      foreach( $template['childs'] as $child )
                          importTemplate( $db, $child, $refMap, $tid, $permission, $modules );

                  break;

        case 'T': $jobMap = array( );

                  // die Jobs erstellen
                  if( isset( $template['jobs'] ))
                      foreach( $template['jobs'] as $job )
                          $jobMap[ $job['jid']] = importJob( $db, $tid, $job, $user, $modules, $refMap );

                  // den Job-Tree erstellen
                  if( isset( $template['jobTree'] ))
                      foreach( $template['jobTree'] as $link )
                          jobFunctions::linkJob( $db, $jobMap[ $link['jid']], $jobMap[ $link['pid']], $user, true );

                  break;
    }
}

function import( $db, &$import, $post, $parent, $perm )
{
    try
    {
        // sind alle Hosts gemappt
        checkRefHosts( $import, $post );

        // wurden alle referenzierten Templates gemappt
        checkRefTemplate( $import, $post );

        // alle Module instanziieren
        $modules = array( );

        foreach( MODULES as $moduleName => $moduleDef )
            if( $moduleDef['active'] )
            {
                require_once( $moduleDef['classFile'] );

                $className = $moduleDef['className'];
                $modules[ $moduleName ] = new $className( );
            }

        // das Mapping der alten auf die neuen tids
        $import['tRefMap'] = array( );

        // das Template importieren
        importTemplate( $db, $import['tpl'], $import, $parent, $perm, $modules );

        // alle Referenzen updaten
        foreach( $import['refTid'] as $oTId => $refT )
            foreach( $refT['refJobs'] as $job )
                if( $refT['refTaget'] != '' )
                    $modules['START_TEMPLATE']->postImport( $db, $job, $refT['refTaget'], $perm->getMyUserName( ));
                else
                    $modules['START_TEMPLATE']->postImport( $db, $job, $import['tRefMap'][ $oTId ], $perm->getMyUserName( ));

        $db->commit( );

        return true;
    }
    catch( Exception $e )
    {
        $db->rollback( );
        print $e->getMessage( );
    }

    return false;
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

    $parentID = 0;

    if( isset( $_POST['parentID'] ))
        $parentID = $_POST['parentID'];

    $smarty->assign( 'parentID', $parentID );

    // Schreibrecht auf das aktuelle Template
    if( isset( $_POST['pageID'] ) && pageID::usePageID( $db, $_POST['pageID'], $perm->getMyUserName( )) &&
        $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $parentID, PERM_WRITE ))
    {
        // wurde eine Datei gesendet
        if( isset( $_FILES['importDatei']['error'] ) && $_FILES['importDatei']['error'] == UPLOAD_ERR_OK )
        {
            // die Datei kopieren
            $fileName = ROOT_DIR . '/tmp/x2_import_' . $_POST['pageID'] . '.json';

            if( move_uploaded_file( $_FILES['importDatei']['tmp_name'], $fileName ))
            {
                // die Datei lesen
                $myJSON = file_get_contents( $fileName );

                // das JSON interpretieren
                $import = json_decode( $myJSON, true );

                // alle referenzierbaren Templates laden
                $refAble = $db->dbRequest( "select OID, OBJECT_NAME
                                              from X2_TEMPLATE
                                             where OBJECT_TYPE = 'T'
                                             order by OBJECT_NAME" );

                $smarty->assign( 'fileName', $fileName );
                $smarty->assign( 'import', $import );
                $smarty->assign( 'REMOTE_HOSTS', REMOTE_HOSTS );
                $smarty->assign( 'refAble', $refAble->resultset );
            }
            else
                print "Fehler beim Zugriff auf die Datei";
        }

        // das Mapping wurde bestätigt
        else if( isset( $_POST['fileName'] ))
        {
            // das File wieder laden
            $myJSON = file_get_contents( $_POST['fileName'] );

            // das JSON interpretieren
            $import = json_decode( $myJSON, true );

            // den Import durchführen
            if( import( $db, $import, $_POST, $parentID, $perm ))
                $smarty->assign( 'importOK', true );
            else
                $smarty->assign( 'importError', true );

            // die Datei löschen
            unlink( $_POST['fileName'] );
        }

        else
            print "Es wurde keine Datei hochgeladen";
    }

    // eine pageID holen
    $smarty->assign( 'pageID', pageID::getMyPageID( $db, $perm->getMyUserName( )));

    // die CSS laden
    $smarty->assign( 'ROOT_URL', ROOT_URL );
    $smarty->assign( 'css', $css );
    $smarty->assign( 'js', $js );

    $smarty->display( 'import.tpl' );
}
else
{
    $smarty->display( 'noSession.tpl' );
}

?>
