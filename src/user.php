<?php
class User
{
    private int $id;
    private string $username;
    private int $joined_at;
    private int $last_active_at;

    function __construct($row)
    {
        $this->id = $row["id"];
        $this->username = $row["username"];
        $this->joined_at = strtotime($row["joined_at"]);
        $this->last_active_at = strtotime($row["last_active_at"]);
    }

    function id()
    {
        return $this->id;
    }

    function username()
    {
        return $this->username;
    }

    function joined_at()
    {
        return $this->joined_at;
    }

    function last_active_at()
    {
        return $this->last_active_at;
    }
}