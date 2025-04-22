<?php
class Emote
{
    public string $id;
    public string $code;
    public string $ext;
    public string|null $uploaded_by;
    public int $created_at;
    public mixed $rating;
    public bool $is_in_user_set;

    function __construct($id, $code, $ext, $created_at, $uploaded_by, $is_in_user_set, $rating)
    {
        $this->id = $id;
        $this->code = $code;
        $this->ext = $ext;
        $this->created_at = $created_at;
        $this->uploaded_by = $uploaded_by;
        $this->is_in_user_set = $is_in_user_set;
        $this->rating = $rating;
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

    function get_uploaded_by()
    {
        return $this->uploaded_by;
    }

    function is_added_by_user()
    {
        return $this->is_in_user_set;
    }

    function get_rating()
    {
        return $this->rating;
    }
}
