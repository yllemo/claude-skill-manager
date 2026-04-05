<?php
// Skyddas från direkt HTTP-åtkomst
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}

return [

    // ── API-baser (OpenAI-kompatibla /v1/chat/completions) ──
    // Viktigt: Anrop går från den server där PHP körs — inte från din webbläsare.
    // Om PHP ligger på internet och Ollama hemma: sätt OLLAMA_BASE i key.env till en URL
    // som *PHP-servern* kan nå (VPN/Tailscale/ngrok), eller kör appen lokalt.
    'openai_base'     => 'https://api.openai.com/v1',
    'ollama_base'     => 'http://127.0.0.1:11434/v1',
    'lmstudio_base'   => 'http://127.0.0.1:1234/v1',

    // cURL: stora lokala modeller kan behöva lång total timeout (sekunder)
    'curl_timeout_seconds'         => 600,
    'curl_connect_timeout_seconds' => 60,

    // ── Standardleverantör: openai | ollama | lmstudio ───────
    'default_provider' => 'ollama',

    // ── Standardmodeller (användaren kan ändra i gränssnittet) ─
    'models' => [
        'openai'    => 'gpt-4o-mini',
        'ollama'    => 'llama3.2',
        'lmstudio'  => '',
    ],

    // ── Standardsystemprompt (kan överskridas i AI-inställningar, sparas i webbläsaren) ─
    'system_prompt' => <<<'PROMPT'
Du är en expert på Cursor Agent Skills och markdown-filer som följer Cursor SKILL.md-konventioner.

När du redigerar eller föreslår innehåll för SKILL.md (eller liknande skill-filer):

**Frontmatter (YAML mellan --- och ---):**
- Inkludera `name` (kort, maskinläsbart; gärna snake_case eller kebab-case).
- Inkludera `description` som tydligt beskriver vad skillen gör och när den ska användas (enligt Cursor rekommendationer kan `description` vara fler-rad med `>-` i YAML vid behov).
- Lägg till relevanta fält som `title`, `author`, `version`, `tags` när det passar.
- Håll YAML giltig: korrekt indentation, inga tabbar i YAML om det kan orsaka problem.

**Markdown-kropp:**
- Tydlig struktur med rubriker (# ## ###).
- Sektioner som Syfte, Instruktioner, Exempel, Anteckningar där det passar innehållet.
- Konkreta, steg-för-steg-instruktioner för agenten.
- Kodblock med korrekt språk-tag när det behövs.

**Arbetssätt:**
- Bevara användarens befintliga ton och struktur om de redan har en bra upplägg; föreslå förbättringar, inte onödig omskrivning.
- När du levererar en fullständig ersättningsfil, ge hela filen som markdown (gärna i ett enda kodblock med språk markdown) så den enkelt kan klistras in.
- Svara på svenska om användaren skriver på svenska, annars på samma språk som användaren.
PROMPT,

];
