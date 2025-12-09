<?php
session_start();
require_once("../conexion.php");

// Verificar sesión de usuario
if(!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'usuario'){ 
    header("Location: ../index.php"); 
    exit; 
}

$id_usuario = $_SESSION['id_usuario'];
$mensaje = "";
$tipo_mensaje = "";

// Procesar cambio de contraseña
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['cambiar_password'])) {
    $password_actual = $_POST['password_actual'];
    $password_nueva = $_POST['password_nueva'];
    $password_confirmar = $_POST['password_confirmar'];
    
    // Validaciones
    if (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
        $mensaje = "Todos los campos son obligatorios";
        $tipo_mensaje = "danger";
    } elseif ($password_nueva !== $password_confirmar) {
        $mensaje = "Las contraseñas nuevas no coinciden";
        $tipo_mensaje = "danger";
    } elseif (strlen($password_nueva) < 6) {
        $mensaje = "La nueva contraseña debe tener al menos 6 caracteres";
        $tipo_mensaje = "danger";
    } else {
        // Verificar contraseña actual
        $stmt = $pdo->prepare("SELECT contrasena FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (password_verify($password_actual, $usuario['contrasena'])) {
            // Actualizar contraseña
            $nueva_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET contrasena = ? WHERE id_usuario = ?");
            $stmt->execute([$nueva_hash, $id_usuario]);
            
            $mensaje = "✅ Contraseña actualizada correctamente";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "La contraseña actual es incorrecta";
            $tipo_mensaje = "danger";
        }
    }
}

// Obtener datos del usuario
$stmt = $pdo->prepare("SELECT u.nombre, u.email, c.nombre as area_nombre, un.nombre as unidad_nombre
                       FROM usuarios u
                       LEFT JOIN carpetas c ON u.id_carpeta = c.id_carpeta
                       LEFT JOIN unidades un ON u.id_unidad = un.id_unidad
                       WHERE u.id_usuario = ?");
$stmt->execute([$id_usuario]);
$usuario_data = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>


<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v6.5.1/css/all.css">


  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mi Perfil</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  background: #f0d5e8;
  font-family: 'Poppins', sans-serif;
  min-height: 100vh;
  padding-top: 100px;
}

.top-header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  background: linear-gradient(135deg, #b893cc, #f5a3c7);
  box-shadow: 0 2px 10px rgba(0,0,0,0.15);
  z-index: 1000;
  padding: 20px 0;
  margin: 15px;
  border-radius: 20px;
}

.top-header .container-fluid {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 30px;
  position: relative;
}

.top-header h2 {
  color: white;
  font-weight: 600;
  margin: 0;
  font-size: 1.5rem;
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
}

.user-info {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-left: auto;
}

.btn-logout {
  background: white;
  border: none;
  color: #9b7cb8;
  font-weight: 500;
  border-radius: 25px;
  padding: 8px 20px;
  transition: 0.3s;
  text-decoration: none;
  display: inline-block;
}

.btn-logout:hover {
  background: #f8f9fa;
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(0,0,0,0.2);
  color: #9b7cb8;
}

.container {
  max-width: 800px;
  padding: 20px 15px;
}

.card {
  border: none;
  border-radius: 15px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
  background: white;
}

.profile-header {
  background: linear-gradient(135deg, rgba(245, 163, 199, 0.1), rgba(155, 124, 184, 0.1));
  border-radius: 15px;
  padding: 30px;
  text-align: center;
  margin-bottom: 20px;
}

.profile-icon {
  font-size: 5rem;
  margin-bottom: 15px;
}

.profile-header h3 {
  color: #9b7cb8;
  font-weight: 600;
  margin-bottom: 5px;
}

.profile-header p {
  color: #666;
  margin: 0;
}

.info-row {
  display: flex;
  align-items: center;
  padding: 15px;
  border-bottom: 1px solid #f0f0f0;
}

.info-row:last-child {
  border-bottom: none;
}

.info-label {
  font-weight: 600;
  color: #9b7cb8;
  min-width: 150px;
}

.info-value {
  color: #333;
}

.badge-custom {
  background: linear-gradient(135deg, rgba(245, 163, 199, 0.2), rgba(155, 124, 184, 0.2));
  color: #9b7cb8;
  padding: 5px 12px;
  border-radius: 15px;
  font-size: 0.9rem;
  font-weight: 500;
}

.form-control {
  border-radius: 25px;
  padding: 10px 20px;
  border: 1px solid #ddd;
}

.btn-primary {
  background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
  border: none;
  color: white;
  font-weight: 500;
  border-radius: 25px;
  padding: 10px 25px;
  transition: 0.3s;
}

