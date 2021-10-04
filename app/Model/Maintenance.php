<?php

class Maintenance extends AppModel
{
    function checkMaintenance($url = "")
    {
        $start_url = "/" . explode("/", $url)[1];
        $check = $this->find("first", ["conditions" => ["url LIKE" => $start_url . "%", "active" => 1]]);
        if (isset($check["Maintenance"]))
            $check = $check["Maintenance"];
        if ($check && (($check["url"] == $url) || ($check["sub_url"] && $url != "/" && strlen($url) >= strlen($check["url"]))))
            return $check;

        $is_full = $this->isFullMaintenance();
        if ($is_full)
            return $is_full;
        return false;
    }

    function isFullMaintenance()
    {
        $result = $this->find("first", ["conditions" => ["url" => "", "active" => 1]]);
        if (isset($result["Maintenance"]))
            $result = $result["Maintenance"];
        return $result;
    }
}