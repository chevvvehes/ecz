<?php

declare(strict_types=1);

$path = dirname(__DIR__) . '/storage/users.sqlite';
if (is_file($path) && !unlink($path)) {
    fwrite(STDERR, "Не удалось удалить базу. Остановите сервер и повторите попытку.\n");
    exit(1);
}

echo "База удалена. При следующем запросе она будет создана заново.\n";
