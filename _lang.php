<?php
declare(strict_types=1);

/**
 * UI-språk: inbyggda strängar (sv/en) + valfri config/lang.php.
 * Anropa __( 'nyckel' ) eller __( 'nyckel', [ 'name' => 'värde' ] ) för platshållare {name}.
 */

function skill_lang_user_config(): array {
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $path = __DIR__ . '/config/lang.php';
    $c = is_file($path) ? (array)(require $path) : [];

    return $c;
}

/** Aktiv språkkod: t.ex. sv, en — fler språk läggs i config/lang.php under 'strings'. */
function skill_locale(): string {
    static $loc = null;
    if ($loc !== null) {
        return $loc;
    }
    $cfg = skill_lang_user_config();
    $loc = strtolower(trim((string)($cfg['locale'] ?? 'sv')));
    if ($loc === '') {
        $loc = 'sv';
    }

    return $loc;
}

/** html lang-attribut */
function skill_lang_html_lang(): string {
    $l = skill_locale();
    if (preg_match('/^[a-z]{2}(-[a-z]{2})?$/i', $l)) {
        return strtolower(str_replace('_', '-', $l));
    }

    return 'sv';
}

/**
 * @return array<string, array<string, string>>
 */
function skill_lang_builtin_all(): array {
    static $all = null;
    if ($all !== null) {
        return $all;
    }
    $sv = [
        // gemensamt
        'common.logo_sub' => '.skill filer',
        'common.nav_subtitle' => 'Navigation',
        'common.theme_toggle' => 'Växla tema',
        'common.theme_title' => 'Tema',
        'common.menu' => 'Meny',
        'common.home' => 'Hem',
        'common.back' => 'Tillbaka',
        'common.logout' => 'Logga ut',
        'common.login' => 'Logga in',
        'common.save' => 'Spara',
        'common.close' => 'Stäng',
        'common.help' => 'Hjälp',
        'common.download' => 'Ladda ner',
        'common.delete' => 'Radera',
        'common.confirm_logout' => 'Logga ut?',
        'common.edit' => 'Redigera',
        'common.view' => 'Visa',
        'common.start' => 'Start',
        'common.tools' => 'Verktyg',
        'common.show_tools' => 'Visa verktyg',
        'common.footer_format' => '.skill format',
        'common.error_prefix' => 'Fel: ',
        'common.error_network' => 'Nätverksfel: ',
        'common.error_network_local' => 'Nätverksfel (lokalt): ',
        // index
        'index.hdr_manage' => 'Hantera skills',
        'index.hdr_overview' => 'Översikt',
        'index.btn_new_skill' => 'Ny skill',
        'index.btn_all_content' => 'Allt innehåll',
        'index.btn_all_content_title' => 'Ladda ner alla filer i content/ som zip',
        'index.search_placeholder' => 'Sök på titel, beskrivning, taggar, författare…',
        'index.filter_all_tags' => 'Alla taggar',
        'index.upload_label' => 'Ladda upp .skill / .zip',
        'index.upload_title' => 'Endast .md och .txt i arkiven',
        'index.col_title' => 'Titel',
        'index.col_tags' => 'Taggar',
        'index.col_author' => 'Författare',
        'index.col_files' => 'Filer',
        'index.col_size' => 'Storlek',
        'index.col_modified' => 'Ändrad',
        'index.col_actions' => 'Åtgärder',
        'index.btn_view' => 'Visa',
        'index.dl_aria' => 'Ladda ner',
        'index.dl_as_skill' => 'Som .skill',
        'index.dl_as_zip' => 'Som .zip',
        'index.confirm_delete' => 'Radera {name}?',
        'index.empty' => 'Inga .skill-filer hittades.',
        'index.empty_upload' => 'Ladda upp en befintlig eller <a href="edit/">skapa en ny</a>.',
        'index.no_results' => 'Inga skills matchar din sökning.',
        'index.sort_modified_desc' => 'Senast ändrad ↓',
        'index.sort_modified_asc' => 'Senast ändrad ↑',
        'index.sort_title_asc' => 'Titel A–Ö',
        'index.sort_title_desc' => 'Titel Ö–A',
        'index.sort_tags_asc' => 'Taggar A–Ö',
        'index.sort_tags_desc' => 'Taggar Ö–A',
        'index.sort_author_asc' => 'Författare A–Ö',
        'index.sort_author_desc' => 'Författare Ö–A',
        'index.sort_files_asc' => 'Filer ↑',
        'index.sort_files_desc' => 'Filer ↓',
        'index.sort_size_asc' => 'Storlek ↑',
        'index.sort_size_desc' => 'Storlek ↓',
        'index.msg_upload_ok' => 'Filen <strong>{file}</strong> laddades upp.',
        'index.msg_upload_invalid' => 'Kunde inte spara filen. Kontrollera rättigheter på /content/.',
        'index.msg_zip_ok' => 'Zip-filen sparades som <strong>{file}</strong> (endast .md och .txt ingår).',
        'index.msg_wrong_ext' => 'Endast .skill- eller .zip-filer tillåts.',
        'index.msg_upload_err' => 'Uppladdningsfel (kod {code}).',
        'index.msg_deleted' => 'Filen raderades.',
        'index.msg_delete_fail' => 'Kunde inte radera filen.',
        'index.js_result_all' => '{n} skills',
        'index.js_result_one' => '1 skill',
        'index.js_result_partial' => '{visible} av {total} visas',
        // login
        'login.title' => 'Logga in',
        'login.prompt' => 'Ange lösenord för att fortsätta',
        'login.password_label' => 'Lösenord',
        'login.password_placeholder' => '••••••••',
        'login.submit' => 'Logga in',
        'login.error' => 'Fel lösenord. Försök igen.',
        // download_content
        'dlc.err_zip' => 'ZipArchive saknas på servern.',
        'dlc.err_no_content' => 'Content-katalogen saknas.',
        'dlc.err_tmp' => 'Kunde inte skapa temporär fil.',
        'dlc.err_zip_create' => 'Kunde inte skapa zip-arkiv.',
        'dlc.err_read' => 'Kunde inte läsa arkivet.',
        // view
        'view.logo_sub' => 'Visa skill',
        'view.sidebar_files' => 'Filer',
        'view.sidebar_toggle_tree' => 'Visa filträd',
        'view.download_btn' => 'Ladda ner ▾',
        'view.download_aria' => 'Ladda ner',
        'view.mobile_dl_skill' => 'Ladda ner som .skill',
        'view.mobile_dl_zip' => 'Ladda ner som .zip',
        'view.files_count' => '{n} filer · {size}',
        'view.section_description' => 'Beskrivning',
        'view.section_metadata' => 'Metadata',
        'view.section_tags' => 'Taggar',
        'view.breadcrumb_pick' => 'Välj en fil till vänster',
        'view.empty_pick' => 'Välj en fil i trädet till vänster.',
        'view.seg_rendered' => 'Renderad',
        'view.seg_raw' => 'Raw',
        'view.copy' => 'Kopiera',
        'view.copied' => 'Kopierat',
        'view.mermaid_fs_aria' => 'Mermaid helskärm',
        'view.mermaid_tab_diagram' => 'Diagram',
        'view.mermaid_tab_code' => 'Kod',
        'view.mermaid_hint' => 'Pan: dra med musen · Zoom: mushjul · Dubbelklick: passa in · Esc: stäng',
        'view.mermaid_fit' => 'Passa in',
        'view.fm_label' => '📋 Frontmatter / Metadata',
        'view.binary_file' => 'Fil',
        'view.binary_path' => 'Sökväg',
        'view.binary_size' => 'Storlek',
        'view.binary_type' => 'Typ',
        'view.binary_kind' => 'Binärfil',
        'view.mermaid_wrap_title' => 'Markera text i diagrammet · knappen uppe till höger öppnar helskärm',
        'view.mermaid_fs_open_aria' => 'Öppna diagram i helskärm',
        'view.mermaid_fs_open_title' => 'Helskärm (pan/zoom, markera text)',
        'view.mermaid_error' => 'Mermaid-fel: ',
        // edit
        'edit.logo_sub_new' => 'Ny skill',
        'edit.logo_sub_edit' => 'Redigera',
        'edit.switch_ai' => 'Byt till AI-redigering',
        'edit.switch_ai_title_new' => 'Byt till AI-redigering (ny tom skill)',
        'edit.switch_ai_title' => 'Öppna samma arkiv i AI-redigering',
        'edit.help_title' => 'Vad är en .skill-fil?',
        'edit.page_new' => 'Ny skill',
        'edit.page_edit' => 'Redigera: {name}',
        'edit.error_name' => 'Ange ett namn för skill-filen.',
        'edit.sidebar_filename' => 'Filnamn (.skill)',
        'edit.placeholder_name' => 'min-skill',
        'edit.btn_add_file' => '+ Fil',
        'edit.add_file_title' => 'Lägg till fil – egen sökväg eller standardmappar',
        'edit.tree_header' => 'Filer i arkivet',
        'edit.current_file_loading' => 'Väljer fil…',
        'edit.rename_btn' => 'Namn',
        'edit.rename_title' => 'Byt namn eller flytta (sökväg i arkivet)',
        'edit.delete_btn' => 'Ta bort',
        'edit.delete_title' => 'Ta bort fil från arkivet',
        'edit.template' => 'Mall',
        'edit.format' => 'Formatera',
        'edit.editor_pane' => 'Editor',
        'edit.editor_hint' => 'Ctrl+Space · filreferenser',
        'edit.editor_hint_title' => 'Visar alla andra filer i arkivet som Markdown-länk eller sökväg',
        'edit.preview_pane' => 'Förhandsgranskning',
        'edit.help_modal_title' => 'Vad är en .skill-fil?',
        'edit.add_file_modal_title' => '+ Fil i arkivet',
        'edit.add_custom_h' => 'Egen sökväg',
        'edit.add_custom_d' => 'Skapa en tom fil var du vill i arkivet. Ange t.ex. <code>references/guide.md</code>, <code>docs/README.md</code> eller <code>SKILL.md</code>.',
        'edit.add_path_btn' => 'Ange sökväg…',
        'edit.structure_intro' => '<strong>Standardmappar:</strong> välj en grupp för att skapa tomma mallfiler (befintliga sökvägar hoppas över). I en <code>.skill</code>-zip finns inga riktiga kataloger — sökvägar som <code>references/guide.md</code> skapar bara logiska mappar i trädet. Tabellen under beskriver <strong>syfte</strong> och hur innehåll typiskt <strong>laddas</strong> i agenten (on-demand jämfört med att köra skript).',
        'edit.add_all_missing' => 'Lägg till alla saknade filer',
        'edit.nav_back' => 'Tillbaka',
        'edit.nav_tools' => 'Visa verktyg',
        'edit.nav_switch_ai' => 'Byt till AI-redigering',
        'edit.nav_view' => 'Visa skill',
        'edit.tree_rename' => 'Byt namn / flytta',
        'edit.tree_rename_aria' => 'Byt namn',
        'edit.tree_delete' => 'Ta bort fil',
        'edit.tree_delete_aria' => 'Ta bort',
        'edit.group_purpose' => 'Syfte: ',
        'edit.group_loaded' => 'Laddas: ',
        'edit.group_add' => 'Lägg till ',
        'edit.alert_filename' => 'Ange ett filnamn för skill-filen.',
        'edit.alert_no_files' => 'Det finns inga filer att spara.',
        'edit.prompt_new_file' => 'Nytt filnamn i arkivet\n(t.ex. references/README.md):',
        'edit.alert_bad_name' => 'Ogiltigt filnamn.',
        'edit.alert_all_exist' => 'Alla dessa filer finns redan i arkivet.',
        'edit.alert_min_file' => 'Arkivet måste innehålla minst en fil.',
        'edit.confirm_remove' => 'Ta bort filen «{path}» från arkivet?',
        'edit.prompt_rename' => 'Ny sökväg i arkivet (nytt namn eller mapp, t.ex. docs/guide.md):',
        'edit.alert_bad_path' => 'Ogiltig sökväg.',
        'edit.alert_path_exists' => 'Det finns redan en fil med den sökvägen.',
        'edit.template_confirm' => 'Ersätt innehållet med mallen?',
        // ai
        'ai.logo_sub' => 'AI-redigering',
        'ai.page_new' => 'Ny skill (AI)',
        'ai.page_edit' => 'AI: {name}',
        'ai.switch_classic' => 'Byt till klassisk redigering',
        'ai.switch_classic_title_new' => 'Byt till klassisk redigering (ny tom skill)',
        'ai.switch_classic_title' => 'Öppna samma arkiv i klassisk redigering',
        'ai.help_title' => 'Hjälp',
        'ai.model_placeholder' => 'modell',
        'ai.model_title' => 'Modellnamn',
        'ai.local_browser' => 'Lokal (webbläsare)',
        'ai.local_browser_title' => 'Bocka ur om PHP-servern ska proxy:a mot Ollama/LM Studio (VPN m.m.)',
        'ai.settings_btn' => 'AI-inställningar',
        'ai.settings_btn_title' => 'Systemprompt & AI',
        'ai.user_placeholder' => 'Skriv vad AI ska göra med filen — eller börja med knappen ovan…',
        'ai.attach_title' => 'Lägger in hela filens innehåll i prompten nedan',
        'ai.attach_btn' => 'Lägg aktuell fil i prompten',
        'ai.attach_hint' => 'Använd denna knapp för att skicka med innehållet från editorn till AI (t.ex. hela <code>SKILL.md</code>). Aktuell fil: ',
        'ai.panel_title' => 'Assistent',
        'ai.send' => 'Skicka',
        'ai.insert' => 'Infoga svar',
        'ai.replace_all' => 'Ersätt hela filen',
        'ai.help_modal' => 'Hjälp',
        'ai.settings_modal' => 'AI-inställningar',
        'ai.settings_intro' => 'Systemprompten styr hur modellen skriver och redigerar SKILL.md (frontmatter, struktur, stil). <strong>Ollama / LM Studio:</strong> som standard skickas frågor <strong>direkt från webbläsaren</strong> till din dator (<code>127.0.0.1</code>), så PHP-servern på internet behöver inte nå Ollama. Bocka ur &quot;Lokal (webbläsare)&quot; i verktygsraden om du vill att <code>chat.php</code> på servern ska proxy:a (kräver att <code>OLLAMA_BASE</code> i <code>key.env</code> nås från servern). <strong>OpenAI:</strong> går alltid via servern; nyckel i <code>config/key.env</code>. Vid <strong>HTTPS</strong>-sajt kan vissa webbläsare blockera anrop till <code>http://127.0.0.1</code> (mixed content) — öppna då appen via HTTP lokalt eller använd server-proxy.',
        'ai.label_ollama' => 'Lokal Ollama-URL (webbläsare)',
        'ai.label_lmstudio' => 'Lokal LM Studio-URL (webbläsare)',
        'ai.label_system' => 'Systemprompt',
        'ai.reset_prompt' => 'Återställ standard',
        'ai.save' => 'Spara',
        'ai.footer_new' => 'Ny skill',
        'ai.error_name' => 'Ange ett namn för skill-filen.',
        'ai.status_sending' => 'Skickar…',
        'ai.status_done' => 'Klar',
        'ai.err_empty_response' => 'Tomt svar från modellen.',
        'ai.err_unknown_short' => 'Okänt',
        'ai.err_invalid_answer' => 'Ogiltigt svar',
        'ai.include_block_header' => "\n\n=== Inkluderad fil: {file} ===\n",
        'ai.include_intro' => 'Här är innehållet i filen:\n',
        'ai.help_empty' => '_Ingen hjälptext hittades._',
        'ai.alert_instruction' => 'Skriv en instruktion först.',
        'ai.alert_model' => 'Ange modellnamn (eller sätt standard i config/ai.php).',
        'ai.alert_local_base' => 'Sätt bas-URL för lokal {provider} under AI-inställningar.',
        'ai.alert_no_reply' => 'Inget assistentsvar att infoga.',
        // chat API
        'chat.err_post_only' => 'Endast POST tillåts',
        'chat.err_empty' => 'Tom begäran',
        'chat.err_bad_json' => 'Ogiltig JSON',
        'chat.err_provider' => 'Ogiltig leverantör',
        'chat.err_messages' => 'messages krävs',
        'chat.err_msg_too_long' => 'Meddelandet är för långt.',
        'chat.err_no_valid_messages' => 'Inga giltiga meddelanden',
        'chat.err_no_base' => 'Bas-URL saknas i config/ai.php',
        'chat.err_no_model' => 'Ange modellnamn i fältet eller i config/ai.php',
        'chat.err_no_openai_key' => 'OPENAI_API_KEY saknas i config/key.env',
        'chat.err_unknown' => 'Okänt fel',
        // _common errors
        'err.ziparchive_missing' => 'ZipArchive saknas på servern.',
        'err.could_not_read_archive' => 'Kunde inte läsa arkivet.',
        'err.forbidden_file' => 'Otillåten fil i arkivet: {name} (endast .md och .txt tillåts vid uppladdning).',
        'err.archive_empty' => 'Arkivet innehåller inga filer.',
        'err.could_not_read_zip' => 'Kunde inte läsa zip-filen.',
        'err.could_not_create_skill' => 'Kunde inte skapa .skill-fil.',
        'err.no_md_txt' => 'Inga .md- eller .txt-filer kunde packas.',
    ];

    $en = [
        'common.logo_sub' => '.skill files',
        'common.nav_subtitle' => 'Navigation',
        'common.theme_toggle' => 'Toggle theme',
        'common.theme_title' => 'Theme',
        'common.menu' => 'Menu',
        'common.home' => 'Home',
        'common.back' => 'Back',
        'common.logout' => 'Log out',
        'common.login' => 'Log in',
        'common.save' => 'Save',
        'common.close' => 'Close',
        'common.help' => 'Help',
        'common.download' => 'Download',
        'common.delete' => 'Delete',
        'common.confirm_logout' => 'Log out?',
        'common.edit' => 'Edit',
        'common.view' => 'View',
        'common.start' => 'Home',
        'common.tools' => 'Tools',
        'common.show_tools' => 'Show tools',
        'common.footer_format' => '.skill format',
        'common.error_prefix' => 'Error: ',
        'common.error_network' => 'Network error: ',
        'common.error_network_local' => 'Network error (local): ',
        'index.hdr_manage' => 'Manage skills',
        'index.hdr_overview' => 'Overview',
        'index.btn_new_skill' => 'New skill',
        'index.btn_all_content' => 'All content',
        'index.btn_all_content_title' => 'Download all files in content/ as zip',
        'index.search_placeholder' => 'Search title, description, tags, author…',
        'index.filter_all_tags' => 'All tags',
        'index.upload_label' => 'Upload .skill / .zip',
        'index.upload_title' => 'Only .md and .txt files inside archives',
        'index.col_title' => 'Title',
        'index.col_tags' => 'Tags',
        'index.col_author' => 'Author',
        'index.col_files' => 'Files',
        'index.col_size' => 'Size',
        'index.col_modified' => 'Modified',
        'index.col_actions' => 'Actions',
        'index.btn_view' => 'View',
        'index.dl_aria' => 'Download',
        'index.dl_as_skill' => 'As .skill',
        'index.dl_as_zip' => 'As .zip',
        'index.confirm_delete' => 'Delete {name}?',
        'index.empty' => 'No .skill files found.',
        'index.empty_upload' => 'Upload an existing one or <a href="edit/">create a new one</a>.',
        'index.no_results' => 'No skills match your search.',
        'index.sort_modified_desc' => 'Last modified ↓',
        'index.sort_modified_asc' => 'Last modified ↑',
        'index.sort_title_asc' => 'Title A–Z',
        'index.sort_title_desc' => 'Title Z–A',
        'index.sort_tags_asc' => 'Tags A–Z',
        'index.sort_tags_desc' => 'Tags Z–A',
        'index.sort_author_asc' => 'Author A–Z',
        'index.sort_author_desc' => 'Author Z–A',
        'index.sort_files_asc' => 'Files ↑',
        'index.sort_files_desc' => 'Files ↓',
        'index.sort_size_asc' => 'Size ↑',
        'index.sort_size_desc' => 'Size ↓',
        'index.msg_upload_ok' => 'File <strong>{file}</strong> was uploaded.',
        'index.msg_upload_invalid' => 'Could not save the file. Check permissions on /content/.',
        'index.msg_zip_ok' => 'Zip saved as <strong>{file}</strong> (only .md and .txt are included).',
        'index.msg_wrong_ext' => 'Only .skill or .zip files are allowed.',
        'index.msg_upload_err' => 'Upload error (code {code}).',
        'index.msg_deleted' => 'File deleted.',
        'index.msg_delete_fail' => 'Could not delete the file.',
        'index.js_result_all' => '{n} skills',
        'index.js_result_one' => '1 skill',
        'index.js_result_partial' => '{visible} of {total} shown',
        'login.title' => 'Log in',
        'login.prompt' => 'Enter password to continue',
        'login.password_label' => 'Password',
        'login.password_placeholder' => '••••••••',
        'login.submit' => 'Log in',
        'login.error' => 'Wrong password. Try again.',
        'dlc.err_zip' => 'ZipArchive is not available on the server.',
        'dlc.err_no_content' => 'Content directory is missing.',
        'dlc.err_tmp' => 'Could not create temporary file.',
        'dlc.err_zip_create' => 'Could not create zip archive.',
        'dlc.err_read' => 'Could not read the archive.',
        'view.logo_sub' => 'View skill',
        'view.sidebar_files' => 'Files',
        'view.sidebar_toggle_tree' => 'Show file tree',
        'view.download_btn' => 'Download ▾',
        'view.download_aria' => 'Download',
        'view.mobile_dl_skill' => 'Download as .skill',
        'view.mobile_dl_zip' => 'Download as .zip',
        'view.files_count' => '{n} files · {size}',
        'view.section_description' => 'Description',
        'view.section_metadata' => 'Metadata',
        'view.section_tags' => 'Tags',
        'view.breadcrumb_pick' => 'Pick a file on the left',
        'view.empty_pick' => 'Select a file in the tree on the left.',
        'view.seg_rendered' => 'Rendered',
        'view.seg_raw' => 'Raw',
        'view.copy' => 'Copy',
        'view.copied' => 'Copied',
        'view.mermaid_fs_aria' => 'Mermaid fullscreen',
        'view.mermaid_tab_diagram' => 'Diagram',
        'view.mermaid_tab_code' => 'Code',
        'view.mermaid_hint' => 'Pan: drag · Zoom: wheel · Double-click: fit · Esc: close',
        'view.mermaid_fit' => 'Fit',
        'view.fm_label' => '📋 Frontmatter / metadata',
        'view.binary_file' => 'File',
        'view.binary_path' => 'Path',
        'view.binary_size' => 'Size',
        'view.binary_type' => 'Type',
        'view.binary_kind' => 'Binary file',
        'view.mermaid_wrap_title' => 'Select text in the diagram · top-right opens fullscreen',
        'view.mermaid_fs_open_aria' => 'Open diagram in fullscreen',
        'view.mermaid_fs_open_title' => 'Fullscreen (pan/zoom, select text)',
        'view.mermaid_error' => 'Mermaid error: ',
        'edit.logo_sub_new' => 'New skill',
        'edit.logo_sub_edit' => 'Edit',
        'edit.switch_ai' => 'Switch to AI editing',
        'edit.switch_ai_title_new' => 'Switch to AI editing (new empty skill)',
        'edit.switch_ai_title' => 'Open the same archive in AI editing',
        'edit.help_title' => 'What is a .skill file?',
        'edit.page_new' => 'New skill',
        'edit.page_edit' => 'Edit: {name}',
        'edit.error_name' => 'Enter a name for the skill file.',
        'edit.sidebar_filename' => 'Filename (.skill)',
        'edit.placeholder_name' => 'my-skill',
        'edit.btn_add_file' => '+ File',
        'edit.add_file_title' => 'Add file — custom path or standard folders',
        'edit.tree_header' => 'Files in archive',
        'edit.current_file_loading' => 'Selecting file…',
        'edit.rename_btn' => 'Name',
        'edit.rename_title' => 'Rename or move (path in archive)',
        'edit.delete_btn' => 'Remove',
        'edit.delete_title' => 'Remove file from archive',
        'edit.template' => 'Template',
        'edit.format' => 'Format',
        'edit.editor_pane' => 'Editor',
        'edit.editor_hint' => 'Ctrl+Space · file refs',
        'edit.editor_hint_title' => 'Lists other files in the archive as Markdown links or paths',
        'edit.preview_pane' => 'Preview',
        'edit.help_modal_title' => 'What is a .skill file?',
        'edit.add_file_modal_title' => '+ File in archive',
        'edit.add_custom_h' => 'Custom path',
        'edit.add_custom_d' => 'Create an empty file anywhere in the archive, e.g. <code>references/guide.md</code>, <code>docs/README.md</code> or <code>SKILL.md</code>.',
        'edit.add_path_btn' => 'Enter path…',
        'edit.structure_intro' => '<strong>Default folders:</strong> pick a group to add empty template files (existing paths are skipped). A <code>.skill</code> zip has no real directories — paths like <code>references/guide.md</code> only create logical folders in the tree. The table below describes <strong>purpose</strong> and how content is typically <strong>loaded</strong> in the agent (on-demand vs running scripts).',
        'edit.add_all_missing' => 'Add all missing files',
        'edit.nav_back' => 'Back',
        'edit.nav_tools' => 'Show tools',
        'edit.nav_switch_ai' => 'Switch to AI editing',
        'edit.nav_view' => 'View skill',
        'edit.tree_rename' => 'Rename / move',
        'edit.tree_rename_aria' => 'Rename',
        'edit.tree_delete' => 'Delete file',
        'edit.tree_delete_aria' => 'Delete',
        'edit.group_purpose' => 'Purpose: ',
        'edit.group_loaded' => 'Loaded: ',
        'edit.group_add' => 'Add ',
        'edit.alert_filename' => 'Enter a filename for the skill file.',
        'edit.alert_no_files' => 'There are no files to save.',
        'edit.prompt_new_file' => 'New filename in the archive\n(e.g. references/README.md):',
        'edit.alert_bad_name' => 'Invalid filename.',
        'edit.alert_all_exist' => 'All of these files already exist in the archive.',
        'edit.alert_min_file' => 'The archive must contain at least one file.',
        'edit.confirm_remove' => 'Remove file «{path}» from the archive?',
        'edit.prompt_rename' => 'New path in the archive (rename or folder, e.g. docs/guide.md):',
        'edit.alert_bad_path' => 'Invalid path.',
        'edit.alert_path_exists' => 'A file with that path already exists.',
        'edit.template_confirm' => 'Replace content with the template?',
        'ai.logo_sub' => 'AI editing',
        'ai.page_new' => 'New skill (AI)',
        'ai.page_edit' => 'AI: {name}',
        'ai.switch_classic' => 'Switch to classic editing',
        'ai.switch_classic_title_new' => 'Switch to classic editing (new empty skill)',
        'ai.switch_classic_title' => 'Open the same archive in classic editing',
        'ai.help_title' => 'Help',
        'ai.model_placeholder' => 'model',
        'ai.model_title' => 'Model name',
        'ai.local_browser' => 'Local (browser)',
        'ai.local_browser_title' => 'Uncheck if the PHP server should proxy to Ollama/LM Studio (VPN, etc.)',
        'ai.settings_btn' => 'AI settings',
        'ai.settings_btn_title' => 'System prompt & AI',
        'ai.user_placeholder' => 'Describe what the AI should do with the file — or start with the button above…',
        'ai.attach_title' => 'Inserts the full file content into the prompt below',
        'ai.attach_btn' => 'Attach current file to prompt',
        'ai.attach_hint' => 'Use this button to send editor content to the AI (e.g. full <code>SKILL.md</code>). Current file: ',
        'ai.panel_title' => 'Assistant',
        'ai.send' => 'Send',
        'ai.insert' => 'Insert reply',
        'ai.replace_all' => 'Replace entire file',
        'ai.help_modal' => 'Help',
        'ai.settings_modal' => 'AI settings',
        'ai.settings_intro' => 'The system prompt controls how the model writes and edits SKILL.md (front matter, structure, style). <strong>Ollama / LM Studio:</strong> by default requests go <strong>directly from the browser</strong> to your machine (<code>127.0.0.1</code>), so the PHP server does not need to reach Ollama. Uncheck &quot;Local (browser)&quot; in the toolbar if you want <code>chat.php</code> on the server to proxy (requires <code>OLLAMA_BASE</code> in <code>key.env</code> to be reachable from the server). <strong>OpenAI:</strong> always goes through the server; key in <code>config/key.env</code>. On <strong>HTTPS</strong> sites some browsers block calls to <code>http://127.0.0.1</code> (mixed content) — run the app over HTTP locally or use server proxy.',
        'ai.label_ollama' => 'Local Ollama URL (browser)',
        'ai.label_lmstudio' => 'Local LM Studio URL (browser)',
        'ai.label_system' => 'System prompt',
        'ai.reset_prompt' => 'Reset to default',
        'ai.save' => 'Save',
        'ai.footer_new' => 'New skill',
        'ai.error_name' => 'Enter a name for the skill file.',
        'ai.status_sending' => 'Sending…',
        'ai.status_done' => 'Done',
        'ai.err_empty_response' => 'Empty response from model.',
        'ai.err_unknown_short' => 'Unknown',
        'ai.err_invalid_answer' => 'Invalid response',
        'ai.include_block_header' => "\n\n=== Included file: {file} ===\n",
        'ai.include_intro' => 'Here is the file content:\n',
        'ai.help_empty' => '_No help text found._',
        'ai.alert_instruction' => 'Write an instruction first.',
        'ai.alert_model' => 'Enter a model name (or set default in config/ai.php).',
        'ai.alert_local_base' => 'Set base URL for local {provider} under AI settings.',
        'ai.alert_no_reply' => 'No assistant reply to insert.',
        'chat.err_post_only' => 'Only POST allowed',
        'chat.err_empty' => 'Empty request',
        'chat.err_bad_json' => 'Invalid JSON',
        'chat.err_provider' => 'Invalid provider',
        'chat.err_messages' => 'messages required',
        'chat.err_msg_too_long' => 'Message is too long.',
        'chat.err_no_valid_messages' => 'No valid messages',
        'chat.err_no_base' => 'Base URL missing in config/ai.php',
        'chat.err_no_model' => 'Enter a model name in the field or in config/ai.php',
        'chat.err_no_openai_key' => 'OPENAI_API_KEY missing in config/key.env',
        'chat.err_unknown' => 'Unknown error',
        'err.ziparchive_missing' => 'ZipArchive is not available on the server.',
        'err.could_not_read_archive' => 'Could not read the archive.',
        'err.forbidden_file' => 'Forbidden file in archive: {name} (only .md and .txt allowed on upload).',
        'err.archive_empty' => 'The archive contains no files.',
        'err.could_not_read_zip' => 'Could not read the zip file.',
        'err.could_not_create_skill' => 'Could not create .skill file.',
        'err.no_md_txt' => 'No .md or .txt files could be packed.',
    ];

    $all = ['sv' => $sv, 'en' => $en];

    return $all;
}

