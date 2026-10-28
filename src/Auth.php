<?php

declare(strict_types=1);

namespace ShoeStoreUsers;

use PDO;

final class Auth
{
    private const SESSION_KEY = 'current_user';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array{
     *     id: int,
     *     full_name: string,
     *     login: string,
     *     role_name: string,
     *     department_name: string
     * }|null
     */
    public function authenticate(string $login, string $password): ?array
    {
        $login = trim($login);
        $password = trim($password);
        if ($login === '' || $password === '') {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT
                users.id,
                users.full_name,
                users.login,
                users.password_hash,
                roles.name AS role_name,
                departments.name AS department_name
             FROM users
             JOIN roles ON roles.id = users.role_id
             JOIN departments ON departments.id = users.department_id
             WHERE users.login = :login COLLATE NOCASE
             LIMIT 1'
        );
        $statement->execute(['login' => $login]);
        $user = $statement->fetch();

        if ($user === false) {
            return null;
        }

        $enteredHash = hash('sha256', $password);
        if (!hash_equals((string) $user['password_hash'], $enteredHash)) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'full_name' => (string) $user['full_name'],
            'login' => (string) $user['login'],
            'role_name' => (string) $user['role_name'],
            'department_name' => (string) $user['department_name'],
        ];
    }

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name('shoe_store_users_session');
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'path' => '/',
        ]);
        session_start();
    }

    /**
     * @param array{
     *     id: int,
     *     full_name: string,
     *     login: string,
     *     role_name: string,
     *     department_name: string
     * } $user
     */
    public static function logIn(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = $user;
    }

    /**
     * @return array{
     *     id: int,
     *     full_name: string,
     *     login: string,
     *     role_name: string,
     *     department_name: string
     * }|null
     */
    public static function currentUser(): ?array
    {
        $user = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($user)
            || !isset(
                $user['id'],
                $user['full_name'],
                $user['login'],
                $user['role_name'],
                $user['department_name']
            )
        ) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'full_name' => (string) $user['full_name'],
            'login' => (string) $user['login'],
            'role_name' => (string) $user['role_name'],
            'department_name' => (string) $user['department_name'],
        ];
    }

    public static function logOut(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $parameters['path'],
                    'domain' => $parameters['domain'],
                    'secure' => $parameters['secure'],
                    'httponly' => $parameters['httponly'],
                    'samesite' => $parameters['samesite'] ?? 'Lax',
                ]
            );
        }

        session_destroy();
    }
}
