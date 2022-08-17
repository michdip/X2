<?php

require_once 'conf.d/base.conf';
require_once "Image/GraphViz.php";
require_once( ROOT_DIR . '/conf.d/x2.conf' );
require_once( ROOT_DIR . '/lib/class/dbLink.class.php' );
require_once( ROOT_DIR . '/lib/class/pageID.class.php' );
require_once( ROOT_DIR . '/lib/class/permission.class.php' );
require_once( ROOT_DIR . '/lib/class/description.class.php' );
require_once( ROOT_DIR . '/lib/class/templateFunctions.class.php' );
require_once( ROOT_DIR . '/lib/class/jobFunctions.class.php' );
require_once( ROOT_DIR . '/lib/class/modulFunctions.class.php' );

function getButtonLabel( $pageID, $tid, $oid, $linkTarget, $actionIdx, $title, $icon, $color = null, $tdOps = '', $lnkOps = ''  )
{
    $result = '<TD   HREF="' . $linkTarget . '.php?pageID=' . $pageID . '&amp;amp;tid=' . $tid . '&amp;amp;' . $actionIdx . '=' . $oid . $lnkOps . '" '
                . 'TARGET="_parent" '
                .  'TITLE="' . $title . '" '
                . 'HEIGHT="25" '
                .  'WIDTH="20" '
                . 'VALIGN="BOTTOM" '
                . $tdOps;

    if( $color != null )
        $result .= ' BGCOLOR="' . $color . '"';

    $result .= '>'
             . '<FONT FACE="Glyphicons Halflings">' . $icon . '</FONT>'
             . '</TD>';

    return $result;
}

function getJobLabel( $db, $job, $writeable, $pageID, $modules )
{
    // Kopf und Name
    $label = '<TABLE>'
           . '<TR>'
           . '<TD ALIGN="center" BGCOLOR="black" COLSPAN="5">'
           . '<FONT COLOR="white">' . $job['JOB_NAME'] . '</FONT>'
           . '</TD>'
           . '</TR>';

    // das Fleisch vom Modul
    $label .= $modules[ $job['JOB_TYPE']]->getJobLabel( $db, $job['TEMPLATE_ID'], $job['OID'], $writeable, $pageID );

    // die Beschreibung
    $desc = description::getDescription( $db, 'JOB', $job['OID'] );

    $label .= '<TR>'
            . '<TD COLSPAN="4">' . $desc['DESCRIPTION'] . '</TD>'
            . getButtonLabel( $pageID,
                              $job['TEMPLATE_ID'],
                              $job['OID'],
                              'buildTemplate',
                              'editDesc',
                              'Beschreibung bearbeiten',
                              '&#x270f;' )
            . '</TR>';

    // die Aktion-Zeile
    $label .= '<TR>';

    // Job bearbeiten
    if( $writeable && $modules[ $job['JOB_TYPE']]->opts['isEditable'] )
        $label .= getButtonLabel( $pageID, $job['TEMPLATE_ID'], $job['OID'], 'buildTemplate', 'edit', 'Job bearbeiten', '&#x270f;' );

//TODO unschön dass COLSPAN="2" für den START gillt, da dieser kein Löschen hat
    else
        $label .= '<TD colspan="2">&nbsp;</TD>';

              // Neuer Job
    $label .= getButtonLabel( $pageID,
                              $job['TEMPLATE_ID'],
                              $job['OID'],
                              'buildTemplate',
                              'newJob',
                              'Neuen Folgejob erstellen',
                              '&#x002b;' )

              // weiteren Job linken
            . getButtonLabel( $pageID,
                              $job['TEMPLATE_ID'],
                              $job['OID'],
                              'linkJob',
                              'linkJob',
                              'weiteren Folgejob verlinken',
                              '&#xe097;' );

    // Breakpoint
    if( $job['BREAKPOINT'] )
        $label .= getButtonLabel( $pageID,
                                  $job['TEMPLATE_ID'],
                                  $job['OID'],
                                  'buildTemplate',
                                  'unsetBp',
                                  'Breakpoint entfernen',
                                  '&#x26fa;',
                                  'red' );
    else
        $label .= getButtonLabel( $pageID,
                                  $job['TEMPLATE_ID'],
                                  $job['OID'],
                                  'buildTemplate',
                                  'setBp',
                                  'Hier einen Breakpoint setzen',
                                  '&#x26fa;' );

    // löschen
    if( $writeable && $modules[ $job['JOB_TYPE']]->opts['hasDelete'] && jobFunctions::isDeletable( $db, $job['OID'] ))
        $label .= getButtonLabel( $pageID,
                                  $job['TEMPLATE_ID'],
                                  $job['OID'],
                                  'buildTemplate',
                                  'remove',
                                  'diesen Job löschen',
                                  '&#xe020;' );

    else if( $modules[ $job['JOB_TYPE']]->opts['hasDelete'])
        $label .= '<TD>&nbsp;</TD>';

    $label .= '</TR>'
            . '</TABLE>';

    return $label;
}

