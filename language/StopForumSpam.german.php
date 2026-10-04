<?php

/* The section Name */
$txt['sfs_admin_area'] = 'Stop Forum Spam';
$txt['sfs_admin_logs'] = 'SFS-Protokolle';
$txt['sfs_admin_test'] = 'SFS testen';
$txt['sfs_admin_test_desc'] = 'Stop-Forum-Spam-API und Ihre Konfiguration testen';

/* Admin Section General header */
$txt['sfs_general_title'] = 'Allgemeine Konfiguration';

/* Admin section configuration options */
$txt['sfs_enabled'] = 'Stop Forum Spam aktivieren?';
$txt['sfs_expire'] = 'Ergebnisse auf Einträge der letzten x Tage beschränken';
$txt['sfs_log_debug'] = 'Protokollierung aller SFS-Anfragen aktivieren (nur zur Fehlersuche)?';
$txt['sfs_ipcheck'] = 'IP-Adresse prüfen?';
$txt['sfs_ipcheck_autoban'] = 'Blockierte IP-Adressen automatisch sperren?';
$txt['sfs_usernamecheck'] = 'Benutzernamen prüfen?';
$txt['sfs_username_confidence'] = 'Konfidenzwert für Benutzernamen bei der Registrierung';
$txt['sfs_emailcheck'] = 'E-Mail-Adresse prüfen? (Empfohlen)';
$txt['sfs_enablesubmission'] = 'Übermittlungen aktivieren';
$txt['sfs_apikey'] = '<a href="https://www.stopforumspam.com/keys">API Key</a>';

/* Admin section: Required */
$txt['sfs_required'] = 'Erforderliche Prüfungen';
$txt['sfs_required_any'] = 'Beliebig [E-Mail oder Benutzername | IP] (Standard)';
$txt['sfs_required_email_ip'] = 'E-Mail-Adresse & IP-Adresse';
$txt['sfs_required_email_username'] = 'E-Mail-Adresse & Benutzername';
$txt['sfs_required_username_ip'] = 'Benutzername & IP-Adresse';

/* Admin section: Region Config */
$txt['sfs_region'] = 'Geografische Zugriffsregion';
$txt['sfs_region_global'] = 'Global (Empfohlen)';
$txt['sfs_region_us'] = 'Region Vereinigte Staaten';
$txt['sfs_region_eu'] = 'Region Europa';

/* Admin section: Wildcard section */
$txt['sfs_wildcard_email'] = 'Wildcard-Prüfungen für E-Mail-Adressen ignorieren';
$txt['sfs_wildcard_username'] = 'Wildcard-Prüfungen für Benutzernamen ignorieren';
$txt['sfs_wildcard_ip'] = 'Wildcard-Prüfungen für IP-Adressen ignorieren';

/* Admin Section: Tor handling section */
$txt['sfs_tor_check'] = 'Umgang mit TOR-Exit-Nodes';
$txt['sfs_tor_check_block'] = 'Alle Exit-Nodes blockieren (Standard)';
$txt['sfs_tor_check_ignore'] = 'Alle Exit-Nodes ignorieren';
$txt['sfs_tor_check_bad'] = 'Nur bekannte problematische Exit-Nodes blockieren';

/* Admin Section: Verification Options header */
$txt['sfs_verification_title'] = 'Verifizierungsoptionen';
$txt['sfs_verification_desc'] = 'Diese Optionen setzen voraus, dass die Anti-Spam-Verifizierung eingerichtet und konfiguriert ist. Das Deaktivieren der Verifizierung oder das Nicht-Anfordern in bestimmten Bereichen hat Vorrang vor diesen Einstellungen.';

/* Admin Section: Verification Options for guests */
$txt['sfs_verification_options'] = 'Verifizierungsbereiche für Gäste';
$txt['sfs_verOptionsMembers'] = 'Verifizierungsbereiche für Mitglieder';
$txt['sfs_verification_options_post'] = 'Beiträge erstellen';
$txt['sfs_verification_options_report'] = 'Themen melden';
$txt['sfs_verification_options_search'] = 'Suche (nicht empfohlen)';
$txt['sfs_verification_options_extra'] = 'Zusätzliche Bereiche';
$txt['sfs_verification_options_extra_subtext'] = 'Für andere Mods oder Bereiche, die zusätzliche Abschnitte mit benutzerdefinierten Verifizierungsnamen hinzufügen. Werte durch Kommas trennen. Verwenden Sie % als Platzhalter.';

$txt['sfs_verOptionsMemExtra'] = 'Verifizierungsbereiche für Mitglieder';
$txt['sfs_verfOptMemPostThreshold'] = 'Anzahl der Beiträge, ab der diese Prüfungen nicht mehr durchgeführt werden.';
$txt['sfs_verification_options_membersextra'] = 'Zusätzliche Bereiche';

