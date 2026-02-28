<?php
// backend/public/site/adminFtyhik5642sZ/logout.php

require_once dirname(__DIR__, 3) . '/core/bootstrap.php';
require_once dirname(__DIR__, 3) . '/core/auth/admin.php';

admin_logout();

header('Location: index.php');
exit;