function getEdgeLabel( $db, $tid, $oid, $parent, $writeable, $pageID )
{
    $result = array( );

    if( $writeable && jobFunctions::isUnlinkable( $db, $oid ))
    {
        $result['edgeURL']     = 'buildTemplate.php?pageID=' . $pageID . '&amp;tid=' . $tid . '&amp;remlink=' . $parent . '&amp;linkTo=' . $oid;
        $result['edgetarget']  = '_parent';
        $result['edgetooltip'] = 'Link auflösen';
    }


    return $result;
}

function getVariablesLabel( $db, $oid, $writeable, $pageID, $varsOnly )
{
    $label = '<TABLE>'
           . '<TR>'
           . '<TD BGCOLOR="black" COLSPAN="3">'
           . '<FONT COLOR="white">Variablen</FONT>'
           . '</TD>'
           . '</TR>'
           . '<TR>'
           . '<TD>Name</TD>'
           . '<TD>Wert</TD>'
           . '<TD HEIGHT="25" WIDTH="20" VALIGN="BOTTOM">'
           . '<FONT FACE="Glyphicons Halflings">&#xe019;</FONT>'
           . '</TD>'
           . '</TR>';

    // Variablen laden
    $vars = templateFunctions::getVariables( $db, $oid );

    // VarsOnly
    if( $varsOnly == 1 )
        $lnkOps = '&amp;amp;varsOnly=1';
    else
        $lnkOps = '';

    foreach( $vars as $row )
    {
        $label .= '<TR>'
                . '<TD>' . $row['VAR_NAME'] . '</TD>'
                . '<TD>' . $row['VAR_VALUE'] . '</TD>';

        if( $row['PARENT_OID'] == $oid )
        {
            if( $writeable )
                $label .= getButtonLabel( $pageID,
                                          $oid,
                                          $row['VAR_OID'],
                                          'buildTemplate',
                                          'remVar',
                                          'Variable löschen',
                                          '&#xe020;',
                                          null,
                                          '',
                                          $lnkOps );

            else
                $label .= '<TD>&nbsp;</TD>';
        }
        else
            $label .= '<TD HREF="buildTemplate.php?pageID=' . $pageID . '&amp;amp;tid=' . $row['PARENT_OID'] . '&amp;amp;varsOnly=1" '
                        . 'TARGET="_parent" TITLE="zum Template wechseln" >'
                        . $row['OBJECT_NAME']
                    . '</TD>';

        $label .= '</TR>';
    }

    if( $writeable )
        $label .= '<TR>'
                . getButtonLabel( $pageID,
                                  $oid,
                                  1,
                                  'buildTemplate',
                                  'editVars',
                                  'Variablen bearbeiten',
                                  '&#x270f;',
                                  null,
                                  'COLSPAN="3" ',
                                  $lnkOps )
                . '</TR>';

    $label .= '</TABLE>';

    return $label;
}

// Alle aktiven Module instanziieren
$modules = modulFunctions::getAllModulInstances( );

$db = new dbMysql( 'X2', false );
$perm = new permission( );

// Habe ich ein Schreibrecht auf das Template
$writeable = $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['tid'], PERM_WRITE );

// eine pageID erzeugen
$pageID = $_GET['pageID'];

$gv = new Image_GraphViz();

$gv->addAttributes( array( 'bgcolor'    => 'transparent',
                           'stylesheet' => ROOT_URL . '/lib/css/graph.css' ));

// Alle Jobs laden (wenn nicht VarsOnly
if( $_GET['varsOnly'] == 0 )
{
    // laden der Jobs
    $jobs = $db->dbRequest( "select TEMPLATE_ID,
                                    OID,
                                    JOB_NAME,
                                    JOB_TYPE,
                                    BREAKPOINT
                               from X2_JOBLIST
                              where TEMPLATE_ID = ?",
                            array( array( 'i', $_GET['tid'] )));

    // alle Jobs ausgeben
    foreach( $jobs->resultset as $job )
        $gv->addNode( $job['OID'], array( 'fontsize'    => 14,
                                          'fontname'    => 'Arial',
                                          'fillcolor'   => '#ffffff',
                                          'style'       => 'filled,rounded',
                                          'shape'       => 'box',
                                          'label'       => getJobLabel( $db, $job, $writeable, $pageID, $modules )));

    // laden des Baumes
    $edges = $db->dbRequest( "select jt.OID, jt.PARENT
                                from X2_JOBLIST jl
                                     inner join X2_JOB_TREE jt
                                        on     jt.OID = jl.OID
                                           and jl.TEMPLATE_ID = ?",
                             array( array( 'i', $_GET['tid'] )));

    foreach( $edges->resultset as $edge )
        $gv->addEdge( array( $edge['PARENT'] => $edge['OID'] ),
                      getEdgeLabel( $db, $_GET['tid'], $edge['OID'], $edge['PARENT'], $writeable, $pageID ));
}

// Für Variablen reicht das Execute-Recht
$writeable = $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['tid'], PERM_EXE );

$gv->addNode( "variablen",
              array( 'fontsize'    => 14,
                     'fontname'    => 'Arial',
                     'fillcolor'   => '#ffffff',
                     'style'       => 'filled,rounded',
                     'shape'       => 'box',
                     'label'       => getVariablesLabel( $db, $_GET['tid'], $writeable, $pageID, $_GET['varsOnly'] )));

$gv->image();

?>
