<?php
// Skyddas från direkt HTTP-åtkomst
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}

/**
 * UI-språk och valfria överskridningar av texter.
 *
 * - locale: aktivt språk (inbyggt: sv, en). För fler språk: lägg till nycklar under 'strings'.
 * - strings: per språkkod, samma nycklar som i _lang.php (t.ex. index.hdr_overview).
 *
 * Om denna fil saknas används svenska (locale sv) med inbyggda strängar i _lang.php.
 */
return [
    'locale' => 'sv',

    'strings' => [
        // 'sv' => [
        //     'index.hdr_overview' => 'Översikt',
        // ],
        // 'en' => [
        //     'index.hdr_overview' => 'Overview',
        // ],
        // Exempel tyska — sätt 'locale' => 'de' och fyll i alla nycklar du behöver (saknade faller tillbaka på sv):
        // 'de' => [
        //     'common.home' => 'Start',
        // ],
    ],
];
