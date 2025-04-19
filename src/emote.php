<?php
class Emote
{
    public string $id;
    public string $code;
    public string $ext;
    public int $created_at;

    function __construct($id, $code, $ext, $created_at)
    {
        $this->id = $id;
        $this->code = $code;
        $this->ext = $ext;
        $this->created_at = $created_at;
    }

    function get_id()
    {
        return $this->id;
    }

    function get_code()
    {
        return $this->code;
    }

    function get_ext()
    {
        return $this->ext;
    }

    function get_created_at()
    {
        return $this->created_at;
    }
}
