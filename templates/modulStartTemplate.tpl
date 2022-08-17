{if isset( $jobReference )}
    <input type="hidden" name="refMode" value="{$eJob.START_MODE}" />
    <input type="hidden" name="refVars" value="{$eJob.VAR_MODE}" />
    <input type="hidden" name="refRun" value="{$eJob.RUN_MODE}" />

    <tr>
        <td>Modus, bei laufender Referenz</td>
        <td>{$refModes[ $eJob.START_MODE ]}</td>
    </tr>
    <tr>
        <td>Modus beim Start der Referenz</td>
        <td>{$refRuns[ $eJob.RUN_MODE ]}</td>
    </tr>
    <tr>
        <td>Weitergabe von Variablen des Templates</td>
        <td>{$refVars[ $eJob.VAR_MODE ]}</td>
    </tr>
    <tr>
        <td>Referenziertes Template</td>
        <td>
            <select name="ReferenceFound" onChange="this.form.submit()">
                <option selected>&nbsp;</option>

                {foreach from=$jobReference item=ref}
                    <option value="{$ref.OID}">({$ref.OID}) {$ref.OBJECT_NAME}</option>
                {/foreach}
            </select>
        </td>
    </tr>
{else}
    <tr>
        <td>Modus, bei laufender Referenz</td>
        <td>
            <select name="refMode">
                {foreach from=$refModes key=mode item=value}
                    {if $eJob.START_MODE == $mode}
                        <option value="{$mode}" selected>{$value}</option>
                    {else}
                        <option value="{$mode}">{$value}</option>
                    {/if}
                {/foreach}
            </select>
        </td>
    </tr>
    <tr>
         <td>Modus beim Start der Referenz</td>
         <td>
             <select name="refRun">
                 {foreach from=$refRuns key=mode item=value}
                     {if $eJob.RUN_MODE == $mode}
                         <option value="{$mode}" selected>{$value}</option>
                     {else}
                         <option value="{$mode}">{$value}</option>
                     {/if}
                 {/foreach}
             </select>
         </td>
    </tr>
    <tr>
        <td>Weitergabe von Variablen des Templates</td>
        <td>
            <select name="refVars">
                {foreach from=$refVars key=mode item=value}
                    {if $eJob.VAR_MODE == $mode}
                        <option value="{$mode}" selected>{$value}</option>
                    {else}
                        <option value="{$mode}">{$value}</option>
                    {/if}
                {/foreach}
            </select>
        </td>
    </tr>
    <tr>
        <td>Referenziertes Template</td>
        <td>
            <input type="text" name="jobReference" size="35" value="{$eJob.TEMPLATE_REFERENCE}" />
            <button type="submit" name="findReference" value="1">
                <span class="glLupe" />
            </button>
        </td>
    </tr>
{/if}
