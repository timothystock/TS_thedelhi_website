<?php
// Verifying whether a cookie is set or not
if(isset($_COOKIE["thedelhi"])){
    $_COOKIE["thedelhi"];
} else {
    setcookie("thedelhi", "thedelhi", time()+30*24*60*60);
}
?>