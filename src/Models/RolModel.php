<?php

namespace App\Models;

use App\Conn;
use PDO;

class RolModel extends DBModel
{
    public ?int $id = null;
    public string $name;

    public function __construct()
    {
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($value)
    {
        $this->name = $value;
    }

    public function getID()
    {
        return $this->id;
    }

    public function setId(?int $value)
    {
        $this->id = $value;
    }

    public static function isBevoegd($userid) {
        $mysql = Conn::getInstance();
        $db = $mysql->getPDO();

        $sql = "SELECT 1
                FROM gebruiker
                WHERE id = :userid
                AND rol IN ('Admin', 'Organisator')
                LIMIT 1
                ";

        $stmt = $db->prepare($sql);

        if (!$stmt->execute(['userid' => $userid])) {
            die('Query failed: ' . implode(' ', $stmt->errorInfo()));
        }

        
        return (bool) $stmt->fetchColumn();
    }
    
    public static function getAllRoles()
    {
        $mysql = Conn::getInstance();
        $db = $mysql->getPDO();

        $sql = "SELECT id, rol FROM rol";

        $stmt = $db->prepare($sql);

        if (!$stmt->execute()) {
            die('Query failed: ' . implode(' ', $stmt->errorInfo()));
        }

        $roles = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $role = new RolModel();
            $role->setid($row['id']);
            $role->setName($row['rol']);
            $roles[] = $role;
        }
        return $roles;
    }

    public static function getRoleIDByName($roleName)
    {
        $mysql = Conn::getInstance();
        $db = $mysql->getPDO();

        $sql = "SELECT id FROM rol WHERE rol = :rolName";

        $stmt = $db->prepare($sql);
        
        if (!$stmt->execute(['rolName' => $roleName])) {
            die('Query failed: ' . implode(' ', $stmt->errorInfo()));
        }

        $rolID = null;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rolID = $row['id'];
        }
        return $rolID;
    }

    public static function getRolesByUserId($id): array
    {
        $mysql = Conn::getInstance();
        $db = $mysql->getPDO();

        $sql = "SELECT g.rol, r.id
                FROM gebruiker g
                LEFT JOIN rol r ON r.rol = g.rol
                WHERE g.id = :gebruikerID";

        $stmt = $db->prepare($sql);

        if (!$stmt->execute(['gebruikerID' => $id])) {
            die('Query failed: ' . implode(' ', $stmt->errorInfo()));
        }

        $roles = [];
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['rol'] === null || $row['rol'] === '') {
                return $roles;
            }

            $role = new RolModel();
            if ($row['id'] !== null) {
                $role->setId((int) $row['id']);
            }
            $role->setName($row['rol']);
            $roles[] = $role;
        }
        return $roles;
    }
}
