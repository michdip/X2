{if isset( $showDesc )}
    <div id="editForm" class="editForm">
        <div>
            <p>
                <a href="#editForm" class="close">x</a>

                {if $descOTpe == 'TEMPLATE'}
                    <form method="post" action="overview.php">
                        <input type="hidden" name="parentID" value="{$parentID}" />
                {elseif $descOTpe == 'JOB'}
                    <form method="post" action="buildTemplate.php">
                        <input type="hidden" name="templateID" value="{$template.OID}" />
                {/if}

                    <input type="hidden" name="descOID" value="{$descOID}" />
                    <input type="hidden" name="pageID" value="{$pageID}" />

                    <table align="center">
                        <tr>
                             <th>Eigentümer</th>
                             <td><input type="text" name="setOwner" size="60" value="{$oldDesc.OWNER}"/></td>
                        </tr>
                        <tr>
                            <th>Beschreibung</th>
                            <td>
                                <textarea rows="15" cols="60" name="setDescription">{$oldDesc.DESCRIPTION}</textarea>
                            </td>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                            <td align="center">
                                <button type="submit">
                                    <span class="glOk" />
                                </button>
                            </td>
                        </tr>
                    </table>
                </form>
            </p>
        </div>
    </div>
{/if}
