<?php
// cotizacion/lib/User.php

// No need to explicitly require Auth.php if hashPassword is moved or Auth is only used elsewhere.
// However, if User class methods might call Auth methods directly (e.g. for login after registration),
// it might be needed, or rely on autoloader.
// For now, we assume hashPassword might be called statically or an Auth instance created if needed.

class User {
    private $db;
    private $auth; // Optional: if we need Auth methods like hashPassword

    public function __construct() {
        $this->db = getDBConnection(); // Assumes getDBConnection() is available from database.php (via init.php)
        // If hashPassword remains in Auth and is not static, we might need an Auth instance.
        // Or, make hashPassword static in Auth, or duplicate/move hashing logic here.
        // For this exercise, let's assume Auth class is available via autoloader and we can instantiate it
        // if we need its methods, or that hashPassword could be a static utility.
        // Let's make a choice: hashPassword should be a utility.
        // For now, let's assume Auth class has a static hashPassword or we duplicate it.
        // To keep it simple, I'll call Auth's hashPassword. It's better to have one place for this.
        // So, User class will need access to Auth's hashing.
        $this->auth = new Auth(); // Autoloader should handle this.
    }

    /**
     * Creates a new user in the database.
     *
     * @param int    $company_id The ID of the company the user belongs to.
     * @param string $username   The username.
     * @param string $password   The plain text password.
     * @param string $email      The user's email address.
     * @param string $firstName  The user's first name.
     * @param string $lastName   The user's last name.
     * @param bool   $isActive   Whether the user account is active (default: true).
     * @return int|false The ID of the newly created user, or false on failure.
     */
    public function create(int $company_id, string $username, string $password, string $email, string $firstName, string $lastName, bool $isActive = true): int|false {
        if (empty($username) || empty($password) || empty($email) || empty($firstName) || empty($lastName) || $company_id <= 0) {
            error_log("User::create - Missing required fields or invalid company_id.");
            return false;
        }

        if ($this->findByUsername($username)) {
            error_log("User::create - Username already exists: " . $username);
            return false; // Username already exists
        }

        if ($this->findByEmail($email)) {
            error_log("User::create - Email already exists: " . $email);
            return false; // Email already exists
        }

        $passwordHash = $this->auth->hashPassword($password); // Using Auth's hashing method

        try {
            $sql = "INSERT INTO users (company_id, username, password_hash, email, first_name, last_name, is_active, created_at)
                    VALUES (:company_id, :username, :password_hash, :email, :first_name, :last_name, :is_active, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password_hash', $passwordHash);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':first_name', $firstName);
            $stmt->bindParam(':last_name', $lastName);
            $stmt->bindParam(':is_active', $isActive, PDO::PARAM_BOOL);

            if ($stmt->execute()) {
                return (int)$this->db->lastInsertId();
            } else {
                error_log("User::create - Failed to execute statement for username: " . $username);
                return false;
            }
        } catch (PDOException $e) {
            error_log("User::create Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Finds a user by their username.
     *
     * @param string $username The username to search for.
     * @return array|false An associative array of user details if found, false otherwise.
     */
    public function findByUsername(string $username): array|false {
        try {
            $sql = "SELECT id, company_id, username, email, first_name, last_name, is_active
                    FROM users WHERE username = :username";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("User::findByUsername Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Finds a user by their email address.
     *
     * @param string $email The email address to search for.
     * @return array|false An associative array of user details if found, false otherwise.
     */
    public function findByEmail(string $email): array|false {
        try {
            $sql = "SELECT id, company_id, username, email, first_name, last_name, is_active
                    FROM users WHERE email = :email";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("User::findByEmail Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Finds a user by their ID.
     *
     * @param int $userId The ID of the user.
     * @return array|false An associative array of user details if found, false otherwise.
     */
    public function findById(int $userId): array|false {
        try {
            $sql = "SELECT id, company_id, username, email, first_name, last_name, is_active
                    FROM users WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("User::findById Error: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Assigns a role to a user.
     *
     * @param int $userId The ID of the user.
     * @param int $roleId The ID of the role.
     * @return bool True on success, false on failure.
     */
    public function assignRole(int $userId, int $roleId): bool {
        // Check if user and role exist (optional, depends on DB constraints)
        // For now, assume they exist or foreign key constraints will handle it.

        // Check if user already has this role to prevent duplicates
        $currentRoles = $this->getRoles($userId);
        foreach ($currentRoles as $currentRole) {
            if ($currentRole['role_id'] == $roleId) {
                return true; // Role already assigned
            }
        }

        try {
            $sql = "INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            // Handle potential duplicate entry errors if not checked above, or other integrity constraints
            error_log("User::assignRole Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gets all roles assigned to a specific user.
     *
     * @param int $userId The ID of the user.
     * @return array An array of role details (e.g., role_id, role_name, description).
     */
    public function getRoles(int $userId): array {
        try {
            $sql = "SELECT r.id as role_id, r.role_name, r.description
                    FROM roles r
                    JOIN user_roles ur ON r.id = ur.role_id
                    WHERE ur.user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("User::getRoles Error: " . $e->getMessage());
            return []; // Return empty array on error
        }
    }
}
?>
