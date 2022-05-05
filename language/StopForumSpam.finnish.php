<?php

/* The section Name */
$txt['sfs_admin_area'] = 'Stop Forum Spam';
$txt['sfs_admin_logs'] = 'SFS:n logit';

/* Admin Section General header */
$txt['sfs_general_title'] = 'Yleiset asetukset';

/* Admin section configuration options */
$txt['sfs_enabled'] = 'Stop Forum Spam käytössä';
$txt['sfs_expire'] = 'Rajaa tulokset viimeiseen x päivään';
$txt['sfs_log_debug'] = 'Kaikkien SFS-pyyntöjen logitus käytössä (vain virheenkorjausta varten)';
$txt['sfs_ipcheck'] = 'Tarkista IP-osoite';
$txt['sfs_ipcheck_autoban'] = 'Aseta estetyt IP-osoitteet automaattisesti porttikieltoon';
$txt['sfs_usernamecheck'] = 'Tarkista käyttäjänimi';
$txt['sfs_username_confidence'] = 'Luottamustaso käyttäjänimille rekisteröitymisen yhteydessä';
$txt['sfs_emailcheck'] = 'Tarkista sähköpostiosoite (suositeltu)';
$txt['sfs_enablesubmission'] = 'Lähetys Stop Forum Spam:n tietokantaan käytössä';
$txt['sfs_apikey'] = '<a href="https://www.stopforumspam.com/keys">API-avain</a>';

/* Admin section: Region Config */
$txt['sfs_region'] = 'Maantieteellinen alue';
$txt['sfs_region_global'] = 'Maailmanlaajuinen (suositeltu)';
$txt['sfs_region_us'] = 'Yhdysvallat';
$txt['sfs_region_eu'] = 'Eurooppa';

/* Admin section: Wildcard section */
$txt['sfs_wildcard_email'] = 'Ohita sähköpostiosoitelistojen (wildcard) tarkistus';
$txt['sfs_wildcard_username'] = 'Ohita käyttäjänimilistojen (wildcard) tarkistus';
$txt['sfs_wildcard_ip'] = 'Ohita IP-osoitelistojen (wildcard) tarkistus';

/* Admin Section: Tor handling section */
$txt['sfs_tor_check'] = 'TOR-verkon poistumissolmujen käsittely';
$txt['sfs_tor_check_block'] = 'Estä kaikki poistumissolmut (oletus)';
$txt['sfs_tor_check_ignore'] = 'Älä huomioi poistumissolmuja';
$txt['sfs_tor_check_bad'] = 'Estä vain tunnetusti ongelmalliset poistumissolmut';

/* Admin Section: Verification Options header */
$txt['sfs_verification_title'] = 'Tarkistusasetukset';
$txt['sfs_verification_desc'] = 'Nämä asetukset edellyttävät, että varmistukset on määritelty ja käytössä.  Varmistusasetusten poistaminen käytöstä tai niiden jättäminen vaatimatta tietyssä kohdassa yliajaa nämä asetukset.';

/* Admin Section: Verification Options for guests */
$txt['sfs_verification_options'] = 'Vieraiden suorittamat toiminnot';
$txt['sfs_verOptionsMembers'] = 'Rekisteröityneiden käyttäjien suorittamat toiminnot';
$txt['sfs_verification_options_post'] = 'Viestin kirjoitus';
$txt['sfs_verification_options_report'] = 'Aiheiden raportointi moderaattorille';
$txt['sfs_verification_options_search'] = 'Haku (ei suositeltu)';
$txt['sfs_verification_options_extra'] = 'Lisätoiminnot';
$txt['sfs_verification_options_extra_subtext'] = 'Käytetään muille lisäosille tai toiminnoille, jotka lisäävät uusia toimintoja tarkistuksiin.  Käytä pilkkua toimintojen erottamiseen. Käytä % jokerimerkkinä.';

$txt['sfs_verification_options_members'] = 'Rekisteröityneiden käyttäjien suorittamat toiminnot';
$txt['sfs_verification_options_members_post_threshold'] = 'Kirjoitettujen viestien minimimäärä, joka lopettaa nämä tarkistukset.';
$txt['sfs_verification_options_membersextra'] = 'Lisätoiminnot';

