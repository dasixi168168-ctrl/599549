<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap/front_security.php';
front_security_apply(array('rate_limit' => false, 'output_buffer' => false));

header('Location: /public/index.php', true, 302);
exit;