/**
 * Slår ihop inbyggt språk, valfritt extra språk i config och användaröverskridningar.
 *
 * @return array<string, string>
 */
function skill_lang_flat_map(): array {
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $builtin = skill_lang_builtin_all();
    $user = skill_lang_user_config();
    $locale = skill_locale();
    $userStrings = (array)($user['strings'] ?? []);

    $base = (array)($builtin['sv'] ?? []);
    if ($locale !== 'sv' && isset($builtin[$locale])) {
        $base = array_merge($base, (array)$builtin[$locale]);
    }
    if (isset($userStrings[$locale]) && is_array($userStrings[$locale])) {
        $base = array_merge($base, $userStrings[$locale]);
    }

    $map = $base;

    return $map;
}

/**
 * Översätt sträng. Platshållare: {nyckel} ersätts från $ctx.
 *
 * @param array<string, string|int|float> $ctx
 */
function __(string $key, array $ctx = []): string {
    $s = skill_lang_flat_map()[$key] ?? $key;
    if ($ctx === []) {
        return $s;
    }
    foreach ($ctx as $k => $v) {
        $s = str_replace('{' . $k . '}', (string)$v, $s);
    }

    return $s;
}

/** Standardmall SKILL.md beroende på språk (innehåll i nya skills). */
function skill_default_skill_md_template(): string {
    if (skill_locale() === 'en') {
        return <<<'MD'
---
name:
title:
description:
author:
version: 1.0.0
tags:
---

# New skill

## Purpose

Describe what this skill does and when to use it.

## Instructions

1. Step one
2. Step two

## Example

```
# Example
```
MD;
    }

    return <<<'MD'
---
name:
title:
description:
author:
version: 1.0.0
tags:
---

# Ny skill

## Syfte

Beskriv vad denna skill gör och när den används.

## Instruktioner

1. Steg ett
2. Steg två

## Exempel

```
# Exempel
```
MD;
}
