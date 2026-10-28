<?php

declare(strict_types=1);

use ShoeStoreUsers\Auth;
use ShoeStoreUsers\Database;

require dirname(__DIR__) . '/src/Database.php';
require dirname(__DIR__) . '/src/Auth.php';

$pdo = Database::connection();
$auth = new Auth($pdo);

$count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($count !== 10) {
    throw new RuntimeException("Ожидалось 10 пользователей, получено: {$count}");
}

$roleCount = (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn();
$departmentCount = (int) $pdo
    ->query('SELECT COUNT(*) FROM departments')
    ->fetchColumn();
if ($roleCount !== 3 || $departmentCount !== 3) {
    throw new RuntimeException(
        "Ожидалось 3 роли и 3 отдела, получено: {$roleCount} и {$departmentCount}"
    );
}

$columns = $pdo
    ->query('PRAGMA table_info(users)')
    ->fetchAll(PDO::FETCH_COLUMN, 1);
foreach (['role_id', 'department_id'] as $requiredColumn) {
    if (!in_array($requiredColumn, $columns, true)) {
        throw new RuntimeException("Нет столбца users.{$requiredColumn}.");
    }
}

$foreignKeys = $pdo
    ->query('PRAGMA foreign_key_list(users)')
    ->fetchAll();
if (count($foreignKeys) !== 2) {
    throw new RuntimeException('В users должно быть два внешних ключа.');
}

$usersWithoutReferences = (int) $pdo
    ->query(
        'SELECT COUNT(*)
         FROM users
         LEFT JOIN roles ON roles.id = users.role_id
         LEFT JOIN departments ON departments.id = users.department_id
         WHERE roles.id IS NULL OR departments.id IS NULL'
    )
    ->fetchColumn();
if ($usersWithoutReferences !== 0) {
    throw new RuntimeException('Найдены пользователи без роли или отдела.');
}

$invalidHashes = (int) $pdo
    ->query(
        "SELECT COUNT(*) FROM users
         WHERE length(password_hash) <> 64
            OR password_hash GLOB '*[^0-9a-f]*'"
    )
    ->fetchColumn();
if ($invalidHashes !== 0) {
    throw new RuntimeException('В базе найден некорректный SHA-256.');
}

$user = $auth->authenticate('94d5ous@gmail.com', 'uzWC67');
if ($user === null
    || $user['full_name'] !== 'Старикова Елена Павловна'
    || $user['role_name'] !== 'Администратор'
    || $user['department_name'] !== 'Администрация'
) {
    throw new RuntimeException(
        'Вход или получение роли и отдела работают неверно.'
    );
}

if ($auth->authenticate('94d5ous@gmail.com', 'wrong-password') !== null) {
    throw new RuntimeException('Принят неправильный пароль.');
}

$trimmed = $auth->authenticate('  UTH4IZ@MAIL.COM  ', '  2L6KZG  ');
if ($trimmed === null
    || $trimmed['full_name'] !== 'Сазонов Руслан Германович'
) {
    throw new RuntimeException(
        'Trim или регистронезависимый логин не работают.'
    );
}

$managerRoleId = (int) $pdo
    ->query("SELECT id FROM roles WHERE code = 'manager'")
    ->fetchColumn();
$salesDepartmentId = (int) $pdo
    ->query("SELECT id FROM departments WHERE code = 'sales'")
    ->fetchColumn();

$statement = $pdo->prepare(
    'SELECT COUNT(*)
     FROM users
     WHERE role_id = :role_id
       AND department_id = :department_id'
);
$statement->execute([
    'role_id' => $managerRoleId,
    'department_id' => $salesDepartmentId,
]);
if ((int) $statement->fetchColumn() !== 3) {
    throw new RuntimeException('Фильтрация по роли и отделу работает неверно.');
}

$statement = $pdo->prepare(
    'SELECT COUNT(*) FROM users
     WHERE full_name LIKE :search COLLATE NOCASE'
);
$statement->execute(['search' => '%Степанов%']);
if ((int) $statement->fetchColumn() !== 1) {
    throw new RuntimeException('Поиск по ФИО работает неверно.');
}

echo "База, справочники, фильтры и аутентификация: OK\n";
