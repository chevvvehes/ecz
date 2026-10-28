<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $roles = [
        ['admin', 'Администратор'],
        ['manager', 'Менеджер'],
        ['client', 'Авторизированный клиент'],
    ];

    $departments = [
        ['administration', 'Администрация'],
        ['sales', 'Отдел продаж'],
        ['customer', 'Клиентский отдел'],
    ];

    $referenceStatement = $pdo->prepare(
        'INSERT INTO roles (code, name) VALUES (:code, :name)'
    );
    foreach ($roles as [$code, $name]) {
        $referenceStatement->execute(['code' => $code, 'name' => $name]);
    }

    $referenceStatement = $pdo->prepare(
        'INSERT INTO departments (code, name) VALUES (:code, :name)'
    );
    foreach ($departments as [$code, $name]) {
        $referenceStatement->execute(['code' => $code, 'name' => $name]);
    }

    $roleIds = $pdo
        ->query('SELECT code, id FROM roles')
        ->fetchAll(PDO::FETCH_KEY_PAIR);
    $departmentIds = $pdo
        ->query('SELECT code, id FROM departments')
        ->fetchAll(PDO::FETCH_KEY_PAIR);

    // ФИО, логины, пароли и роли соответствуют user_import.xlsx.
    $users = [
        ['admin', 'administration', 'Старикова Елена Павловна', '94d5ous@gmail.com', 'uzWC67'],
        ['admin', 'administration', 'Сазонов Руслан Германович', 'uth4iz@mail.com', '2L6KZG'],
        ['admin', 'administration', 'Одинцов Серафим Артёмович', 'yzls62@outlook.com', 'JlFRCZ'],
        ['manager', 'sales', 'Степанов Михаил Артёмович', '1diph5e@tutanota.com', '8ntwUp'],
        ['manager', 'sales', 'Ворсин Петр Евгеньевич', 'tjde7c@yahoo.com', 'YOyhfR'],
        ['manager', 'sales', 'Никифорова Весения Николаевна', 'wpmrc3do@tutanota.com', 'RSbvHv'],
        ['client', 'customer', 'Михайлюк Анна Вячеславовна', '5d4zbu@tutanota.com', 'rwVDh9'],
        ['client', 'customer', 'Ситдикова Елена Анатольевна', 'ptec8ym@yahoo.com', 'LdNyos'],
        ['client', 'customer', 'Ворсин Петр Евгеньевич', '1qz4kw@mail.com', 'gynQMT'],
        ['client', 'customer', 'Никифорова Весения Николаевна', '4np6se@mail.com', 'AtnDjr'],
    ];

    $statement = $pdo->prepare(
        'INSERT INTO users (
            role_id,
            department_id,
            full_name,
            login,
            password_hash
         ) VALUES (
            :role_id,
            :department_id,
            :full_name,
            :login,
            :password_hash
         )'
    );

    foreach ($users as [$role, $department, $fullName, $login, $password]) {
        $statement->execute([
            'role_id' => $roleIds[$role],
            'department_id' => $departmentIds[$department],
            'full_name' => $fullName,
            'login' => $login,
            'password_hash' => hash('sha256', $password),
        ]);
    }
};
