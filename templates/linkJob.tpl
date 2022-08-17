{include file='header.tpl'}

        <h2 align="center">{$template.OBJECT_NAME}</h2>

        <p align="center">
            {if $mode == 'JOB'}
                <a href="buildTemplate.php?pageID={$pageID}&tid={$template.OID}">zur&uuml;ck</a>
            {else if $mode == 'WORK'}
                <a href="worklist.php?pageID={$pageID}&wid={$exeID}">zur&uuml;ck</a>
            {/if}
        </p>

        <p align="center">
            {if $mode == 'JOB'}
                <object data="linkJobImage.php?tid={$template.OID}&pageID={$pageID}&mode={$mode}&oid={$linkJob}"
                        type="image/svg+xml">
            {else if $mode == 'WORK'}
                <object data="linkJobImage.php?tid={$template.OID}&pageID={$pageID}&mode={$mode}&oid={$linkJob}&exeID={$exeID}"
                        type="image/svg+xml">
            {/if}
                <param name="src" value="linkJobImage.php" />
            </object>
        </p>

        <p align="center">
            {if $mode == 'JOB'}
                <a href="buildTemplate.php?pageID={$pageID}&tid={$template.OID}">zur&uuml;ck</a>
            {else if $mode == 'WORK'}
                <a href="worklist.php?pageID={$pageID}&wid={$exeID}">zur&uuml;ck</a>
            {/if}
        </p>

    </body>
</htmL>
