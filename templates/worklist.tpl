{include file='header.tpl'}

{if isset( $edit )}
    <div id="editForm" class="editForm">
        <div>
            <p>
                <a href="#editForm" class="close">x</a>
            </p>

            <form method="post" action="worklist.php">
                <input type="hidden" name="pageID" value="{$pageID}" />
                <input type="hidden" name="exeID" value="{$exeID}" />
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
{/if}

        <h2 align="center">{$template.OBJECT_NAME}</h2>

        <p align="center">
            <a href="overview.php?parentID={$template.PARENT}&pageID={$pageID}">zur&uuml;ck</a>
        </p>

        <p align="center">
            <object data="worklistImage.php?{$get}"
                    type="image/svg+xml">
                <param name="src" value="worklistImag.php" />
            </object>
        </p>

        <p align="center">
            <a href="overview.php?parentID={$template.PARENT}&pageID={$pageID}">zur&uuml;ck</a>
        </p>

    </body>
</htmL>
