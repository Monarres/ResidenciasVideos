<?php
header('Content-Type: application/json');
echo json_encode([
    'success' => true, 
    'mensaje' => 'El archivo PHP funciona correctamente',
    'ruta_completa' => __FILE__
]);
?>
```

Luego abre:
```
http://localhost/learning/admin/test.php