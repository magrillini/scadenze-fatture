<?php

declare(strict_types=1);

$query = $_GET;
$query['pagina'] = 'controllo';

header('Location: /index.php?' . http_build_query($query));
exit;
