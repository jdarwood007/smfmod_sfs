<?php

/* The section Name */
$txt['sfs_admin_area'] = 'Stop Forum Spam';
$txt['sfs_admin_logs'] = 'Журнал SFS';
$txt['sfs_admin_test'] = 'Тестирование SFS';
$txt['sfs_admin_test_desc'] = 'Протестируйте API Stop Forum Spam и вашу конфигурацию';

/* Admin Section General header */
$txt['sfs_general_title'] = 'Основные настройки';

/* Admin section configuration options */
$txt['sfs_enabled'] = 'Включить Stop Forum Spam?';
$txt['sfs_expire'] = 'Ограничить результаты записями за последние x дней';
$txt['sfs_log_debug'] = 'Включить ведение журнала всех запросов SFS (только для отладки)?';
$txt['sfs_ipcheck'] = 'Проверять IP-адрес?';
$txt['sfs_ipcheck_autoban'] = 'Автоматический бан заблокированных IP-адресов?';
$txt['sfs_usernamecheck'] = 'Проверять имя пользователя?';
$txt['sfs_username_confidence'] = 'Уровень доверия к именам пользователей при регистрации';
$txt['sfs_emailcheck'] = 'Проверять имейл? (рекомендуется)';
$txt['sfs_enablesubmission'] = 'Включить подачу заявок';
$txt['sfs_apikey'] = '<a href="https://www.stopforumspam.com/keys">Ключ API</a>';

/* Admin section: Required */
$txt['sfs_required'] = 'Требуемые проверки';
$txt['sfs_required_any'] = 'Все [имейл или имя пользователя | IP-адрес] (по умолчанию)';
$txt['sfs_required_email_ip'] = 'Имейл и IP-адрес';
$txt['sfs_required_email_username'] = 'Имейл и имя пользователя';
$txt['sfs_required_username_ip'] = 'Имя пользователя и IP-адрес';

/* Admin section: Region Config */
$txt['sfs_region'] = 'Географический регион доступа';
$txt['sfs_region_global'] = 'Глобальный (рекомедуется)';
$txt['sfs_region_us'] = 'США';
$txt['sfs_region_eu'] = 'Европа';

/* Admin section: Wildcard section */
$txt['sfs_wildcard_email'] = 'Игнорировать проверки имейлов с использованием подстановочных знаков';
$txt['sfs_wildcard_username'] = 'Игнорировать проверки имён пользователей с подстановочными знаками';
$txt['sfs_wildcard_ip'] = 'Игнорировать проверки IP-адресов с использованием подстановочных знаков';

/* Admin Section: Tor handling section */
$txt['sfs_tor_check'] = 'Обработка узлов выхода TOR';
$txt['sfs_tor_check_block'] = 'Блокировать все узлы выхода (по умолчанию)';
$txt['sfs_tor_check_ignore'] = 'Игнорировать все узлы выхода';
$txt['sfs_tor_check_bad'] = 'Блокировать только известные плохие узлы выхода';

/* Admin Section: Verification Options header */
$txt['sfs_verification_title'] = 'Параметры проверки';
$txt['sfs_verification_desc'] = 'Эти параметры требуют настройки и конфигурации опций антиспама. Отключение параметров проверки или отсутствие их требования в определённых разделах переопределит эти параметры.';

/* Admin Section: Verification Options for guests */
$txt['sfs_verification_options'] = 'Проверка гостей';
$txt['sfs_verOptionsMembers'] = 'Проверка пользователей';
$txt['sfs_verification_options_post'] = 'Отправка сообщений';
$txt['sfs_verification_options_report'] = 'Отправка жалоб';
$txt['sfs_verification_options_search'] = 'Поиск (не рекомендуется)';
$txt['sfs_verification_options_extra'] = 'Дополнительные секции';
$txt['sfs_verification_options_extra_subtext'] = 'Используется для других модификаций или областей, которые добавляют дополнительные секции с использованием пользовательских имён проверки. Используйте значения, разделённые запятыми. Используйте % для подстановочных знаков';

$txt['sfs_verOptionsMemExtra'] = 'Проверка пользователей';
$txt['sfs_verfOptMemPostThreshold'] = 'Количество сообщений, после которого мы прекращаем эти проверки';
$txt['sfs_verification_options_membersextra'] = 'Дополнительные секции';

