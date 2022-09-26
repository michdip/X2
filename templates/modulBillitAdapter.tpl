{include file='modulCommand.tpl'}

<tr>
    <td>BMQ-Instanz</td>
    <td>
        <input type="hidden" name="bmqInstanceChanged" id="bmqInstanceChanged" value="false" />
        <select name="bmqInstance" onChange="document.getElementById( 'bmqInstanceChanged' ).value = 'true'; this.form.submit( );" >
            {foreach from=$bmqInstances item=inst}
                {if $eJob.INSTANCE == $inst}
                    <option selected>{$inst}</option>
                {else}
                    <option>{$inst}</option>
                {/if}
            {/foreach}
        </select>
    </td>
</tr>
<tr>
    <td>BMQ-Modul</td>
    <td>
        <select name="bmqModul">
            {foreach from=$bmqModul item=modul}
                {if $eJob.MODUL == $modul}
                    <option selected>{$modul}</option>
                {else}
                    <option>{$modul}</option>
                {/if}
            {/foreach}
        </select>
    </td>
</tr>
<tr>
    <td>BMQ-Prüfzyklus</td>
    <td>
        <input type="text" size="5" name="bmqCheckTime" value="{$eJob.BMQ_CHECK_TIME}" />
    </td>
</tr>
<tr>
    <td>Min BMQ-Messages</td>
    <td>
        <input type="text" size="10" name="minBmqMessages" value="{$eJob.MIN_MESSAGES}" />
    </td>
</tr>
<tr>
    <td>X2 BMQ-Vorgänger</td>
    <td>
        <select name="bmqProducer">
            {foreach from=$parallelJob item=pJob}
                {if $eJob.PRODUCER == $pJob.OID}
                    <option value="{$pJob.OID}" selected>({$pJob.OID}) {$pJob.JOB_NAME}</option>
                {else}
                    <option value="{$pJob.OID}">({$pJob.OID}) {$pJob.JOB_NAME}</option>
                {/if}
            {/foreach}
        </select>
    </td>
</tr>

