<?php

namespace MailCleaner\RuleEngine;

use MailCleaner\Models\MessageHeaders;

/**
 * FORMÅL:
 * RuleEngine er laget som vurderer en e-post (headers, evt. body) mot et
 * sett regler og returnerer en anbefalt handling — UTEN å utføre noe selv.
 * Driverne (GmailDriver/ImapDriver) utfører; RuleEngine bestemmer kun "hva".
 *
 * Tanken er to regelkilder som kan kombineres:
 *  1. Importerte Gmail-filtre (avsendermønstre/nøkkelord du allerede har
 *     validert i Gmail UI) — hentes via users.settings.filters.list
 *  2. Egendefinerte regler i MySQL (samme struktur, men driver-uavhengige,
 *     så de kan kjøres mot IMAP-kontoen også)
 *
 * applyRuleset() er ment å kjøre i to faser:
 *  - DRY RUN: returner forslag (messageId + regel + foreslått handling)
 *  - APPLY: kun etter at du har godkjent forslagene, kall driver.act()
 * Dette er bevisst adskilt fra utførelse — vi vil aldri auto-slette
 * uten et godkjenningssteg i starten av prosjektet.
 *
 * TODO (i prioritert rekkefølge):
 *  1. RuleRepository: CRUD mot MySQL-tabellen `rules`
 *  2. importFromGmailFilters(): map Gmail-filter-format -> Rule-objekt
 *  3. evaluate(): match enkelt-melding mot én regel (avsender/emne/SPF)
 *  4. applyRuleset(): loop over meldinger, samle treff, IKKE utfør
 *  5. Logging: hvert forslag/utførelse skrives til `processing_log`
 *     (se database/schema.sql) for sporbarhet
 */
class RuleEngine
{
    /**
     * @param MessageHeaders[] $messages
     * @return array<int, array{messageId: string, ruleId: int, action: string}>
     */
    public function applyRuleset(array $messages, array $rules): array
    {
        // Dummy-implementasjon: ingen regler evaluert ennå, alt er "ingen match"
        return [];
    }

    public function evaluate(MessageHeaders $message, array $rule): bool
    {
        // Dummy: alltid false inntil ekte matching-logikk er på plass
        return false;
    }

    public function importFromGmailFilters(array $gmailFilters): array
    {
        // TODO: map Gmail sitt filterformat til internt Rule-format
        return [];
    }
}