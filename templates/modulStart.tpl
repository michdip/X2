<input type="hidden" name="editStartTime" value="{$eJob.STID}" />

<tr>
    <th colspan="3">Start</th>
</tr>
<tr>
    <td colspan="2">
        {if $eJob.START_MODE == 'manual'}
            <input type="radio" name="startTime" value="manual" checked />
        {else}
            <input type="radio" name="startTime" value="manual" />
        {/if}
        manuell
    </td>
<tr>
    <td colspan="2">
        {if $eJob.START_MODE == 'int'}
            <input type="radio" name="startTime" value="int" checked />
        {else}
            <input type="radio" name="startTime" value="int" />
        {/if}
        alle <input type="text" name="timeDelta" value="{$eJob.SEKUNDEN}" size="5" /> Sekunden
    </td>
</tr>
<tr>
    <td>
        {if $eJob.START_MODE == 'weekday'}
            <input type="radio" name="startTime" value="weekday" checked />
        {else}
            <input type="radio" name="startTime" value="weekday" />
        {/if}
        <select name="dow">
            {foreach from=$dows item=txt key=ky}
                {if $eJob.DAY_OF_WEEK == $ky}
                    <option value="{$ky}" selected>{$txt.long}</option>
                {else}
                    <option value="{$ky}">{$txt.long}</option>
                {/if}
            {/foreach}
        </select>
    </td>
    <td rowspan="2">
        um <input type="text" name="startH" size="3" value="{$eJob.START_HOUR}" /> h <input type="text" name="startM" size="3" value="{$eJob.START_MINUTES}" /> m
    </td>
</tr>
<tr>
    <td>
        {if $eJob.START_MODE == 'day'}
            <input type="radio" name="startTime" value="day" checked />
        {else}
            <input type="radio" name="startTime" value="day" />
        {/if}
        am <input type="text" name="dom" size="3" value="{$eJob.DAY_OF_MONTH}" />. Tag im Monat
    </td>
</tr>
<tr>
    <td>Gültig von</td>
    <td colspan="2">
        <input type="text" name="vFrom" class="datepicker" size="12" value="{$eJob.VALIDFROM}"> 0:00 Uhr</td>
    </td>
</tr>
<tr>
    <td>Gültig bis</td>
    <td colspan="2">
        <input type="text" name="vUntil" class="datepicker" size="12" value="{$eJob.VALIDUNTIL}"> 0:00 Uhr</td>
    </td>
</tr>
