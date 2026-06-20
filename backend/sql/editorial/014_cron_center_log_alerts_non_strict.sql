UPDATE {{table:cron_jobs}}
SET `arguments_json` = '{"args":[]}',
    `updated_at` = NOW()
WHERE `code` = 'check_log_alerts'
  AND `script_path` = 'core/tools/check_log_alerts.php'
  AND TRIM(COALESCE(`arguments_json`, '')) = '{"args":["--strict"]}';
