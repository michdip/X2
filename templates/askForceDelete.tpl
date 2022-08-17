{if isset( $askForceDelete )}
    <div id="editForm" class="editForm">
        <div>
            <p>
                <a href="#editForm" class="close">x</a>

                <form method="post" action="overview.php">
                    <input type="hidden" name="parentID" value="{$parentID}" />
                    <input type="hidden" name="pageID" value="{$pageID}" />
                    <input type="hidden" name="deleteTemplateForced" value="{$askForceDelete}" />

                    <p align="center">willst du das Template wirklich löschen</p>

                    {foreach from=$templates item=template}
                        {if $template.OID == $askForceDelete}
                            <p align="center">
                                <input type="checkbox" name="reallyDelete"> ( {$template.OID} ) {$template.OBJECT_NAME}
                            </p>
                        {/if}
                    {/foreach}

                    <p align="center">
                        <button type="submit" >
                            <span class="glOk" />
                        </button>
                    </p>
                </form>
            </p>
        </div>
    </div>
{/if}
