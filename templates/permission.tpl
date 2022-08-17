{function name=printEditPermission parentID='' pageID='' templateID='' editable=false groupname='' permission='' pValue=false}
    <td align="center">
        {* darf das Recht verändert werden *}
        {if $editable}
            <form method="post" action="overview.php" style="display: inline;" id="rf_{$groupname}_{$permission}">
                <input type="hidden" name="grpname" value="{$groupname}" />
                <input type="hidden" name="permission" value="{$permission}" />
                <input type="hidden" name="pageID" value="{$pageID}" />
                <input type="hidden" name="parentID" value="{$parentID}" />
                <input type="hidden" name="templateID" value="{$templateID}" />

                {* entfernen des Rechts *}
                {if $pValue}
                    <input type="hidden" name="method" value="revoke" />
                    <a href="#"
                       onClick="document.getElementById('rf_{$groupname}_{$permission}').submit()"
                       class="glOk greenButton"
                       title="Recht entziehen"></a>

                {* hinzufügen des Rechts *}
                {else}
                    <input type="hidden" name="method" value="grant" />
                    <a href="#"
                       onClick="document.getElementById('rf_{$groupname}_{$permission}').submit()"
                       class="glFire redButton"
                       title="Recht geben"></a>
                {/if}
            </form>
        {elseif $pValue}
            <span class="glOk grayButton" />
        {else}
            <span class="glFire grayButton" />
        {/if}
    </td>
{/function}

{if isset( $setRights )}
    <div id="editForm" class="editForm">
        <div>
            <p>
                <a href="#editForm" class="close">x</a>

                <table align="center">
                    <tr>
                        <th>Gruppe</th>
                        <th>Lesen</th>
                        <th>Ausführen</th>
                        <th>Editieren</th>
                        <th>Administrator</th>
                    </tr>

                    {foreach from=$allGroups key=groupname item=props}
                        <tr>
                            <td>{$groupname}</td>
                            {printEditPermission parentID=$parentID
                                                 pageID=$pageID
                                                 templateID=$setRights
                                                 editable=$editable
                                                 groupname=$groupname
                                                 permission=$PERM_READ
                                                 pValue=$props[ $PERM_READ ]}

                            {printEditPermission parentID=$parentID
                                                 pageID=$pageID
                                                 templateID=$setRights
                                                 editable=$editable
                                                 groupname=$groupname
                                                 permission=$PERM_EXE
                                                 pValue=$props[ $PERM_EXE ]}

                            {printEditPermission parentID=$parentID
                                                 pageID=$pageID
                                                 templateID=$setRights
                                                 editable=$editable
                                                 groupname=$groupname
                                                 permission=$PERM_WRITE
                                                 pValue=$props[ $PERM_WRITE ]}

                            {printEditPermission parentID=$parentID
                                                 pageID=$pageID
                                                 templateID=$setRights
                                                 editable=$editable
                                                 groupname=$groupname
                                                 permission=$PERM_ADMIN
                                                 pValue=$props[ $PERM_ADMIN ]}
                        </tr>
                    {/foreach}
                </table>
            </p>
        </div>
    </div>
{/if}