/* Admin section: Test API */
$txt['sfs_testapi_error'] = 'API не удалось установить связь с серверами SFS';
$txt['sfs_testapi_title'] = 'Введите информацию о тесте';
$txt['sfs_testapi_results'] = 'Результаты тестирования API';
$txt['sfs_value'] = 'Значение';
$txt['sfs_testapi_submit'] = 'Отправить тест API';

/* Request handling */
$txt['sfs_request_failure'] = 'Неверный ответ';
$txt['sfs_request_failure_nodata'] = 'Данные не были отправлены';

/* Spammer detection */
$txt['sfs_request_blocked'] = 'Ваш запрос был отклонён, так как ваш адрес электронной почты, имя пользователя и/или IP-адрес занесены в базу данных Stop Forum Spam';

/* Admin Section Logs */
$txt['sfs_log_no_entries_found'] = 'В журнале нет записей';
$txt['sfs_log_search_url'] = $txt['url'];
$txt['sfs_log_search_member'] = $txt['who_member'];
$txt['sfs_log_search_username'] = $txt['username'];
$txt['sfs_log_search_email'] = $txt['email'];
$txt['sfs_log_search_ip'] = $txt['ip'];
$txt['sfs_log_search_ip2'] = 'IP-адрес (проверка запрета)';
$txt['sfs_log_header_type'] = 'Тип';
$txt['sfs_log_header_url'] = $txt['url'];
$txt['sfs_log_header_time'] = 'Время';
$txt['sfs_log_header_member'] = $txt['who_member'];
$txt['sfs_log_header_username'] = $txt['username'];
$txt['sfs_log_header_email'] = $txt['email'];
$txt['sfs_log_header_ip'] = $txt['ip'];
$txt['sfs_log_header_ip2'] = 'IP-адрес (проверка запрета)';
$txt['sfs_log_checks'] = 'Проверок';
$txt['sfs_log_result'] = 'Результатов';
$txt['sfs_log_search'] = 'Поиск по журналу';
$txt['sfs_log_types_0'] = 'Отладка';
$txt['sfs_log_types_1'] = $txt['username'];
$txt['sfs_log_types_2'] = $txt['email'];
$txt['sfs_log_types_3'] = $txt['ip'];
$txt['sfs_log_matched_on'] = 'Соответствует %1$s [%2$s]';
$txt['sfs_log_auto_banned'] = 'Под запретом';
$txt['sfs_log_confidence'] = 'Уровень доверия: %1$s';

// The ban group info.
$txt['sfs_ban_group_name'] = 'Автоматическая блокировка IP-адресов SFS';
$txt['sfs_ban_group_reason'] = 'Ваш IP-адрес вызвал автоматический бан за плохую репутацию и был заблокирован.';
$txt['sfs_ban_group_notes'] = 'Эта группа автоматически создаётся с помощью настройки «Остановить спам на форуме», и заблокированные IP-адреса будут автоматически добавлены в эту группу';

// Profile menu
$txt['sfs_profile'] = 'Отслеживать спам на форуме';
$txt['sfs_check'] = 'Проверка';
$txt['sfs_result'] = 'Результат';
$txt['sfs_check_username'] = $txt['username'];
$txt['sfs_check_email'] = $txt['email'];
$txt['sfs_check_ip'] = $txt['ip'];
$txt['sfs_last_seen'] = 'Последнее посещение';
$txt['sfs_confidence'] = 'Доверие';
$txt['sfs_frequency'] = 'Частота';
$txt['sfs_torexit'] = 'Выходной узел TOR';

// Profile section Submission
$txt['sfs_submit_title'] = 'Остановить рассылку спама на форуме';
$txt['sfs_submit'] = 'Отправить, чтобы остановить спам на форуме';
$txt['sfs_submit_ban'] = 'Отправьте заявку в раздел «Остановить спам на форуме» и начать процесс бана';
$txt['sfs_evidence'] = 'Доказательство';
$txt['sfs_submission_error'] = 'Ошибка отправки';
$txt['sfs_submission_success'] = 'Отправка успешна';
