{include file='header.tpl'}
        <form action="showHistory.php" method="post" style="display:compact;">
            <p align="center">

            {if $limitoffsetminus > -1}
                <a href="showHistory.php?tid={$tid}&limit={$dblimit}&limitoffset={$limitoffsetminus}">Vorherige Seite</a>
            {/if}

            <input id="limitpost" name="limitpost" placeholder="{$dblimit}">
            <input type="hidden" name="limitoffset" value="{$limitoffset}">
            <input type="hidden" name="tid" value="{$tid}">
            <input type="hidden" name="eid" value="{$eid}">
            <input type="submit" value="senden">
            <a href="showHistory.php?tid={$tid}&limit={$dblimit}&limitoffset={$limitoffset}">Nächste Seite</a> </p>
        </form>

        <table align="center" cellpadding="5">
            <tr>
                <th colspan="2">Definition</th>
                <th colspan="2">Akteur</th>
                <th colspan="3">Ausführung</th>
            </tr>
            <tr>
                <th>Aktion</th>
                <th>Inhalt</th>
                <th>Zeitpunkt</th>
                <th>Benutzer</th>
                <th>Prozessnummer</th>
                <th>Aktion</th>
                <th>Inhalt</th>
            </tr>
            {foreach from=$actions item=action}
                <tr>
                    {if isset( $action.TEMPLATE_EXE_ID )}
                        <td colspan="2">&nbsp;</td>
                    {else}
                        <td>{$action.DESCRIPTION}</td>
                        <td>{$action.ACTION_TEXT}</td>
                    {/if}
                    <td>{$action.CTS}</td>
                    <td>{$action.X2_USER}</td>
                    {if isset( $action.TEMPLATE_EXE_ID )}
                        <td>{$action.TEMPLATE_EXE_ID}</td>
                        <td>{$action.DESCRIPTION}</td>
                        <td>{$action.ACTION_TEXT}</td>
                    {else}
                        <td colspan="3">&nbsp;</td>
                    {/if}
                </tr>
            {/foreach}
        </table>
    </body>
</html>