.btn-primary:hover {
  background: linear-gradient(135deg, #9b7cb8, #f5a3c7);
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(155, 124, 184, 0.3);
}

.alert {
  border-radius: 15px;
  border: none;
}

.section-title {
  color: #9b7cb8;
  font-weight: 600;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.password-strength {
  height: 8px;
  background: #e0e0e0;
  border-radius: 10px;
  margin-top: 8px;
  overflow: hidden;
  transition: all 0.3s ease;
}

.password-strength-bar {
  height: 100%;
  width: 0%;
  transition: all 0.3s ease;
  border-radius: 10px;
}

.password-strength-bar.weak {
  width: 33.33%;
  background: linear-gradient(90deg, #dc3545, #ff6b6b);
}

.password-strength-bar.medium {
  width: 66.66%;
  background: linear-gradient(90deg, #ffc107, #ffed4e);
}

.password-strength-bar.strong {
  width: 100%;
  background: linear-gradient(90deg, #28a745, #5cb85c);
}

.password-strength-text {
  font-size: 0.85rem;
  margin-top: 5px;
  font-weight: 500;
  transition: all 0.3s ease;
}

.password-strength-text.weak {
  color: #dc3545;
}

.password-strength-text.medium {
  color: #ffc107;
}

.password-strength-text.strong {
  color: #28a745;
}

.password-requirements {
  background: #f8f9fa;
  border-radius: 10px;
  padding: 15px;
  margin-top: 10px;
  font-size: 0.85rem;
}

.password-requirements ul {
  margin: 0;
  padding-left: 20px;
}

.password-requirements li {
  margin: 5px 0;
  color: #666;
}

.password-requirements li.met {
  color: #28a745;
}

.password-requirements li.met::marker {
  content: "✓ ";
}

@media (max-width: 768px) {
  body {
    padding-top: 90px;
  }
  
  .top-header {
    margin: 10px;
  }
  
  .top-header h2 {
    font-size: 1.2rem;
  }
  
  .info-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 5px;
  }
  
  .info-label {
    min-width: auto;
  }
}
  </style>
</head>
<body>

<div class="top-header">
  <div class="container-fluid">
    <h2><i class="fa-solid fa-user" style="color: #B197FC;"></i>Mi Perfil</h2>
    <div class="user-info">
      <a href="dashboard.php" class="btn-logout"><i class="fa-solid fa-angle-left" style="color: #B197FC;"></i> Volver</a>
    </div>
  </div>
</div>

<div class="container">
  
  <!-- Mensajes -->
  <?php if (!empty($mensaje)): ?>
    <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show" role="alert">
      <?= $mensaje ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Información del perfil -->
  <div class="profile-header">
    <div class="profile-icon"><i class="fa-solid fa-user" style="color: #B197FC;"></i>
</div>
    <h3><?= htmlspecialchars($usuario_data['nombre']) ?></h3>
    <p><?= htmlspecialchars($usuario_data['email']) ?></p>
  </div>

  <!-- Datos del usuario -->
  <div class="card p-4 mb-4">
    <h5 class="section-title"><i class="fa-solid fa-clipboard-list" style="color: #B197FC;"></i> Mis Datos</h5>
    
    <div class="info-row">
      <div class="info-label"><i class="fa-solid fa-user" style="color: #B197FC;"></i> Nombre:</div>
      <div class="info-value"><?= htmlspecialchars($usuario_data['nombre']) ?></div>
    </div>
    
    <div class="info-row">
      <div class="info-label"><i class="fa-solid fa-at" style="color: #B197FC;"></i> Correo:</div>
      <div class="info-value"><?= htmlspecialchars($usuario_data['email']) ?></div>
    </div>
    
    <div class="info-row">
      <div class="info-label"><i class="fa-solid fa-location-dot" style="color: #B197FC;"></i> Área asignada:</div>
      <div class="info-value">
        <?php if($usuario_data['area_nombre']): ?>
          <span class="badge-custom"><?= htmlspecialchars($usuario_data['area_nombre']) ?></span>
        <?php else: ?>
          <span class="text-muted">Sin área asignada</span>
        <?php endif; ?>
      </div>
    </div>
    
    <div class="info-row">
      <div class="info-label"><i class="fa-solid fa-building-columns" style="color: #B197FC;"></i> Sucursal:</div>
      <div class="info-value">
        <?php if($usuario_data['unidad_nombre']): ?>
          <span class="badge-custom"><?= htmlspecialchars($usuario_data['unidad_nombre']) ?></span>
        <?php else: ?>
          <span class="text-muted">Sin Sucursal asignada</span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Cambiar contraseña -->
  <div class="card p-4">
    <h5 class="section-title">Cambiar Contraseña</h5>
    
    <form method="POST">
      <input type="hidden" name="cambiar_password" value="1">
      
      <div class="mb-3">
        <label class="form-label fw-bold">Contraseña Actual</label>
        <input type="password" name="password_actual" class="form-control" 
               placeholder="Ingresa tu contraseña actual" required>
      </div>
      
      <div class="mb-3">
        <label class="form-label fw-bold">Nueva Contraseña</label>
        <input type="password" name="password_nueva" id="password_nueva" class="form-control" 
               placeholder="Mínimo 8 caracteres" minlength="6" required>
        
        <!-- Barra de seguridad -->
        <div class="password-strength">
          <div class="password-strength-bar" id="strengthBar"></div>
        </div>
        <div class="password-strength-text" id="strengthText"></div>
        
        <!-- Requisitos de contraseña -->
        <div class="password-requirements">
          <strong>Requisitos de seguridad:</strong>
          <ul id="requirementsList">
            <li id="req-length">Al menos 8 caracteres</li>
            <li id="req-uppercase">Una letra mayúscula</li>
            <li id="req-lowercase">Una letra minúscula</li>
            <li id="req-number">Un número</li>
            <li id="req-special">Un carácter especial (!@#$%^&*)</li>
          </ul>
        </div>
      </div>
      
      <div class="mb-3">
        <label class="form-label fw-bold">Confirmar Nueva Contraseña</label>
        <input type="password" name="password_confirmar" class="form-control" 
               placeholder="Repite la nueva contraseña" minlength="6" required>
      </div>
      
      <button type="submit" class="btn btn-primary w-100">
        Actualizar Contraseña
      </button>
    </form>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-cerrar alertas después de 5 segundos
setTimeout(function() {
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach(alert => {
    const bsAlert = new bootstrap.Alert(alert);
    bsAlert.close();
  });
}, 5000);

// Validador de seguridad de contraseña
const passwordInput = document.getElementById('password_nueva');
const strengthBar = document.getElementById('strengthBar');
const strengthText = document.getElementById('strengthText');

// Elementos de requisitos
const reqLength = document.getElementById('req-length');
const reqUppercase = document.getElementById('req-uppercase');
const reqLowercase = document.getElementById('req-lowercase');
const reqNumber = document.getElementById('req-number');
const reqSpecial = document.getElementById('req-special');

passwordInput.addEventListener('input', function() {
  const password = this.value;
  let strength = 0;
  let strengthLevel = '';
  
  // Reset clases
  strengthBar.className = 'password-strength-bar';
  strengthText.className = 'password-strength-text';
  
  // Verificar requisitos
  const hasLength = password.length >= 6;
  const hasUppercase = /[A-Z]/.test(password);
  const hasLowercase = /[a-z]/.test(password);
  const hasNumber = /[0-9]/.test(password);
  const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
  
  // Actualizar lista de requisitos
  reqLength.classList.toggle('met', hasLength);
  reqUppercase.classList.toggle('met', hasUppercase);
  reqLowercase.classList.toggle('met', hasLowercase);
  reqNumber.classList.toggle('met', hasNumber);
  reqSpecial.classList.toggle('met', hasSpecial);
  
  // Calcular nivel de seguridad
  if (hasLength) strength++;
  if (hasUppercase) strength++;
  if (hasLowercase) strength++;
  if (hasNumber) strength++;
  if (hasSpecial) strength++;
  
  // Determinar nivel y color
  if (password.length === 0) {
    strengthBar.style.width = '0%';
    strengthText.textContent = '';
  } else if (strength <= 2) {
    strengthLevel = 'weak';
    strengthBar.className = 'password-strength-bar weak';
    strengthText.className = 'password-strength-text weak';
    strengthText.textContent = '🔴 Débil - Mejora tu contraseña';
  } else if (strength <= 4) {
    strengthLevel = 'medium';
    strengthBar.className = 'password-strength-bar medium';
    strengthText.className = 'password-strength-text medium';
    strengthText.textContent = '🟡 Media - Puedes hacerla más segura';
  } else {
    strengthLevel = 'strong';
    strengthBar.className = 'password-strength-bar strong';
    strengthText.className = 'password-strength-text strong';
    strengthText.textContent = '🟢 Fuerte - ¡Excelente contraseña!';
  }
});

// Validar que las contraseñas coincidan al enviar
document.querySelector('form').addEventListener('submit', function(e) {
  const nueva = document.getElementById('password_nueva').value;
  const confirmar = document.querySelector('input[name="password_confirmar"]').value;
  
  if (nueva !== confirmar) {
    e.preventDefault();
    alert('Las contraseñas no coinciden. Por favor verifica.');
    return false;
  }
});
</script>
</body>
</html>