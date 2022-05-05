<?php

/* The section Name */
$txt['sfs_admin_area'] = 'Stop Forum Spam';
$txt['sfs_admin_logs'] = 'SFS Logs';

/* Admin Section General header */
$txt['sfs_general_title'] = 'General Configuration';

/* Admin section configuration options */
$txt['sfs_enabled'] = 'Enable Stop Forum Spam?';
$txt['sfs_expire'] = 'Limit results to entries in the past x days';
$txt['sfs_log_debug'] = 'Enable Logging of all SFS requests (Debugging Only)?';
$txt['sfs_ipcheck'] = 'Check IP Address?';
$txt['sfs_ipcheck_autoban'] = 'Automatically ban IPs that are blacklisted?';
$txt['sfs_usernamecheck'] = 'Check Username?';
$txt['sfs_username_confidence'] = 'Confidence level for usernames on registration';
$txt['sfs_emailcheck'] = 'Check Email? (Recommended)';
$txt['sfs_enablesubmission'] = 'Enable Submissions';
$txt['sfs_apikey'] = '<a href="https://www.stopforumspam.com/keys">API Key</a>';

/* Admin section: Region Config */
$txt['sfs_region'] = 'Geographic Access Region';
$txt['sfs_region_global'] = 'Global (Recommended)';
$txt['sfs_region_us'] = 'United States Region';
$txt['sfs_region_eu'] = 'Europe Region';

/* Admin section: Wildcard section */
$txt['sfs_wildcard_email'] = 'Ignore Wildcard Email Checks';
$txt['sfs_wildcard_username'] = 'Ignore Wildcard Username Checks';
$txt['sfs_wildcard_ip'] = 'Ignore Wildcard IP Checks';

/* Admin Section: Tor handling section */
$txt['sfs_tor_check'] = 'TOR Exit Node Handling';
$txt['sfs_tor_check_block'] = 'Block All Exit Nodes (Default)';
$txt['sfs_tor_check_ignore'] = 'Ignore All Exit Nodes';
$txt['sfs_tor_check_bad'] = 'Block Only Known Bad Exit Nodes';

/* Admin Section: Verification Options header */
$txt['sfs_verification_title'] = 'Verification Options';
$txt['sfs_verification_desc'] = 'Usage of these options reuqire Anti-Spam Verification options to be setup and configurred.  Disabling verification options or not requiring it in specific sections will override these options.';

/* Admin Section: Verification Options for guests */
$txt['sfs_verification_options'] = 'Guest Verification Sections';
$txt['sfs_verOptionsMembers'] = 'Member Verification Sections';
$txt['sfs_verification_options_post'] = 'Posting';
$txt['sfs_verification_options_report'] = 'Reporting Topics';
$txt['sfs_verification_options_search'] = 'Search (Not Recommended)';
$txt['sfs_verification_options_extra'] = 'Extra sections';
$txt['sfs_verification_options_extra_subtext'] = 'Used for other mods or areas that add additional sections using custom verification names.  Use comma separted values. Use % for wildcards';

$txt['sfs_verOptionsMemExtra'] = 'Member Verification Sections';
$txt['sfs_verfOptMemPostThreshold'] = 'Post Count after which we stop these checks.';
$txt['sfs_verification_options_membersextra'] = 'Extra sections';

/* Request handling */
$txt['sfs_request_failure'] = 'SFS Failed with invalid response';
$txt['sfs_request_failure_nodata'] = 'SFS Failed as no data was sent';

/* Spammer detection */
$txt['sfs_request_blocked'] = 'Your request was denied as one or more emails, usernames, or IPs where found on the Stop Forum Spam blacklist';

/* Admin Section Logs */
$txt['sfs_log_no_entries_found'] = 'No Entries found in the SFS logs';
$txt['sfs_log_search_url'] = 'URL';
$txt['sfs_log_search_member'] = 'Member';
$txt['sfs_log_search_username'] = 'Username';
$txt['sfs_log_search_email'] = 'Email';
$txt['sfs_log_search_ip'] = 'IP Address';
$txt['sfs_log_search_ip2'] = 'IP Address (Ban Check)';
$txt['sfs_log_header_type'] = 'Log Type';
$txt['sfs_log_header_url'] = 'URL';
$txt['sfs_log_header_time'] = 'Time';
$txt['sfs_log_header_member'] = 'Member';
$txt['sfs_log_header_username'] = 'Username';
$txt['sfs_log_header_email'] = 'Email';
$txt['sfs_log_header_ip'] = 'IP Address';
$txt['sfs_log_header_ip2'] = 'IP Address (Ban Check)';
$txt['sfs_log_checks'] = 'Checks';
$txt['sfs_log_result'] = 'Results';
$txt['sfs_log_search'] = 'Log Search';
$txt['sfs_log_types_0'] = 'Debug';
$txt['sfs_log_types_1'] = 'Username';
$txt['sfs_log_types_2'] = 'Email';
$txt['sfs_log_types_3'] = 'IP Address';
$txt['sfs_log_matched_on'] = 'Matched on %1$s [%2$s]';
$txt['sfs_log_auto_banned'] = 'Banned';
$txt['sfs_log_confidence'] = 'Confidence Level: %1$s';

// The ban group info.
$txt['sfs_ban_group_name'] = 'SFS Automatic IP Bans';
$txt['sfs_ban_group_reason'] = 'Your IP has triggered the automatic ban for poor reputation and has been blacklisted';
$txt['sfs_ban_group_notes'] = 'This Group is automatically created by the Stop Forum Spam Customization and automatically will add IPs that are blacklisted to this group';

// Profile menu
$txt['sfs_profile'] = 'Track Stop Forum Spam';
$txt['sfs_check'] = 'Check';
$txt['sfs_result'] = 'Result';
$txt['sfs_check_username'] = 'Username';
$txt['sfs_check_email'] = 'Email';
$txt['sfs_check_ip'] = 'IP Address';
$txt['sfs_last_seen'] = 'Last Seen';
$txt['sfs_confidence'] = 'Confidence';
$txt['sfs_frequency'] = 'Frequency';
$txt['sfs_torexit'] = 'TOR Exit Node';

// Profile section Submission
$txt['sfs_submit_title'] = 'Stop Forum Spam Submission';
$txt['sfs_submit'] = 'Submit to Stop Forum Spam';
$txt['sfs_submit_ban'] = 'Submit to Stop Forum Spam and Start ban process';
$txt['sfs_evidence'] = 'Evidence';
$txt['sfs_submission_error'] = 'Submission Error';
$txt['sfs_submission_success'] = 'Submission Success';