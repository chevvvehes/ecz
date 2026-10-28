<?php

declare(strict_types=1);

namespace ShoeStoreUsers;

use PDO;
use RuntimeException;
use Throwable;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $root = dirname(__DIR__);
        $storage = $root . '/storage';
        if (!is_dir($storage)
            && !mkdir($storage, 0775, true)
            && !is_dir($storage)
        ) {
            throw new RuntimeException('Не удалось создать каталог storage.');
        }

        self::$connection = new PDO(
            'sqlite:' . $storage . '/users.sqlite',
            null,
            null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        self::$connection->exec('PRAGMA foreign_keys = ON');

        $tableExists = (int) self::$connection
            ->query(
                "SELECT COUNT(*) FROM sqlite_master
                 WHERE type = 'table' AND name = 'users'"
            )
            ->fetchColumn();

        if ($tableExists === 0) {
            self::initialize(self::$connection, $root);
        }

        return self::$connection;
    }

    private static function initialize(PDO $pdo, string $root): void
    {
        $schema = file_get_contents($root . '/database/schema.sql');
        if ($schema === false) {
            throw new RuntimeException('Файл database/schema.sql не найден.');
        }

        $pdo->beginTransaction();
        try {
            $pdo->exec($schema);
            $seed = require $root . '/database/seed.php';
            $seed($pdo);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }
}
