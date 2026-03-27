<?php
// cotizacion/lib/Auth.php

// Session start is handled by init.php
// require_once __DIR__ . '/../config/database.php'; // This is also handled by init.php's autoloader or direct require of database.php

// Assuming init.php (which includes config.php and database.php and sets up autoloading)
// has been included by the calling script (e.g., login.php, index.php).
// So, getDBConnection() and other classes like User should be available.

class Auth {
    private $db;
    private $cachedRoles = null;

    public function __construct() {
        // The getDBConnection function is made available through init.php -> config/database.php
        $this->db = getDBConnection();
    }

    /**
     * Hashes a password using PHP's password_hash function.
     *
     * @param string $password The password to hash.
     * @return string The hashed password.
     */
    public function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Attempts to log in a user.
     *
     * @param string $usernameOrEmail The username or email of the user.
     * @param string $password The user's plain text password.
     * @return bool True on successful login, false otherwise.
     */
    public function login(string $usernameOrEmail, string $password): bool {
        if (empty($usernameOrEmail) || empty($password)) {
            return false;
        }

        try {
            $sql = "SELECT id, company_id, username, email, password_hash, is_active
                    FROM users
                    WHERE (username = ? OR email = ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {
                // Reopen session (init.php closed it early to avoid lock contention)
                if (session_status() !== PHP_SESSION_ACTIVE) session_start();
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['company_id'] = $user['company_id'];
                $_SESSION['logged_in_timestamp'] = time();
                unset($_SESSION['user_data'], $_SESSION['user_roles']); // clear stale cache
                session_write_close();

                return true;
            }
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            // In a real app, don't expose detailed error messages to the user
        }
        return false;
    }

    /**
     * Logs out the current user by destroying the session.
     */
    public function logout(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();
    }

    /**
     * Checks if a user is currently logged in.
     *
     * @return bool True if logged in, false otherwise.
     */
    public function isLoggedIn(): bool {
        return isset($_SESSION['user_id']);
    }

    /**
     * Gets the ID of the currently logged-in user.
     *
     * @return int|null The user ID if logged in, null otherwise.
     */
    public function getUserId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Gets the details of the currently logged-in user.
     *
     * @return array|null An associative array of user details if logged in, null otherwise.
     *                    Excludes sensitive information like password_hash.
     */
    public function getUser(): ?array {
        if (!$this->isLoggedIn()) {
            return null;
        }

        // Return cached user data from session to avoid DB query on every page load
        if (isset($_SESSION['user_data'])) {
            return $_SESSION['user_data'];
        }

        try {
            $sql = "SELECT id, company_id, username, email, first_name, last_name, is_active
                    FROM users
                    WHERE id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if (session_status() !== PHP_SESSION_ACTIVE) session_start();
                $_SESSION['user_data'] = $user;
                session_write_close();
            }

            return $user ?: null;

        } catch (PDOException $e) {
            error_log("GetUser Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Clears the cached user data and roles from session (call after profile updates).
     */
    public function clearSessionCache(): void {
        unset($_SESSION['user_data'], $_SESSION['user_roles']);
    }

    /**
     * Gets the company ID of the currently logged-in user.
     *
     * @return int|null The company ID if logged in, null otherwise.
     */
    public function getCompanyId(): ?int {
        return $_SESSION['company_id'] ?? null;
    }

    /**
     * Checks if the currently logged-in user has one or more specified roles.
     *
     * @param string|array $roleNameOrNames A single role name (string) or an array of role names.
     * @return bool True if the user has at least one of the specified roles, false otherwise.
     */
    public function hasRole($roleNameOrNames): bool {
        if (!$this->isLoggedIn()) {
            return false;
        }

        $userId = $this->getUserId();
        if (!$userId) {
            return false;
        }

        // Use cached roles from session to avoid repeated DB queries
        if (!isset($_SESSION['user_roles'])) {
            $userRepo = new User();
            $userRoles = $userRepo->getRoles($userId);
            $roleNames = array_column($userRoles, 'role_name');
            // Save to session if possible (before headers are sent)
            if (session_status() === PHP_SESSION_ACTIVE || !headers_sent()) {
                if (session_status() !== PHP_SESSION_ACTIVE) session_start();
                $_SESSION['user_roles'] = $roleNames;
                session_write_close();
            }
            // Always keep in-memory cache for this request
            $this->cachedRoles = $roleNames;
        }

        $currentRoleNames = $_SESSION['user_roles'] ?? ($this->cachedRoles ?? []);

        if (empty($currentRoleNames)) {
            return false;
        }

        if (is_string($roleNameOrNames)) {
            return in_array($roleNameOrNames, $currentRoleNames);
        } elseif (is_array($roleNameOrNames)) {
            foreach ($roleNameOrNames as $roleName) {
                if (in_array($roleName, $currentRoleNames)) {
                    return true;
                }
            }
            return false;
        }
        return false;
    }

    /**
     * Verifies the password of the currently logged-in user.
     *
     * @param string $password The password to verify.
     * @return bool True if password is correct, false otherwise.
     */
    public function verifyCurrentUserPassword(string $password): bool {
        if (!$this->isLoggedIn() || empty($password)) {
            return false;
        }

        try {
            $sql = "SELECT password_hash FROM users WHERE id = :user_id AND is_active = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                return true;
            }
        } catch (PDOException $e) {
            error_log("VerifyPassword Error: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Redirects to a given URL.
     * Ensures that headers are not already sent.
     *
     * @param string $url The URL to redirect to.
     */
    public function redirect(string $url): void {
        if (!headers_sent()) {
            header("Location: " . $url);
            exit;
        } else {
            // Fallback if headers already sent (e.g., echo in a script)
            $escapedUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            echo "<script type='text/javascript'>window.location.href='$escapedUrl';</script>";
            echo "<noscript><meta http-equiv='refresh' content='0;url=$escapedUrl' /></noscript>";
            exit;
        }
    }
}
?>
