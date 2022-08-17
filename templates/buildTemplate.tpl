{include file='header.tpl'}
{include file='description.tpl'}

{if isset( $edit )}
    <div id="editForm" class="editForm">
        <div>
            <p>
                <a href="#editForm" class="close">x</a>
            </p>

            <form method="post" action="buildTemplate.php">
                <input type="hidden" name="pageID" value="{$pageID}" />
                <input type="hidden" name="templateID" value="{$template.OID}" />
                <input type="hidden" name="jobID" value="{$eJob.OID}" />
                <input type="hidden" name="currentJobType" value="{$eJob.JOB_TYPE}" />

                <table>
                    {if $eJob.JOB_TYPE != 'START'}
                        <tr>
                            <td>Name</td>
                            <td>
                                <input type="text" name="jobName" size="40" value="{$eJob.JOB_NAME}" />
                            </td>
                        </tr>
                       <tr>
                            <td>Typ</td>
                            <td>
                                <select name="changeJobType" onChange="this.form.submit()">
                                    {foreach from=$MODULES item=value key=key}
                                        {if $value.active && !$value.system}
                                            {if $eJob.JOB_TYPE == $key}
                                                <option value="{$key}" selected>{$value.description}</option>
                                            {else}
                                                <option value="{$key}">{$value.description}</option>
                                            {/if}
                                        {/if}
                                    {/foreach}
                                </select>
                            </td>
                        </tr>
                    {/if}

                    {include file=$jobTemplate}

                    <tr>
                        <td colspan="3" align="center">
                            <button type="submit">
                                <span class="glOk" />
                            </button>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
    </div>
{elseif isset( $editVars )}
    <div id="editForm" class="editForm">
        <div>
            <p> 
                <a href="#editForm" class="close">x</a>
            </p>

            <form method="post" action="buildTemplate.php">
                <input type="hidden" name="pageID" value="{$pageID}" />
                <input type="hidden" name="templateID" value="{$template.OID}" />
                <input type="hidden" name="varsOnly" value="{$varsOnly}" />

                <table>
                    <tr>
                        <th>Name</th>
                        <th>Wert</th>
                    </tr>

                    {foreach from=$tplVars item=row name=variables}
                        <tr>
                            <td>
                                {if $smarty.foreach.variables.last}
                                    <input type="hidden" name="maxVars" value="{$smarty.foreach.variables.iteration}" />
                                {/if}

                                {if $row.PARENT_OID == $template.OID}
                                    <input type="text" size="30" name="varName_{$smarty.foreach.variables.iteration}" value="{$row.VAR_NAME}" />
                                {else}
                                    {$row.VAR_NAME}
                                {/if}
                            </td>
                            <td>
                                {if $row.PARENT_OID == $template.OID}
                                    <input type="text" size="40" name="varValue_{$smarty.foreach.variables.iteration}" value="{$row.VAR_VALUE}" />
                                {else}
                                    {$row.VAR_VALUE}
                                {/if}
                            </td>
                        </tr>
                    {/foreach}

                    <tr>
                        <td><input type="text" size="30" name="varName_0" /></td>
                        <td><input type="text" size="40" name="varValue_0" /></td>
                    </tr>
                    <tr>
                        <td align="center" colspan="2">
                            <button type="submit">
                                <span class="glOk" />
                            </button>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
    </div>
{/if}

        <h2 align="center">{$template.OBJECT_NAME}</h2>

        <p align="center">
            <a href="overview.php?parentID={$template.PARENT}&pageID={$pageID}">zur&uuml;ck</a>
        </p>

        <p align="center">
            <object data="buildTemplateImage.php?tid={$template.OID}&pageID={$pageID}&varsOnly={$varsOnly}"
                    type="image/svg+xml">
                <param name="src" value="buildTemplateImage.php" />
            </object>
        </p>

        <p align="center">
            <a href="overview.php?parentID={$template.PARENT}&pageID={$pageID}">zur&uuml;ck</a>
        </p>

    </body>
</htmL>
