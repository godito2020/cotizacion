<?php
// cotizacion/lib/Auth.php

// Session start is handled by init.php
// require_once __DIR__ . '/../config/database.php'; // This is also handled by init.php's autoloader or direct require of database.php

// Assuming init.php (which includes config.php and database.php and sets up autoloading)
// has been included by the calling script (e.g., login.php, index.php).
// So, getDBConnection() and other classes like User should be available.

class Auth {
    private $db;

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
                    WHERE (username = :usernameOrEmail OR email = :usernameOrEmail)";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':usernameOrEmail', $usernameOrEmail);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);

                // Store user information in session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['company_id'] = $user['company_id'];
                $_SESSION['logged_in_timestamp'] = time();
                // Add any other relevant user data you want in the session, but avoid sensitive data.

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
        // Unset all of the session variables.
        $_SESSION = array();

        // If it's desired to kill the session, also delete the session cookie.
        // Note: This will destroy the session, and not just the session data!
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Finally, destroy the session.
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

        try {
            // Fetch user details again to ensure data is fresh and avoid storing too much in session.
            // Or, if you stored enough non-sensitive details in $_SESSION, you could return those.
            $sql = "SELECT id, company_id, username, email, first_name, last_name, is_active
                    FROM users
                    WHERE id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            return $user ?: null;

        } catch (PDOException $e) {
            error_log("GetUser Error: " . $e->getMessage());
            return null;
        }
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

        // User class should be autoloaded by init.php
        $userRepo = new User();
        $userRoles = $userRepo->getRoles($userId); // This fetches an array like [['role_id' => 1, 'role_name' => 'Admin', ...], ...]

        if (empty($userRoles)) {
            return false;
        }

        $currentRoleNames = array_column($userRoles, 'role_name');

        if (is_string($roleNameOrNames)) {
            return in_array($roleNameOrNames, $currentRoleNames);
        } elseif (is_array($roleNameOrNames)) {
            foreach ($roleNameOrNames as $roleName) {
                if (in_array($roleName, $currentRoleNames)) {
                    return true; // Found at least one matching role
                }
            }
            return false; // No matching roles found
        }
        return false; // Invalid input type
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
            echo "<script type='text/javascript'>window.location.href='$url';</script>";
            echo "<noscript><meta http-equiv='refresh' content='0;url=$url' /></noscript>";
            exit;
        }
    }
}
?>
