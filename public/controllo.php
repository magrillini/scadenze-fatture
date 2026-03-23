<?php

declare(strict_types=1);

$query = $_GET;
$query['pagina'] = 'fatture';

header('Location: /index.php?' . http_build_query($query));
exit;
