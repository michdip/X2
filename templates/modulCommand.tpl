{function name=getInputValue inputValue=''}
    value="{$inputValue|replace:'"':'&quot;'}"
{/function}

{if isset( $matches )}
    <input type="hidden" name="host" value="{$eJob.HOST}" />
    <input type="hidden" name="source" value="{$eJob.SOURCE}" />
    <input type="hidden" name="execpath" value="{$eJob.EXEC_PATH}" />

    {if isset( $cmdOpts.instances ) && $cmdOpts.instances}
        <input type="hidden" name="instances" value="{$eJob.INSTANCES}" />
    {/if}

    {if isset( $cmdOpts.retries ) && $cmdOpts.retries}
        <input type="hidden" name="retries" value="{$eJob.RETRIES}" />
        <input type="hidden" name="retryTime" value="{$eJob.RETRY_TIME}" />
    {/if}

    <tr>
        <td>Host</td>
        <td>{$eJob.HOST}</td>
    </tr>
    <tr>
        <td>SH-Umgebung</td>
        <td>{$eJob.SOURCE}</td>
    </tr>
    <tr>
        <td>EXE-Pfad</td>
        <td>{$eJob.EXEC_PATH}</td>
    </tr>
    {if isset( $cmdOpts.instances ) && $cmdOpts.instances}
        <tr>
            <td>Instanzen</td>
            <td>{$eJob.INSTANCES}</td>
        </tr>
    {/if}
    {if isset( $cmdOpts.retries ) && $cmdOpts.retries}
        <tr>
            <td>Retries</td>
            <td>{$eJob.RETRIES}</td>
        </tr>
        <tr>
            <td>Retry-Verz&ouml;gerung</td>
            <td>{$eJob.RETRY_TIME}</td>
        </tr>
    {/if}
    <tr>
        <td>Befehl</td>
        <td>
            <select name="patternFound" onChange="this.form.submit()">
                {foreach from=$matches item=match}
                    <option>{$match}</option>
                {/foreach}
            </select>
        </td>
    </tr>
{else}
    <tr>
        <td>Host</td>
        <td>
            <select name="host">
                {foreach from=$hosts key=host item=dummy}
                    {if $eJob.HOST == $host}
                        <option selected>{$host}</option>
                    {else}
                        <option>{$host}</option>
                    {/if}
                {/foreach}
            </select>
        </td>
    </tr>
    <tr>
        <td>SH-Umgebung</td>
        <td>
            <input type="text" name="source" size="85" value="{$eJob.SOURCE}" />
        </td>
    </tr>
    <tr>
        <td>EXE-Pfad</td>
        <td>
            <input type="text" name="execpath" size="85" value="{$eJob.EXEC_PATH}" />
        </td>
    </tr>
    {if isset( $cmdOpts.instances ) && $cmdOpts.instances}
        <tr>
            <td>Instanzen</td>
            <td>
                <select name="instances">
                    {for $i=1 to 10}
                        {if $i == $eJob.INSTANCES}
                            <option selected>{$i}</option>
                        {else}
                            <option>{$i}</option>
                        {/if}
                    {/for}
                </select>
            </td>
        </tr>
    {/if}
    {if isset( $cmdOpts.retries ) && $cmdOpts.retries}
        <tr>
            <td>Retries</td>
            <td>
                <input type="text" name="retries" size="10" value="{$eJob.RETRIES}" />
            </td>
        </tr>
        <tr>
            <td>Retry-Verz&ouml;gerung</td>
            <td>
                <input type="text" name="retryTime" size="10" value="{$eJob.RETRY_TIME}" />
            </td>
        </tr>
    {/if}
    <tr>
        <td>Befehl</td>
        <td>
            {if isset( $cmdOpts.findPattern ) && $cmdOpts.findPattern}
                <input type="text" name="command" size="80" {getInputValue inputValue=$eJob.COMMAND} />

                <button type="submit" name="findPattern" value="1">
                    <span class="glLupe" />
                </button>
            {else}
                <input type="text" name="command" size="85" {getInputValue inputValue=$eJob.COMMAND} />
            {/if}
        </td>
    </tr>
{/if}