/* Admin section: Test API */
$txt['sfs_testapi_error'] = 'Die API konnte keine Verbindung zu den SFS-Servern herstellen';
$txt['sfs_testapi_title'] = 'Testdaten eingeben';
$txt['sfs_testapi_results'] = 'Ergebnisse des API-Tests';
$txt['sfs_value'] = 'Wert';
$txt['sfs_testapi_submit'] = 'API-Test senden';

/* Request handling */
$txt['sfs_request_failure'] = 'SFS ist aufgrund einer ungültigen Antwort fehlgeschlagen';
$txt['sfs_request_failure_nodata'] = 'SFS ist fehlgeschlagen, da keine Daten gesendet wurden';

/* Spammer detection */
$txt['sfs_request_blocked'] = 'Ihre Anfrage wurde abgelehnt, da Ihre E-Mail-Adresse, Ihr Benutzername und/oder Ihre IP-Adresse in der Stop-Forum-Spam-Datenbank aufgeführt ist';

/* Admin Section Logs */
$txt['sfs_log_no_entries_found'] = 'Keine Einträge in den SFS-Protokollen gefunden';
$txt['sfs_log_search_url'] = 'URL';
$txt['sfs_log_search_member'] = 'Mitglied';
$txt['sfs_log_search_username'] = 'Benutzername';
$txt['sfs_log_search_email'] = 'E-Mail-Adresse';
$txt['sfs_log_search_ip'] = 'IP-Adresse';
$txt['sfs_log_search_ip2'] = 'IP-Adresse (Sperrprüfung)';
$txt['sfs_log_header_type'] = 'Protokolltyp';
$txt['sfs_log_header_url'] = 'URL';
$txt['sfs_log_header_time'] = 'Zeit';
$txt['sfs_log_header_member'] = 'Mitglied';
$txt['sfs_log_header_username'] = 'Benutzername';
$txt['sfs_log_header_email'] = 'E-Mail-Adresse';
$txt['sfs_log_header_ip'] = 'IP-Adresse';
$txt['sfs_log_header_ip2'] = 'IP-Adresse (Sperrprüfung)';
$txt['sfs_log_checks'] = 'Prüfungen';
$txt['sfs_log_result'] = 'Ergebnisse';
$txt['sfs_log_search'] = 'Protokollsuche';
$txt['sfs_log_types_0'] = 'Debug';
$txt['sfs_log_types_1'] = 'Benutzername';
$txt['sfs_log_types_2'] = 'E-Mail-Adresse';
$txt['sfs_log_types_3'] = 'IP-Adresse';
$txt['sfs_log_matched_on'] = 'Übereinstimmung bei %1$s [%2$s]';
$txt['sfs_log_auto_banned'] = 'Gesperrt';
$txt['sfs_log_confidence'] = 'Konfidenzwert: %1$s';

// The ban group info.
$txt['sfs_ban_group_name'] = 'Automatische SFS-IP-Sperren';
$txt['sfs_ban_group_reason'] = 'Ihre IP-Adresse hat aufgrund einer schlechten Reputation eine automatische Sperre ausgelöst und wurde blockiert';
$txt['sfs_ban_group_notes'] = 'Diese Gruppe wird automatisch von der Stop-Forum-Spam-Anpassung erstellt. Blockierte IP-Adressen werden dieser Gruppe automatisch hinzugefügt';

// Profile menu
$txt['sfs_profile'] = 'Stop Forum Spam nachverfolgen';
$txt['sfs_check'] = 'Prüfen';
$txt['sfs_result'] = 'Ergebnis';
$txt['sfs_check_username'] = 'Benutzername';
$txt['sfs_check_email'] = 'E-Mail-Adresse';
$txt['sfs_check_ip'] = 'IP-Adresse';
$txt['sfs_last_seen'] = 'Zuletzt gesehen';
$txt['sfs_confidence'] = 'Konfidenz';
$txt['sfs_frequency'] = 'Häufigkeit';
$txt['sfs_torexit'] = 'TOR-Exit-Node';

// Profile section Submission
$txt['sfs_submit_title'] = 'Übermittlung an Stop Forum Spam';
$txt['sfs_submit'] = 'An Stop Forum Spam übermitteln';
$txt['sfs_submit_ban'] = 'An Stop Forum Spam übermitteln und Sperrvorgang starten';
$txt['sfs_evidence'] = 'Nachweis';
$txt['sfs_submission_error'] = 'Fehler bei der Übermittlung';
$txt['sfs_submission_success'] = 'Übermittlung erfolgreich';
