# .skill

## Vad är en Claude-skill?
En skill är en permanent förmåga som Claude kan lära sig: ett återanvändbart arbetsflöde beskrivet i text, ofta med exempel, regler och ibland script.  När du aktiverar en skill kan Claude automatiskt läsa in den när en användarfråga matchar skillens beskrivning, vilket ökar träffsäkerhet och hastighet för just den typen av uppgifter.  En skill kan till exempel standardisera hur rapporter skrivs, hur Excel-filer ska struktureras eller hur marknadsföringsanalyser ska utföras.

## Grundidé: mapp som paketeras till .zip
Tekniskt sett är en Claude-skill en vanlig mapp med en bestämd struktur, som sedan komprimeras till en .zip-fil och laddas upp i inställningarna för skills.  Mappen innehåller minst en huvudfil som beskriver skillen (SKILL.md), och kan dessutom innehålla undermappar för script, referensdokument, mallar, exempeldata och liknande.  När du laddar upp .zip-filen läser Claude in metadata (namn, beskrivning, taggar) först, och hämtar sedan mer detaljerade instruktioner bara när det behövs för en konkret uppgift.

## SKILL.md – hjärtat i skillen
Varje skill kretsar kring en fil SKILL.md, skriven i Markdown med YAML-frontmatter högst upp.  YAML-blocket (mellan `---` och `---`) innehåller nyckelfält som namn, kort beskrivning, syfte, eventuella nyckelord och ibland begränsningar – det är denna metadata Claude använder för att avgöra när skillen är relevant.  Under frontmattern ligger själva instruktionstexten: detaljerade riktlinjer, steg-för-steg-flöden, formatmallar för output samt exempel på bra och dåliga svar.

Ett förenklat exempel på struktur i SKILL.md kan se ut så här (förklarande, ej komplett):

```markdown
---
name: "kommun-rapport-skill"
description: "Skapar standardiserade tjänsteskrivelser för kommunala beslut."
tags: ["kommun", "tjänsteskrivelse", "beslutsunderlag"]
---

# Syfte
Instruktioner för hur tjänsteskrivelser ska struktureras ...

## Arbetsflöde
1. Ställ klarläggande frågor ...
2. Skapa disposition med rubriker ...
3. Skriv text med dessa regler ...

## Exempel
...
```

## Typisk mappstruktur i en .skill-zip
En rekommenderad struktur för själva mappen (innan du zippar) ser ofta ut ungefär så här.

```text
din-skill/
├── SKILL.md              # Obligatorisk huvudfil
├── scripts/              # (Valfri) körbar kod, t.ex. Python, shell
│   ├── process_data.py
│   └── validate.sh
├── references/           # (Valfri) referensmaterial, dokumentation
│   ├── style_guide.pdf
│   └── examples.md
└── templates/            # (Valfri) mallar, t.ex. rapport- eller e‑postmallar
    └── report_template.md
```

- **SKILL.md**: Beskriver vad skillen gör, när den ska användas och exakt hur Claude ska bete sig.
- `scripts/`: Script Claude kan referera till i instruktionerna, t.ex. för att beskriva hur data ska förbehandlas eller valideras (Claude kan inte exekvera dem direkt, men de dokumenterar processer eller outputformat).
- `references/`: Policyer, stilguider, API-specar, processbeskrivningar eller andra statiska dokument som ger kontext.
- `templates/` eller liknande: Strukturerade mallar som Claude ska följa när den genererar text eller kod.

När du ska skapa .skill-filen:

1. Bygg mappstrukturen enligt ovan och se till att SKILL.md finns i roten.
2. Komprimera hela mappen till en ZIP-fil med valfritt verktyg (t.ex. Windows inbyggda komprimering eller annat zip-verktyg).
3. Ladda upp ZIP-filen i Claude under inställningar för skills; då registreras skillen och kan aktiveras.

## Hur Claude använder skills i praktiken
När du sedan ställer en fråga eller ber Claude utföra en uppgift, går modellen igenom tillgängliga skills och matchar på framför allt namn och beskrivning i SKILL.md.  Bara om en skill verkar relevant läser Claude in den fulla instruktionstexten, vilket gör att du kan ha många skills utan att “spamma” varje konversation med all kontext på en gång.  Claude kan också använda flera skills samtidigt för komplexa uppgifter, till exempel en skill för rapportstruktur och en annan för språklig ton eller ett visst fackspråk.

