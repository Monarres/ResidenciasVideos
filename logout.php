<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

$_SESSION = array();

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html>
<head>
    <script>
        // Reemplazar el historial completo
        window.location.replace('index.php');
    </script>
</head>
<body>
    <noscript>
        <meta http-equiv="refresh" content="0;url=index.php">
    </noscript>
</body>
</html>