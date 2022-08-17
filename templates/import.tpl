{include file='header.tpl'}

        <h2 align="center">Import</h2>

        {if isset( $fileName )}
            <form method="post" action="import.php">
                <input type="hidden" name="pageID" value="{$pageID}" />
                <input type="hidden" name="parentID" value="{$parentID}" />
                <input type="hidden" name="fileName" value="{$fileName}" />

                <table align="center">
                    <tr>
                        <th colspan="2">Mapping der Hosts</th>
                    </tr>
                    <tr>
                        <td>Host im Import</td>
                        <td>Host im X2</td>
                    </tr>

                    {foreach from=$import.hosts key=name item=dummy}
                        <tr>
                            <td>{$name}</td>
                            <td>
                                <select name="host_{$name}">
                                    {foreach from=$REMOTE_HOSTS key=rHost item=dummy2}
                                        <option>{$rHost}</option>
                                    {/foreach}
                                </select>
                            </td>
                        </tr>
                    {/foreach}
                </table>

                <table align="center">
                    <tr>
                        <th colspan="2">Mapping der referenzierten Templates</th>
                    </tr>
                    <tr>
                        <td>Template im Import</td>
                        <td>Template im X2</td>
                    </tr>
                    {foreach from=$import.refTid key=tid item=tName}
                        <tr>
                            <td>( {$tid} ) {$tName}</td>

                            {if isset( $import.tid[ $tid ] )}
                                <td>im Import vorhanden</td>
                            {else}
                                <td>
                                    <select name="ref_{$tid}">
                                        {foreach from=$refAble item=tpl}
                                            <option value="{$tpl.OID}">( {$tpl.OID} ) {$tpl.OBJECT_NAME}</option>
                                        {/foreach}
                                    </select>
                                </td>
                            {/if}
                        </tr>
                    {/foreach}
                </table>

                <p align="center">
                    <input type="submit" value="weiter"/>
                </p>

            </form>

        {elseif isset( $importOK )}
            <p align="center">OK</p>
        
        {elseif isset( $importError )}
            <p align="center">Fehlerhaft</p>
        
        {/if}

        <p align="center">  
            <a href="overview.php?parentID={$parentID}">zur&uuml;ck</a>
        </p>

    </body>
</html> 
