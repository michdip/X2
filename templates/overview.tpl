{include file='header.tpl'}
{include file='description.tpl'}
{include file='permission.tpl'}
{include file='notifier.tpl'}
{include file='askForceDelete.tpl'}

{function name=printViewOption pName='' checked=false}
    <td align="center">
        {if $checked}
            <input type="checkbox" name="set_{$pName}" onChange="this.form.submit()" checked />
        {else}
            <input type="checkbox" name="set_{$pName}" onChange="this.form.submit()" />
        {/if}
    </td>
{/function}


        <h2 align="center">X2-Prozessübersicht</h2>

        <form method="post" action="overview.php" />
            <input type="hidden" name="setUserViewOption" value="1" />
            <input type="hidden" name="pageID" value="{$pageID}" />
            <input type="hidden" name="parentID" value="{$parentID}" />

            <table align="center">
                <tr>
                    <td colspan="19">Anzeigeoptionen</td>
                </tr>
                <tr>
                     <td><span class="glFKreuz" /></td>
                     <td><span class="glTag" /></td>
                     <td><span class="glFilm" /></td>
                     <td><span class="glRecht" /></td>
                     <td><span class="glRad" /></td>
                     <td><span class="glVar" /></td>
                     <td><span class="glEdit" /></td>
                     <td><span class="glDup" /></td>
                     <td><span class="glMove" /></td>
                     <td><span class="glExport" /></td>
                     <td><span class="glRem" /></td>
                     <td><span class="glLink" /></td>
                     <td><span class="glMail" /></td>
                     <td><span class="glRoad" /></td>
                     <td><span class="glPower" /></td>
                     <td>
                         <span class="glSchedule" />
                         <span class="glPlay" />
                         <span class="glPause" />
                         <span class="glEject" />
                     </td>
                     <td><span class="glLupe" /></td>
                 </tr>
                 <tr>
                     {printViewOption pName='X2_SEARCH_TEMPLATE' checked=$viewOption.X2_SEARCH_TEMPLATE}
                     {printViewOption pName='X2_VIEW_TAG' checked=$viewOption.X2_VIEW_TAG}
                     {printViewOption pName='X2_VIEW_HISTORY' checked=$viewOption.X2_VIEW_HISTORY}
                     {printViewOption pName='X2_VIEW_RECHT' checked=$viewOption.X2_VIEW_RECHT}
                     {printViewOption pName='X2_EDIT_TEMPLATE' checked=$viewOption.X2_EDIT_TEMPLATE}
                     {printViewOption pName='X2_EDIT_VARIABLE' checked=$viewOption.X2_EDIT_VARIABLE}
                     {printViewOption pName='X2_EDIT_TEMPLATE_NAME' checked=$viewOption.X2_EDIT_TEMPLATE_NAME}
                     {printViewOption pName='X2_DUPLICATE_TEMPLATE' checked=$viewOption.X2_DUPLICATE_TEMPLATE}
                     {printViewOption pName='X2_MOVE_TEMPLATE' checked=$viewOption.X2_MOVE_TEMPLATE}
                     {printViewOption pName='X2_EXPORT_TEMPLATE' checked=$viewOption.X2_EXPORT_TEMPLATE}
                     {printViewOption pName='X2_REMOVE_TEMPLATE' checked=$viewOption.X2_REMOVE_TEMPLATE}
                     {printViewOption pName='X2_VIEW_LINKED_TEMPLATE' checked=$viewOption.X2_VIEW_LINKED_TEMPLATE}
                     {printViewOption pName='X2_TEMPLATE_NOTIFIER' checked=$viewOption.X2_TEMPLATE_NOTIFIER}
                     {printViewOption pName='X2_MUTEX_STATE' checked=$viewOption.X2_MUTEX_STATE}
                     {printViewOption pName='X2_SWITCH_POWER' checked=$viewOption.X2_SWITCH_POWER}
                     {printViewOption pName='X2_RUN_CONTROLS' checked=$viewOption.X2_RUN_CONTROLS}
                     {printViewOption pName='X2_VIEW_CONTROLS' checked=$viewOption.X2_VIEW_CONTROLS}
                 </tr>
            </table>
        </form>

        {if $viewOption.X2_SEARCH_TEMPLATE}
            <form method="post" action="overview.php" style="display: inline" />
                <input type="hidden" name="pageID" value="{$pageID}" />
                <input type="hidden" name="set_X2_SEARCH_TEMPLATE" value="on" />

                <p align="center">
                    Suche: <input type="text" name="sPattern" value="{$sPattern}" />
                </p>
            </form>
        {else}
            <p align="center">
                Ebene: <a href="overview.php?pageID={$pageID}&parentID=0">ROOT</a>

                {foreach from=$ttree item=parent}
                    / <a href="overview.php?pageID={$pageID}&parentID={$parent.OID}">{$parent.OBJECT_NAME}</a>
                {/foreach}
            </p>
        {/if}

        <table align="center">
            <tr>
                <th>OID</th>
                <th colspan="12">
                    <span class="glRad" />
                </th>
                <th>Name</th>
                <th>letzter Start</th>
                <th colspan="2">Status</th>
                <th colspan="5">
                    <span class="glPferd" />
                </th>
                <th>nächster Start</th>
            </tr>

            {foreach from=$templates item=template}
                <tr>
                    <td>{$template.OID}</td>

                    {**** G: DrillDown T: bearbeiten ****}
                    {if $template.OBJECT_TYPE == 'G'}
                        <td>
                            {if $template.uPerms.READ}
                                <a class="glEye blueButton"
                                    href="overview.php?parentID={$template.OID}"
                                   title="Inhalt der Gruppe betrachten"></a>
                            {else}
                                <span class="glEye grayButton" />
                            {/if}
                        </td>
                    {elseif $viewOption.X2_EDIT_TEMPLATE && $template.showEdit}
                        <td>
                            {if $template.uPerms.WRITE}
                                <a class="glRad blueButton"
                                    href="buildTemplate.php?pageID={$pageID}&tid={$template.OID}"
                                   title="Jobs des Templates bearbeiten"></a>
                            {else}
                                <span class="glRad grayButton" />
                            {/if}
                        </td>
                    {else}
                        <td>&nbsp;</td>
                    {/if}

                    {**** Kommentar am Template hinterlegen ****}
                    {if $viewOption.X2_VIEW_TAG}
                        <td>
                            {if $template.uPerms.READ}
                                {if $template.desc.DESCRIPTION == ''}
                                    <a class="glTag blueButton"
                                        href="overview.php?parentID={$template.parentID}&pageID={$pageID}&editDesc={$template.OID}"
                                       title="Die Beschreibung des Templates anzeigen"></a>
                                {else}
                                    <a class="glTag greenButton"
                                        href="overview.php?parentID={$template.parentID}&pageID={$pageID}&editDesc={$template.OID}"
                                       title="{$template.desc.DESCRIPTION}"></a>
                                {/if}
                            {else}
                                <span class="glTag grayButton" />
                            {/if}
                        </td>
                    {else}
                        <td>&nbsp;</td>
                    {/if}

                    {**** Historie anzeigen ****}
                    {if $viewOption.X2_VIEW_HISTORY}
                        <td>
                            {if $template.uPerms.READ}
                                <a  class="glFilm blueButton"
                                     href="showHistory.php?tid={$template.OID}"
                                   target="_blank"
                                    title="Die Änderungshistorie des Templates anzeigen"></a>
                            {else}
                                <span class="glTag grayButton" />
                            {/if}
                        </td>
                    {else}
                        <td>&nbsp;</td>
                    {/if}

                    {**** Rechte setzen ****}
                    {if $viewOption.X2_VIEW_RECHT}
                        <td>
                            <a class="glRecht blueButton"
                                href="overview.php?parentID={$template.parentID}&pageID={$pageID}&setRights={$template.OID}"
                               title="Berechtigungen bearbeiten"></a>
                        </td>
                    {else}
                        <td>&nbsp;</td>
                    {/if}

                    {**** Variablen editieren ****}
                    {if $viewOption.X2_EDIT_VARIABLE}
                        <td>
                            {if !$template.uPerms.EXE}
                                <span class="glVar grayButton" />
                            {elseif $template.hasVariables}
                                <a class="glVar greenButton"
                                    href="buildTemplate.php?pageID={$pageID}&tid={$template.OID}&varsOnly=1"
                                   title="Variablen bearbeiten"></a>
                            {else}
                                <a class="glVar blueButton"
                                    href="buildTemplate.php?pageID={$pageID}&tid={$template.OID}&varsOnly=1"
                                   title="Variablen bearbeiten"></a>
                            {/if}
                        </td>
                    {else}
                        <td>&nbsp;</td>
                    {/if}

                    {**** Template umbenennen ****}
                    {if $viewOption.X2_EDIT_TEMPLATE_NAME}
                        <td>
                            {if $template.uPerms.WRITE}
                                <a class="glEdit blueButton"
                                    href="overview.php?parentID={$template.parentID}&pageID={$pageID}&edit={$template.OID}"
                                    title="Objekt umbenennen"></a>
                            {else}
                                <span class="glEdit grayButton" />
                            {/if}
                        </td>
                    {else}
                        <td>&nbsp;</td>
                    {/if}

                    {**** Template duplizieren ****}
                    {if $viewOption.X2_DUPLICATE_TEMPLATE && $template.OBJECT_TYPE == 'T'}
                        <td>
                            {if $template.gPerms.WRITE && $template.uPerms.READ}
                                <a class="glDup blueButton"
                                    href="overview.php?parentID={$template.parentID}&pageID={$pageID}&duplicate={$template.OID}"
                                   title="Template duplizieren"></a>
                            {else}
                                <span class="glDup grayButton" />
                            {/if}
                        </td>
                    {else}
                        <td>&nbsp;</td>
                    {/if}

                    {**** Template verschieben ****}
                    {if $viewOption.X2_MOVE_TEMPLATE}
                        <td>
                            {if $template.gPerms.WRITE && $template.uPerms.WRITE}
                                <a class="glMove blueButton"
                                    href="templateTree.php?parentID={$template.parentID}&pageID={$pageID}&move={$template.OID}"
                                   title="Objekt verschieben"></a>
                            {else}
                                <span class="glMove grayButton" />
                            {/if}
                        </td>
                    {else}
                        <td>&nbsp;</td>
                    {/if}

                    {**** Template exportieren ****}
                    {if $viewOption.X2_EXPORT_TEMPLATE}
                        <td>
                            {if $template.uPerms.WRITE}
                                <a class="glExport blueButton"
                                    href="export.php?pageID={$pageID}&tid={$template.OID}"
                                   title="Objekt exportieren"
                                   target="_blank"></a>
                            {else}
                                <span class="glExport grayButton" />
                            {/if}
                        </td>
                    {else}
                        <td>&nbsp;</td>
                    {/if}

                    {**** Template löschen ****}
                    {if $viewOption.X2_REMOVE_TEMPLATE}
                        <td>
                            {if $template.gPerms.WRITE && $template.uPerms.WRITE}
                                {if $template.showDelete}
                                    <a class="glRem greenButton"
                                        href="overview.php?parentID={$template.parentID}&pageID={$pageID}&rem={$template.OID}"
                                       title="Objekt löschen"></a>
                                {else if $template.OBJECT_TYPE == 'T' &&
                                         !$template.isLinked &&
                                         $template.showPower == 'powerOn'}
                                    <a class="glRem blueButton"
                                        href="overview.php?parentID={$template.parentID}&pageID={$pageID}&rem={$template.OID}"
                                       title="Objekt löschen"></a>
                                {/if}
                            {else}
                                <span class="glRem grayButton" />
                            {/if}
                        </td>
                    {else}
                        <td>&nbsp;</td>
                    {/if}

                    {**** ist das Template verlinkt ****}
                    {if $viewOption.X2_VIEW_LINKED_TEMPLATE}
                        {if $template.isLinked}
                            <td><span class="glLink greenButton" /></td>
                        {else}
                            <td>&nbsp;</td>
                        {/if}
                    {else}
                        <td>&nbsp;</td>
                    {/if}

                    {**** Notoifier ****}
                    {if $viewOption.X2_TEMPLATE_NOTIFIER}
                        <td>
                            {if !$template.uPerms.EXE}
                                <span class="glMail grayButton" />
                            {elseif $template.hasNotifier}
                                <a class="glMail greenButton"
                                    href="overview.php?parentID={$template.parentID}&pageID={$pageID}&mail={$template.OID}"
                                   title="eMail-Benachrichtigung setzen"></a>
                            {else}
                                <a class="glMail blueButton"
                                    href="overview.php?parentID={$template.parentID}&pageID={$pageID}&mail={$template.OID}"
                                   title="eMail-Benachrichtigung setzen"></a>
                            {/if}
                        </td>
                    {else}
                        <td>&nbsp;</td>
                    {/if}

                    {**** Name des Templates ****}
                    {if isset( $editTplName ) && $editTplName == $template.OID}
                        <form method="post" action="overview.php" style="display: inline">
                            <input type="hidden" name="pageID" value="{$pageID}" />
                            <input type="hidden" name="parentID" value="{$template.parentID}" />
                            <input type="hidden" name="templateID" value="{$template.OID}" />

                            <td>
                                <input type="text" name="renameTemplate" size="80" value="{$template.OBJECT_NAME}" />
                            </td>
                        </form>
                    {elseif $template.displayLength > 130}
                        <td>{$template.OBJECT_NAME} <a title="{$template.startzeit}">[ ... ]</a></td>
                    {else}
                        <td>{$template.OBJECT_NAME} {$template.startzeit}</td>
                    {/if}

                    {**** letzte Laufzeit ****}
                    <td>{$template.LAST_RUN} {$template.LAST_DURATION_STR}</td>

                    {**** Mutex-State ****}
                    {if $viewOption.X2_MUTEX_STATE}
                        {if !$template.mutexState}
                            {if $template.uPerms.ADMIN}
                                <td>
                                    <a class="glRoad redButton"
                                        href="overview.php?parentID={$template.parentID}&pageID={$pageID}&mutex={$template.OID}"
                                       title="Mutex zurücksetzen"></a>
                                </td>
                            {else}
                                <td><span class="glRoad redButton" /></td>
                            {/if}
                        {else if !$template.mutexTreeState}
                            <td><span class="glRoad orangeButton" /></td>
                        {else}
                            <td><span class="glRoad greenButton" /></td>
                        {/if}
                    {else}
                        <td>&nbsp;</td>
                    {/if}

                    {**** Status ****}
                    <td>
                        {if $template.RUN_STATE == $JOB_STATES.RUNNING_6.id}
                            {if $template.EXE_STATE}
                                <span class="glRunner purpleButton" />
                            {else}
                                <span class="glOkState purpleButton" />
                            {/if}
                        {else if $template.RUN_STATE == $JOB_STATES.OK.id}
                            {if $template.EXE_STATE}
                                <span class="glRunner blueButton" />
                            {else}
                                <span class="glOkState greenButton" />
                            {/if}
                        {else if $template.RUN_STATE == $JOB_STATES.ERROR.id}
                            {if $template.EXE_STATE}
                                <span class="glRunner redButton" />
                            {else}
                                <span class="glFire redButton" />
                            {/if}
                        {else if $template.EXE_STATE}
                            <span class="glRunner blueButton" />
                        {else}
                            &nbsp;
                        {/if}
                    </td>

                    {**** Ausführungsaktionen ****}
                    {if $template.OBJECT_TYPE == 'T'}

                        {*** Power.Knopf ***}
                        {if $viewOption.X2_SWITCH_POWER && $template.showPower != 'none'}
                            <td>
                                {if !$template.uPerms.EXE}
                                    <span class="glPower grayButton" />
                                {elseif $template.showPower == 'powerOn'}
                                    <a class="glPower redButton"
                                        href="overview.php?parentID={$template.parentID}&pageID={$pageID}&powerOn={$template.OID}"
                                       title="Template aktivieren"></a>
                                {elseif $template.showPower == 'powerOff'}
                                    <a class="glPower blueButton"
                                        href="overview.php?parentID={$template.parentID}&pageID={$pageID}&powerOff={$template.OID}"
                                       title="Template deaktivieren"></a>
                                {/if}
                            </td>
                        {else}
                            <td>&nbsp;</td>
                        {/if}

                        {*** Job schedulen ***}
                        {if $viewOption.X2_RUN_CONTROLS && $template.showSheduler}
                            <td>
                                {if !$template.uPerms.EXE}
                                    <span class="glSchedule grayButton" />
                                {else}
                                    <a class="glSchedule redButton"
                                        href="overview.php?parentID={$template.parentID}&pageID={$pageID}&shedule={$template.OID}"
                                       title="automatisierte Ausführung starten"></a>
                                {/if}
                            </td>
                        {else}
                            <td>&nbsp;</td>
                        {/if}

                        {*** Manuell Play | Pause | Resume ***}
                        {if $viewOption.X2_RUN_CONTROLS && $template.showPlay == 'play'}
                            <td>
                                {if $template.uPerms.EXE}
                                    <a class="glPlay blueButton"
                                        href="overview.php?parentID={$template.parentID}&pageID={$pageID}&play={$template.OID}"
                                       title="Ausführung starten"></a>
                                {else}
                                    <span class="glPlay grayButton" />
                                {/if}
                            </td>
                        {elseif $viewOption.X2_RUN_CONTROLS && $template.showPlay == 'pause'}
                            <td>
                                {if $template.uPerms.EXE}
                                    <a class="glPause blueButton"
                                        href="overview.php?parentID={$template.parentID}&pageID={$pageID}&pause={$template.OID}"
                                       title="Ausführung pausieren"></a>
                                {else}
                                    <span class="glPause grayButton" />
                                {/if}
                            </td>
                        {elseif $viewOption.X2_RUN_CONTROLS && $template.showPlay == 'resume'}
                            <td class="rotated">
                                {if $template.uPerms.EXE}
                                    <a class="glEject blueButton"
                                        href="overview.php?parentID={$template.parentID}&pageID={$pageID}&resume={$template.OID}"
                                       title="Ausführung fortsetzen"></a>
                                {else}
                                    <span class="glEject grayButton" />
                                {/if}
                            </td>
                        {else}
                            <td>&nbsp;</td>
                        {/if}

                        {*** Ausführung abbrechen ***}
                        {if $viewOption.X2_RUN_CONTROLS && $template.showEject}
                            <td>
                                {if $template.uPerms.EXE}
                                    <a class="glEject blueButton"
                                        href="overview.php?parentID={$template.parentID}&pageID={$pageID}&eject={$template.OID}"
                                       title="Ausführung abbrechen"></a>
                                {else}
                                    <span class="glEject grayButton" />
                                {/if}
                            </td>
                        {else}
                            <td>&nbsp;</td>
                        {/if}

                        {*** Ausführung betrachten ***}
                        {if $viewOption.X2_VIEW_CONTROLS && $template.showWork != 0}
                            <td>
                                {if $template.uPerms.READ}
                                    <a class="glLupe blueButton"
                                        href="worklist.php?pageID={$pageID}&wid={$template.showWork}"
                                       title="Ausführung anzeigen"></a>
                                
                                {else}
                                    <span class="glLupe grayButton" />
                                {/if}
                            </td>
                        {else}
                            <td>&nbsp;</td>
                        {/if}

                        {*** Laufzeit ***}
                        {if $template.showPlay == 'pause'}
                            <td>{$template.RUN_DURATION}</td>
                        {else}
                            <td>{$template.NEXT_START_TIME}</td>
                        {/if}
                    {else}
                        <td colspan="6">&nbsp;</td>
                    {/if}
                </tr>
            {/foreach}

            {* Template erstellen / Import bei Suchansicht daktivieren *}
            {if !$viewOption.X2_SEARCH_TEMPLATE}
                <form method="post" action="overview.php" style="display: inline">
                    <input type="hidden" name="pageID" value="{$pageID}" />
                    <input type="hidden" name="parentID" value="{$parentID}" />

                    <tr>
                        {if $writeTree}
                            <td colspan="13" align="right">
                                <label for="gType">G</label>
                                <input id="gType" type="radio" name="oType" value="G" />

                                <label for="tType">T</label>
                                <input id="tType" type="radio" name="oType" value="T" checked />
                            </td>
                            <td>
                                <input type="text" name="newTemplate" size="80" />
                            </td>
                            <td colspan="10" align="center">
                                <button type="submit">
                                    <span class="glOk"/>
                                </button>
                            </td>
                        {else}
                            <td colspan="13" align="right">
                                <label for="gType">G</label>
                                <input id="gType" type="radio" name="oType" value="G" disabled />

                                <label for="tType">T</label>
                                <input id="tType" type="radio" name="oType" value="T" checked disabled />
                            </td>
                            <td>
                                <input type="text" name="newTemplate" size="80" disabled />
                            </td>
                            <td colspan="10" align="center">
                                <button type="submit" disabled >
                                    <span class="glOk"/>
                                </button>
                            </td>
                        {/if}
                    </tr>
                </form>

                {if $viewOption.X2_EXPORT_TEMPLATE}
                    <form method="post" enctype="multipart/form-data" action="import.php">
                        <input type="hidden" name="pageID" value="{$pageID}" />
                        <input type="hidden" name="parentID" value="{$parentID}" />

                        <tr>
                            <td colspan="13" align="right">Objekte importieren</td>
                            {if $writeTree}
                                <td>
                                    <input type="file" size="80" name="importDatei" accept="application/JSON" />
                                </td>
                                <td colspan="10" align="center">
                                    <button type="submit">
                                        <span class="glImport" />
                                    </button>
                                </td>
                            {else}
                                <td>
                                    <input type="file" size="80" name="importDatei" accept="application/JSON" disabled />
                                </td>
                                <td colspan="10" align="center">
                                    <button type="submit"disabled >
                                        <span class="glImport" />
                                    </button>
                                </td>
                            {/if}
                        </tr>
                    </form>
                {/if}
            {/if}
        </table>

        {if isset( $showMaintenance)}
            <h2 align="center">Wartungsfenster</h2>

            <table align="center">
                <tr>
                    <th>Tag</th>
                    <th>Datum</th>
                    <th>Startzeit</th>
                    <th>Endzeit</th>
                    <th>Aktion</th>
                </tr>

                {foreach from=$maintenance item=row}
                    <tr>
                        {if isset( $editMiD ) && $row.OID == $editMiD}
                            <form method="post" action="./overview.php" style="display: inline">
                                <input type="hidden" name="pageID" value="{$pageID}" />
                                <input type="hidden" name="parentID" value="{$parentID}" />
                                <input type="hidden" name="editMID" value="{$editMiD}" />

                                <td>
                                    <select name="updateWDAY">
                                        {foreach from=$wDaySelect item=wDay}
                                            {if $row.WDAY == $wDay.name}
                                                <option value="{$wDay.value}" selected>{$wDay.name}</option>
                                            {else}
                                                <option value="{$wDay.value}">{$wDay.name}</option>
                                            {/if}
                                        {/foreach}
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="updateDate" size="12" value="{$row.MAINTENANCE_DATE}" />
                                </td>
                                <td>
                                    <input type="text" name="updateStart" size="6" value="{$row.START}" />
                                </td>
                                <td>
                                    <input type="text" name="updateEnd" size="6" value="{$row.ENDE}" />
                                </td>
                                <td>
                                    <button type="submit">
                                        <span class="glOk " />
                                    </button>
                                </td>
                            </form>
                        {else}
                            <td>{$row.WDAY}</td>
                            <td>{$row.MAINTENANCE_DATE}</td>
                            <td>{$row.START}</td>
                            <td>{$row.ENDE}</td>
                            <td>
                                <a class="glEdit blueButton"
                                    href="overview.php?pageID={$pageID}&parentID={$parentID}&mID={$row.OID}"
                                   title="editieren"></a>
                                <a class="glRem blueButton"
                                    href="overview.php?pageID={$pageID}&parentID={$parentID}&rmID={$row.OID}"
                                   title="löschen"></a>
                            </td>
                        {/if}
                    </tr>
                {/foreach}
                <tr>
                    <form method="post" action="./overview.php" style="display: inline">
                        <input type="hidden" name="pageID" value="{$pageID}" />
                        <input type="hidden" name="parentID" value="{$parentID}" />

                        <td>
                            <select name="newWDAY">
                                {foreach from=$wDaySelect item=wDay}
                                    <option value="{$wDay.value}">{$wDay.name}</option>
                                {/foreach}
                            </select>
                        </td>
                        <td>
                            <input type="text" name="newDate" size="12" />
                        </td>
                        <td>
                            <input type="text" name="newStart" size="6" />
                        </td>
                        <td>
                            <input type="text" name="newEnd" size="6" />
                        </td>
                        <td>
                            <button type="submit">
                                <span class="glOk " />
                            </button>
                        </td>
                    </form>
                </tr>
            </table>
        {/if}

    </body>
</html>
