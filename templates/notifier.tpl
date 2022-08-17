{if isset( $showMail )}
    <div id="editForm" class="editForm">
        <div>
            <p>
                <a href="#editForm" class="close">x</a>

                <form method="post" action="overview.php">
                    <input type="hidden" name="parentID" value="{$parentID}" />
                    <input type="hidden" name="pageID" value="{$pageID}" />
                    <input type="hidden" name="templateID" value="{$showMail}" />

                    <table align="center">
                        <tr>
                            <th>Status</th>
                            <th>Empfänger</th>
                        </tr>

                        {foreach from=$notis item=row name=myNotis}
                            <tr>
                                <td>
                                    {if $smarty.foreach.myNotis.last}
                                        <input type="hidden" name="maxNotis" value="{$smarty.foreach.myNotis.iteration}" />
                                    {/if}

                                    <select name="mailOnReturnState_{$smarty.foreach.myNotis.iteration}">
                                        {foreach from=$mailStates key=key item=value}
                                            {if $value == $row.STATE}
                                                <option value="{$value}" selected>{$key}</option>
                                            {else}
                                                <option value="{$value}">{$key}</option>
                                            {/if}
                                        {/foreach}
                                    </select>
                                </td>
                                <td>
                                    <input type="text" size="40" name="nMail_{$smarty.foreach.myNotis.iteration}" value="{$row.RECIPIENT}" />
                                </td>
                        </tr>
                        {/foreach}

                        <tr>
                            <td>
                                <select name="mailOnReturnState_0">
                                    {foreach from=$mailStates key=key item=value}
                                        <option value="{$value}">{$key}</option>
                                    {/foreach}
                                </select>
                            </td>
                            <td>
                                <input type="text" size="40" name="nMail_0" />
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="center">
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
