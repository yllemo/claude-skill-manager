<?php
// Skyddas från direkt HTTP-åtkomst
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}

return [

    // Tillåt åtkomst utan inloggning (false = allt kräver inloggning)
    'allow_guest_access' => true,

    // Respektera visibility i SKILL.md frontmatter (public / internal)
    'use_skill_visibility' => true,

    // Standard för nya skills och när visibility saknas i frontmatter: public | internal
    'default_skill_visibility' => 'public',

];
