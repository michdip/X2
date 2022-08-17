<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        {foreach from=$css item=file}
            <link href="{$ROOT_URL}/lib/css/{$file}" rel="stylesheet" type="text/css" />
        {/foreach}

        {foreach from=$js item=file}
            <script src="{$ROOT_URL}/lib/js/{$file}"></script>
        {/foreach}
    </head>
    <body>
