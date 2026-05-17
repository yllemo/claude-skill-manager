<?php
// Skyddas från direkt HTTP-åtkomst
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}

/**
 * Tillåtna filtyper i .skill-arkiv (uppladdning, visning, redigering).
 *
 * - allowed_extensions: filändelser som får finnas i arkiv vid uppladdning
 * - text_extensions: läses som text och kan redigeras i Monaco (prioriteras före bilder)
 * - image_extensions: visas som bild i viewer (base64, max 512 KB i read_zip_files)
 * - mime_types: MIME vid öppning i ny flik (/view) och för bild-data-URL:er
 */
return [

    'allowed_extensions' => [
        // Dokument & data
        'md', 'mdx', 'txt', 'rst', 'csv', 'tsv', 'json', 'jsonl', 'ndjson', 'xml', 'yml', 'yaml', 'toml',
        'sef', // Smart Exam Format
        // Webb & kod
        'html', 'htm', 'svg', 'css', 'scss', 'less',
        'js', 'mjs', 'cjs', 'ts', 'jsx', 'tsx', 'vue', 'svelte',
        'py', 'rb', 'php', 'go', 'rs', 'java', 'kt', 'cs', 'lua', 'r', 'sql', 'sh', 'bash', 'ps1',
        'graphql', 'gql', 'hcl', 'tf',
        // Konfiguration
        'ini', 'cfg', 'conf', 'env', 'properties',
        // Bilder
        'png', 'jpg', 'jpeg', 'gif', 'webp',
    ],

    'text_extensions' => [
        'md', 'mdx', 'txt', 'rst', 'csv', 'tsv', 'json', 'jsonl', 'ndjson', 'xml', 'yml', 'yaml', 'toml',
        'sef',
        'html', 'htm', 'svg', 'css', 'scss', 'less',
        'js', 'mjs', 'cjs', 'ts', 'jsx', 'tsx', 'vue', 'svelte',
        'py', 'rb', 'php', 'go', 'rs', 'java', 'kt', 'cs', 'lua', 'r', 'sql', 'sh', 'bash', 'ps1',
        'graphql', 'gql', 'hcl', 'tf',
        'ini', 'cfg', 'conf', 'env', 'properties',
    ],

    'image_extensions' => [
        'png', 'jpg', 'jpeg', 'gif', 'webp',
    ],

    'mime_types' => [
        'md'     => 'text/markdown',
        'mdx'    => 'text/markdown',
        'txt'    => 'text/plain',
        'rst'    => 'text/plain',
        'csv'    => 'text/csv',
        'tsv'    => 'text/tab-separated-values',
        'json'   => 'application/json',
        'jsonl'  => 'application/json',
        'ndjson' => 'application/x-ndjson',
        'xml'    => 'application/xml',
        'yml'    => 'text/yaml',
        'yaml'   => 'text/yaml',
        'toml'   => 'application/toml',
        'sef'    => 'application/vnd.smart-exam', // justera om SEF är JSON: application/json
        'html'   => 'text/html',
        'htm'    => 'text/html',
        'svg'    => 'image/svg+xml',
        'css'    => 'text/css',
        'scss'   => 'text/css',
        'less'   => 'text/css',
        'js'     => 'text/javascript',
        'mjs'    => 'text/javascript',
        'cjs'    => 'text/javascript',
        'ts'     => 'text/typescript',
        'jsx'    => 'text/javascript',
        'tsx'    => 'text/typescript',
        'vue'    => 'text/plain',
        'svelte' => 'text/plain',
        'py'     => 'text/x-python',
        'rb'     => 'text/x-ruby',
        'php'    => 'application/x-php',
        'go'     => 'text/x-go',
        'rs'     => 'text/x-rust',
        'java'   => 'text/x-java',
        'kt'     => 'text/x-kotlin',
        'cs'     => 'text/x-csharp',
        'lua'    => 'text/x-lua',
        'r'      => 'text/x-r',
        'sql'    => 'application/sql',
        'sh'     => 'text/x-shellscript',
        'bash'   => 'text/x-shellscript',
        'ps1'    => 'text/x-powershell',
        'graphql'=> 'application/graphql',
        'gql'    => 'application/graphql',
        'hcl'    => 'text/plain',
        'tf'     => 'text/plain',
        'ini'    => 'text/plain',
        'cfg'    => 'text/plain',
        'conf'   => 'text/plain',
        'env'    => 'text/plain',
        'properties' => 'text/plain',
        'png'    => 'image/png',
        'jpg'    => 'image/jpeg',
        'jpeg'   => 'image/jpeg',
        'gif'    => 'image/gif',
        'webp'   => 'image/webp',
    ],

];
