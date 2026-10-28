<?php

declare(strict_types=1);

use ShoeStoreUsers\Auth;
use ShoeStoreUsers\Database;

require dirname(__DIR__) . '/src/Database.php';
require dirname(__DIR__) . '/src/Auth.php';

Auth::startSession();

function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function redirectToHome(): never
{
    header('Location: /');
    exit;
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])
        || !is_string($_SESSION['csrf_token'])
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validCsrfToken(mixed $token): bool
{
    return is_string($token)
        && hash_equals(csrfToken(), $token);
}

$pdo = Database::connection();
$auth = new Auth($pdo);
$error = '';
$enteredLogin = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $error = 'Срок действия формы истёк. Обновите страницу и повторите попытку.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'logout') {
            Auth::logOut();
            redirectToHome();
        }

        if ($action === 'login') {
            $enteredLogin = trim((string) ($_POST['login'] ?? ''));
            $password = trim((string) ($_POST['password'] ?? ''));
            $user = $auth->authenticate($enteredLogin, $password);

            if ($user === null) {
                $error = 'Неверный логин или пароль.';
            } else {
                Auth::logIn($user);
                redirectToHome();
            }
        }
    }
}

$currentUser = Auth::currentUser();

if ($currentUser === null):
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход — пользователи</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="login-page">
<main class="login-shell">
    <section class="login-intro">
        <div class="brand-mark">О</div>
        <p class="eyebrow">ООО «Обувь»</p>
        <h1>Авторизация</h1>
        <p>
            Введите логин и пароль пользователя из первого задания.
            После входа откроется список пользователей с поиском по ФИО.
        </p>
    </section>

    <section class="login-card" aria-labelledby="login-title">
        <div>
            <p class="eyebrow">Система пользователей</p>
            <h2 id="login-title">Вход в систему</h2>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/" class="login-form">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

            <label for="login">Логин</label>
            <input
                id="login"
                name="login"
                type="text"
                value="<?= e($enteredLogin) ?>"
                required
                autofocus
                autocomplete="username"
                placeholder="example@mail.com"
            >

            <label for="password">Пароль</label>
            <input
                id="password"
                name="password"
                type="password"
                required
                autocomplete="current-password"
                placeholder="Введите пароль"
            >

            <button type="submit">Войти</button>
        </form>
    </section>
</main>
</body>
</html>
<?php
exit;
endif;

$roles = $pdo
    ->query('SELECT id, name FROM roles ORDER BY name COLLATE NOCASE')
    ->fetchAll();
$departments = $pdo
    ->query('SELECT id, name FROM departments ORDER BY name COLLATE NOCASE')
    ->fetchAll();

$search = trim((string) ($_GET['search'] ?? ''));
$roleId = filter_var(
    $_GET['role_id'] ?? 0,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
) ?: 0;
$departmentId = filter_var(
    $_GET['department_id'] ?? 0,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
) ?: 0;

$validRoleIds = array_map(
    static fn (array $role): int => (int) $role['id'],
    $roles
);
$validDepartmentIds = array_map(
    static fn (array $department): int => (int) $department['id'],
    $departments
);

if (!in_array($roleId, $validRoleIds, true)) {
    $roleId = 0;
}
if (!in_array($departmentId, $validDepartmentIds, true)) {
    $departmentId = 0;
}

$parameters = [];
$conditions = [];
$sql = 'SELECT
            users.full_name,
            users.login,
            roles.name AS role_name,
            departments.name AS department_name
        FROM users
        JOIN roles ON roles.id = users.role_id
        JOIN departments ON departments.id = users.department_id';

if ($search !== '') {
    $conditions[] = 'users.full_name LIKE :search COLLATE NOCASE';
    $parameters['search'] = '%' . $search . '%';
}
if ($roleId > 0) {
    $conditions[] = 'users.role_id = :role_id';
    $parameters['role_id'] = $roleId;
}
if ($departmentId > 0) {
    $conditions[] = 'users.department_id = :department_id';
    $parameters['department_id'] = $departmentId;
}

if ($conditions !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY users.full_name COLLATE NOCASE,
                   users.login COLLATE NOCASE';
$statement = $pdo->prepare($sql);
$statement->execute($parameters);
$users = $statement->fetchAll();
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Пользователи</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="container header-content">
        <div class="brand">
            <div class="brand-mark small">О</div>
            <div>
                <strong>ООО «Обувь»</strong>
                <small>Список пользователей</small>
            </div>
        </div>

        <div class="account">
            <div>
                <strong><?= e($currentUser['full_name']) ?></strong>
                <small><?= e($currentUser['role_name']) ?> · <?= e($currentUser['department_name']) ?></small>
                <small><?= e($currentUser['login']) ?></small>
            </div>
            <form method="post" action="/">
                <input type="hidden" name="action" value="logout">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <button class="secondary-button" type="submit">Выйти</button>
            </form>
        </div>
    </div>
</header>

<main class="container content">
    <section class="page-heading">
        <div>
            <p class="eyebrow">ООО «Обувь»</p>
            <h1>Пользователи</h1>
            <p class="subtitle">
                Страница доступна только после успешной аутентификации.
                Поиск выполняется по ФИО. Роль и отдел можно выбрать
                в выпадающих списках.
            </p>
        </div>
        <div class="counter">
            <strong><?= count($users) ?></strong>
            <span>записей</span>
        </div>
    </section>

    <form class="filter-form" method="get" action="/">
        <div class="filters-grid">
            <label for="search">
                Поиск по ФИО
                <input
                    id="search"
                    type="search"
                    name="search"
                    value="<?= e($search) ?>"
                    placeholder="Например, Степанов"
                    autocomplete="off"
                >
            </label>

            <label for="role_id">
                Роль
                <select id="role_id" name="role_id">
                    <option value="0">Все роли</option>
                    <?php foreach ($roles as $role): ?>
                        <option
                            value="<?= e($role['id']) ?>"
                            <?= $roleId === (int) $role['id'] ? 'selected' : '' ?>
                        ><?= e($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label for="department_id">
                Отдел
                <select id="department_id" name="department_id">
                    <option value="0">Все отделы</option>
                    <?php foreach ($departments as $department): ?>
                        <option
                            value="<?= e($department['id']) ?>"
                            <?= $departmentId === (int) $department['id'] ? 'selected' : '' ?>
                        ><?= e($department['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="filter-actions">
            <button type="submit">Применить</button>
            <?php if ($search !== '' || $roleId > 0 || $departmentId > 0): ?>
                <a href="/">Сбросить фильтры</a>
            <?php endif; ?>
        </div>
    </form>

    <section class="table-card">
        <?php if ($users === []): ?>
            <div class="empty-state">
                <h2>Пользователи не найдены</h2>
                <p>Измените поисковый запрос или сбросьте поиск.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>ФИО</th>
                        <th>Логин</th>
                        <th>Роль</th>
                        <th>Отдел</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><strong><?= e($user['full_name']) ?></strong></td>
                            <td><?= e($user['login']) ?></td>
                            <td><span class="tag"><?= e($user['role_name']) ?></span></td>
                            <td><?= e($user['department_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer>
    <div class="container">PHP 8, SQLite, PDO и сессии</div>
</footer>
</body>
</html>