/* Request handling */
$txt['sfs_request_failure'] = 'SFS-pyyntö epäonnistui, vastaus ei ollut kelvollinen';
$txt['sfs_request_failure_nodata'] = 'SFS-pyyntö epäonnistui, mitään tietoa ei lähetetty';

/* Spammer detection */
$txt['sfs_request_blocked'] = 'Pyyntösi hylättiin, koska sähköpostiosoitteesi, käyttäjänimesi ja/tai IP-osoitteesi on Stop Forum Spam -estolistalla.';

/* Admin Section Logs */
$txt['sfs_log_no_entries_found'] = 'Ei tapahtumia SFS:n logeissa';
$txt['sfs_log_search_url'] = 'URL';
$txt['sfs_log_search_member'] = 'Jäsen';
$txt['sfs_log_search_username'] = 'Käyttäjänimi';
$txt['sfs_log_search_email'] = 'Sähköposti';
$txt['sfs_log_search_ip'] = 'IP-osoite';
$txt['sfs_log_search_ip2'] = 'IP-osoite (porttikiellon tarkistus)';
$txt['sfs_log_header_type'] = 'Login tyyppi';
$txt['sfs_log_header_url'] = 'URL';
$txt['sfs_log_header_time'] = 'Aika';
$txt['sfs_log_header_member'] = 'Jäsen';
$txt['sfs_log_header_username'] = 'Käyttäjänimi';
$txt['sfs_log_header_email'] = 'Sähköposti';
$txt['sfs_log_header_ip'] = 'IP-osoite';
$txt['sfs_log_header_ip2'] = 'IP-osoite (porttikiellon tarkistus)';
$txt['sfs_log_checks'] = 'Tarkistukset';
$txt['sfs_log_result'] = 'Tulokset';
$txt['sfs_log_search'] = 'Haku logista';
$txt['sfs_log_types_0'] = 'Virheenkorjaus';
$txt['sfs_log_types_1'] = 'Käyttäjänimi';
$txt['sfs_log_types_2'] = 'Sähköpostiosoite';
$txt['sfs_log_types_3'] = 'IP-osoite';
$txt['sfs_log_matched_on'] = 'Osuma: %1$s [%2$s]';
$txt['sfs_log_auto_banned'] = 'porttikiellossa';
$txt['sfs_log_confidence'] = 'Luottamustaso: %1$s';

// The ban group info.
$txt['sfs_ban_group_name'] = 'SFS:n automaattiset IP-porttikiellot';
$txt['sfs_ban_group_reason'] = 'IP-osoitteesi on estetty ja porttikiellossa huonomaineisena';
$txt['sfs_ban_group_notes'] = 'Tämä on Stop Forum Spam -lisäosan automaattisia IP-porttikieltoja varten luotu ryhmä.';

// Profile menu
$txt['sfs_profile'] = 'Seuraa Stop Forum Spam:ia';
$txt['sfs_check'] = 'Tarkista';
$txt['sfs_result'] = 'Tulos';
$txt['sfs_check_username'] = 'Käyttäjänimi';
$txt['sfs_check_email'] = 'Sähköposti';
$txt['sfs_check_ip'] = 'IP-osoite';
$txt['sfs_last_seen'] = 'Viimeksi nähty';
$txt['sfs_confidence'] = 'Luottamustaso';
$txt['sfs_frequency'] = 'Toistuvuus';
$txt['sfs_torexit'] = 'TOR-poistumissolmu';

// Profile section Submission
$txt['sfs_submit_title'] = 'Stop Forum Spam -lähetys';
$txt['sfs_submit'] = 'Lähetä Stop Forum Spam:n tietokantaan';
$txt['sfs_submit_ban'] = 'Lähetä Stop Forum Spam:n tietokantaan ja aloita porttikielto';
$txt['sfs_evidence'] = 'Todistusaineisto';
$txt['sfs_submission_error'] = 'Lähetys epäonnistui';
$txt['sfs_submission_success'] = 'Lähetys onnistui';
