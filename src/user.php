<?php
class Badge
{
    public string $id;

    public static function from_array(array $arr, string $prefix = ""): Badge|null
    {
        if (!empty($prefix)) {
            $prefix .= "_";
        }
        if (!isset($arr["{$prefix}badge_id"])) {
            return null;
        }

        $b = new Badge();
        $b->id = $arr["{$prefix}badge_id"];

        return $b;
    }
}

class Role
{
    public string $name;
    public Badge|null $badge;

    public static function from_array(array $arr): Role|null
    {
        if (!isset($arr["role_name"])) {
            return null;
        }

        $r = new Role();

        $r->name = $arr["role_name"];
        $r->badge = Badge::from_array($arr, "role");

        return $r;
    }
}

class User
{
    public string $id;
    public string $username;
    public int $joined_at;
    public int $last_active_at;

    public Badge|null $custom_badge;

    public Role|null $role;

    public bool $private_profile;

    public static function from_array(array $arr): User
    {
        $u = new User();

        $u->id = $arr["id"];
        $u->username = $arr["username"];
        $u->joined_at = strtotime($arr["joined_at"] ?? "0");
        $u->last_active_at = strtotime($arr["last_active_at"] ?? "0");

        $u->private_profile = $row["private_profile"] ?? false;

        $u->custom_badge = Badge::from_array($arr, "custom");

        $u->role = Role::from_array($arr);

        return $u;
    }

    public static function get_user_by_id(PDO &$db, string $user_id): User|null
    {
        $stmt = $db->prepare("SELECT
                u.id,
                u.username,
                u.joined_at,
                u.last_active_at,

                up.private_profile,
                r.name AS role_name,
                r.badge_id AS role_badge_id,
                ub.badge_id AS custom_badge_id
            FROM users u
            INNER JOIN user_preferences up ON up.id = u.id
            LEFT JOIN role_assigns ra ON ra.user_id = u.id
            LEFT JOIN roles r ON r.id = ra.role_id
            LEFT JOIN user_badges ub ON ub.user_id = u.id
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);

        $u = null;

        if ($uploader_row = $stmt->fetch()) {
            $u = User::from_array($uploader_row);
        }

        return $u;
    }
}