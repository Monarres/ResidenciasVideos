<?php
session_start();
require_once("../conexion.php");

// Verificar sesión de usuario
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'usuario') {
    header('Location: ../login.php');
    exit;
}

$id_usuario  = $_SESSION['id_usuario'];
$nombre      = $_SESSION['nombre'];
$id_carpeta  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_carpeta) {
    header('Location: carpetas.php');
    exit;
}

// Obtener datos de la carpeta
$stmtC = $pdo->prepare("SELECT * FROM carpetas WHERE id_carpeta = ?");
$stmtC->execute([$id_carpeta]);
$carpeta = $stmtC->fetch(PDO::FETCH_ASSOC);

if (!$carpeta) {
    header('Location: carpetas.php');
    exit;
}

// ── Verificar que el usuario terminó todos los cuestionarios individuales ──
// Obtener todos los videos del módulo
$stmtV = $pdo->prepare("SELECT id_video FROM videos WHERE id_carpeta = ?");
$stmtV->execute([$id_carpeta]);
$videos = $stmtV->fetchAll(PDO::FETCH_COLUMN);

$todosCompletos = true;
foreach ($videos as $id_video) {
    // Preguntas de tipo incisos
    $stmtPI = $pdo->prepare("
        SELECT COUNT(*) FROM cuestionarios 
        WHERE id_video = ? AND tipo_pregunta = 'incisos'
    ");
    $stmtPI->execute([$id_video]);
    $totalIncisos = (int)$stmtPI->fetchColumn();

    if ($totalIncisos > 0) {
        $stmtRI = $pdo->prepare("
            SELECT COUNT(*) FROM respuestas_usuario ru
            JOIN cuestionarios c ON ru.id_cuestionario = c.id_cuestionario
            WHERE c.id_video = ? AND ru.id_usuario = ?
        ");
        $stmtRI->execute([$id_video, $id_usuario]);
        $respondidosIncisos = (int)$stmtRI->fetchColumn();
        if ($respondidosIncisos < $totalIncisos) { $todosCompletos = false; break; }
    }

    // Preguntas de tipo archivo
    $stmtPA = $pdo->prepare("
        SELECT COUNT(*) FROM cuestionarios 
        WHERE id_video = ? AND tipo_pregunta = 'archivo'
    ");
    $stmtPA->execute([$id_video]);
    $totalArchivo = (int)$stmtPA->fetchColumn();

    if ($totalArchivo > 0) {
        $stmtRA = $pdo->prepare("
            SELECT COUNT(*) FROM respuestas_archivo ra
            JOIN cuestionarios c ON ra.id_cuestionario = c.id_cuestionario
            WHERE c.id_video = ? AND ra.id_usuario = ?
        ");
        $stmtRA->execute([$id_video, $id_usuario]);
        $respondidosArchivo = (int)$stmtRA->fetchColumn();
        if ($respondidosArchivo < $totalArchivo) { $todosCompletos = false; break; }
    }
}

if (!$todosCompletos) {
    $_SESSION['error_acceso'] = 'Debes completar todos los cuestionarios de los videos antes de acceder al cuestionario del módulo.';
    header("Location: videos_usuario.php?carpeta=$id_carpeta");
    exit;
}

// ── Obtener preguntas del cuestionario del módulo ──
$stmtQ = $pdo->prepare("SELECT * FROM cuestionario_modulo WHERE id_carpeta = ? ORDER BY orden ASC");
$stmtQ->execute([$id_carpeta]);
$preguntas = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

// ── Verificar si el usuario ya respondió este cuestionario ──
$yaRespondio = false;
if (count($preguntas) > 0) {
    // Verificar respuestas de incisos del módulo
    $stmtYa = $pdo->prepare("
        SELECT COUNT(*) FROM respuestas_modulo 
        WHERE id_usuario = ? AND id_carpeta = ?
    ");
    // Si no tienes tabla respuestas_modulo aún, se crea abajo en guardar_respuestas_modulo.php
    // Aquí simplemente lo dejamos en false para que siempre pueda responder
    // (puedes agregar la lógica una vez que tengas la tabla)
}

// ── Procesar envío del cuestionario ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correctas = 0;
    $total_incisos = 0;
    $tieneArchivo = false;

    foreach ($preguntas as $p) {
        if ($p['tipo_pregunta'] === 'incisos') {
            $total_incisos++;
            $respuesta = $_POST['resp'][$p['id_cuestionario_modulo']] ?? '';

            // Guardar respuesta
            $stmtIns = $pdo->prepare("
                INSERT INTO respuestas_modulo 
                    (id_usuario, id_carpeta, id_cuestionario_modulo, respuesta, correcta)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE respuesta = VALUES(respuesta), correcta = VALUES(correcta)
            ");
            $esCorrecta = (strtoupper($respuesta) === strtoupper($p['respuesta_correcta'])) ? 1 : 0;
            if ($esCorrecta) $correctas++;
            $stmtIns->execute([$id_usuario, $id_carpeta, $p['id_cuestionario_modulo'], $respuesta, $esCorrecta]);

        } elseif ($p['tipo_pregunta'] === 'archivo') {
            $tieneArchivo = true;
            $idPregunta = $p['id_cuestionario_modulo'];

            if (isset($_FILES['archivo_modulo']['name'][$idPregunta]) && $_FILES['archivo_modulo']['error'][$idPregunta] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/respuestas_modulo/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                $ext = pathinfo($_FILES['archivo_modulo']['name'][$idPregunta], PATHINFO_EXTENSION);
                $nombreArchivo = "modulo_{$id_carpeta}_usuario_{$id_usuario}_preg_{$idPregunta}_" . time() . ".$ext";
                $rutaDestino   = $uploadDir . $nombreArchivo;

                if (move_uploaded_file($_FILES['archivo_modulo']['tmp_name'][$idPregunta], $rutaDestino)) {
                    $stmtArch = $pdo->prepare("
                        INSERT INTO respuestas_archivo_modulo
                            (id_usuario, id_carpeta, id_cuestionario_modulo, ruta_archivo)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE ruta_archivo = VALUES(ruta_archivo), fecha_subida = NOW()
                    ");
                    $stmtArch->execute([$id_usuario, $id_carpeta, $idPregunta, $rutaDestino]);
                }
            }
        }
    }

    // Calcular resultado
    if ($total_incisos > 0) {
        $porcentaje = round(($correctas / $total_incisos) * 100);
        $_SESSION['resultado_modulo'] = [
            'tipo'       => $tieneArchivo ? 'mixto' : 'incisos',
            'correctas'  => $correctas,
            'total'      => $total_incisos,
            'porcentaje' => $porcentaje,
        ];
    } else {
        $_SESSION['resultado_modulo'] = ['tipo' => 'archivo'];
    }

    header("Location: cuestionario_modulo_usuario.php?id=$id_carpeta&enviado=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cuestionario del módulo — <?= htmlspecialchars($carpeta['nombre']) ?></title>
  <!-- Ajusta las rutas de tus estilos según tu proyecto -->
  <link rel="stylesheet" href="../assets/css/usuario.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<div class="top-header">
  <div class="container-fluid">
    <h2><i class="fa-solid fa-clipboard-list fa-beat"></i> Cuestionario del módulo</h2>
    <div class="header-right">
      <div class="user-section">
        <button class="user-toggle" id="userToggle">
          <i class="fa-solid fa-user"></i> <?= htmlspecialchars($nombre) ?>
          <span style="font-size:0.8em;"><i class="fa-solid fa-caret-down"></i></span>
        </button>
        <div class="user-dropdown" id="userDropdown">
          <a href="../logout.php" class="user-dropdown-item logout">
            <i class="fa-solid fa-door-open"></i> Cerrar sesión
          </a>
        </div>
      </div>
      <a href="videos_usuario.php?carpeta=<?= $id_carpeta ?>" class="btn-volver">
        <i class="fa-solid fa-angle-left" style="color:#B197FC;"></i> Volver
      </a>
    </div>
  </div>
</div>

<div class="container" style="max-width:800px; margin-top:30px;">

  <!-- Cabecera del módulo -->
  <div class="card mb-4" style="border-radius:20px; border:2px solid #f0e4f3; overflow:hidden;">
    <div class="video-header" style="background:linear-gradient(135deg,#f5a3c7,#9b7cb8); padding:25px 30px; color:white;">
      <h3 style="margin:0; font-weight:700;">
        <i class="fa-solid fa-clipboard-list"></i> <?= htmlspecialchars($carpeta['nombre']) ?>
      </h3>
      <small style="opacity:0.9;">Cuestionario general del módulo</small>
    </div>

    <?php if (!empty($carpeta['instrucciones_cuestionario'])): ?>
    <div class="card-body" style="background:rgba(155,124,184,0.05); border-bottom:2px solid #f0e4f3;">
      <p style="margin:0; color:#555;">
        <i class="fa-solid fa-circle-info" style="color:#9b7cb8;"></i>
        <strong> Instrucciones:</strong> <?= nl2br(htmlspecialchars($carpeta['instrucciones_cuestionario'])) ?>
      </p>
    </div>
    <?php endif; ?>
  </div>

  <?php if (count($preguntas) === 0): ?>
    <div class="card text-center" style="border-radius:20px; padding:40px; border:2px solid #f0e4f3;">
      <div style="font-size:3rem; margin-bottom:15px;">📋</div>
      <h5 style="color:#9b7cb8;">Este módulo aún no tiene cuestionario</h5>
      <p class="text-muted">El administrador todavía no ha agregado preguntas.</p>
      <a href="videos_usuario.php?carpeta=<?= $id_carpeta ?>" class="btn btn-primary-custom mt-3">
        <i class="fa-solid fa-angle-left"></i> Volver al módulo
      </a>
    </div>

  <?php else: ?>

    <form id="formCuestionarioModulo" method="post" enctype="multipart/form-data">

      <?php $num = 1; foreach ($preguntas as $p): ?>
        <div class="pregunta-card" data-pregunta="<?= $num ?>" data-tipo="<?= $p['tipo_pregunta'] ?>"
             style="background:white; border:2px solid #f0e4f3; border-radius:18px; padding:25px; margin-bottom:20px; box-shadow:0 2px 10px rgba(155,124,184,0.08);">

          <h5 style="margin-bottom:15px;">
            <span style="background:#9b7cb8; color:white; padding:5px 13px; border-radius:50%; margin-right:10px; font-size:0.9rem;">
              <?= $num ?>
            </span>
            <?= htmlspecialchars($p['pregunta']) ?>
          </h5>

          <?php if ($p['tipo_pregunta'] === 'archivo'): ?>
            <?php if (!empty($p['instrucciones_archivo'])): ?>
              <div style="background:rgba(155,124,184,0.08); border-left:4px solid #9b7cb8; padding:12px 15px; border-radius:0 10px 10px 0; margin-bottom:15px;">
                <strong><i class="fa-solid fa-thumbtack"></i> Instrucciones:</strong><br>
                <?= nl2br(htmlspecialchars($p['instrucciones_archivo'])) ?>
              </div>
            <?php endif; ?>
            <label style="font-weight:600; color:#9b7cb8;">Sube tu archivo:</label>
            <input type="file"
                   name="archivo_modulo[<?= $p['id_cuestionario_modulo'] ?>]"
                   class="form-control file-input mt-2"
                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip"
                   required
                   style="border:2px dashed #9b7cb8; padding:10px; border-radius:12px;">
            <small class="text-muted d-block mt-1">Formatos: PDF, Word, Imágenes, ZIP. Máx. 10MB</small>

          <?php else: ?>
            <?php
            $opciones = json_decode($p['opciones_json'], true);
            if ($opciones && is_array($opciones)):
              foreach ($opciones as $letra => $texto):
                $inputId = "modq_{$p['id_cuestionario_modulo']}_$letra";
            ?>
              <div class="form-check" style="border:2px solid #f0e4f3; border-radius:12px; padding:12px 15px; margin-bottom:8px; cursor:pointer; transition:all 0.2s;">
                <input class="form-check-input" type="radio"
                       name="resp[<?= $p['id_cuestionario_modulo'] ?>]"
                       value="<?= htmlspecialchars($letra) ?>"
                       id="<?= $inputId ?>" required>
                <label class="form-check-label" for="<?= $inputId ?>" style="cursor:pointer; width:100%;">
                  <strong><?= htmlspecialchars($letra) ?>)</strong> <?= htmlspecialchars($texto) ?>
                </label>
              </div>
            <?php endforeach; endif; ?>
          <?php endif; ?>

        </div>
      <?php $num++; endforeach; ?>

      <div class="text-center mt-4 mb-5">
        <p style="color:#888; margin-bottom:15px;">Asegúrate de responder todas las preguntas antes de enviar.</p>
        <button type="submit" class="btn btn-enviar"
                style="background:linear-gradient(135deg,#f5a3c7,#9b7cb8); color:white; border:none; border-radius:25px; padding:14px 50px; font-size:1.1rem; font-weight:700; box-shadow:0 4px 15px rgba(155,124,184,0.4);">
          <i class="fa-solid fa-paper-plane"></i> Enviar respuestas
        </button>
      </div>

    </form>

  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Resaltar opción seleccionada ──
document.querySelectorAll('.form-check').forEach(fc => {
  const radio = fc.querySelector('input[type="radio"]');
  if (!radio) return;

  fc.addEventListener('click', function(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'LABEL') return;
    radio.checked = true;
    radio.dispatchEvent(new Event('change', { bubbles: true }));
  });

  radio.addEventListener('change', function() {
    if (this.checked) {
      const cont = fc.closest('.pregunta-card');
      if (cont) cont.querySelectorAll('.form-check').forEach(x => x.classList.remove('selected'));
      fc.classList.add('selected');
    }
  });
});

// ── Validación y confirmación antes de enviar ──
const form = document.getElementById('formCuestionarioModulo');
let enviando = false;

if (form) {
  form.addEventListener('submit', function(e) {
    if (enviando) return true;
    e.preventDefault();

    const sinResponder = [];
    document.querySelectorAll('.pregunta-card').forEach((card, i) => {
      const tipo = card.getAttribute('data-tipo');
      if (tipo === 'archivo') {
        const file = card.querySelector('input[type="file"]');
        if (!file || !file.files || file.files.length === 0) sinResponder.push(i + 1);
      } else {
        if (!card.querySelector('input[type="radio"]:checked')) sinResponder.push(i + 1);
      }
    });

    if (sinResponder.length > 0) {
      Swal.fire({
        title: 'Preguntas sin responder',
        html: `Te falta responder ${sinResponder.length} pregunta${sinResponder.length > 1 ? 's' : ''}.<br><br>Pregunta${sinResponder.length > 1 ? 's' : ''}: <strong>${sinResponder.join(', ')}</strong>`,
        icon: 'warning',
        confirmButtonText: 'Entendido'
      });
      const primera = document.querySelector(`.pregunta-card[data-pregunta="${sinResponder[0]}"]`);
      if (primera) {
        primera.scrollIntoView({ behavior: 'smooth', block: 'center' });
        primera.style.border = '3px solid #ff6b6b';
        setTimeout(() => primera.style.border = '2px solid #f0e4f3', 2000);
      }
      return false;
    }

    Swal.fire({
      title: '¿Enviar respuestas?',
      text: 'Una vez enviadas no podrás modificarlas.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, enviar',
      cancelButtonText: 'Revisar de nuevo',
      confirmButtonColor: '#9b7cb8'
    }).then(result => {
      if (result.isConfirmed) {
        Swal.fire({
          title: 'Enviando...',
          text: 'Por favor espera',
          allowOutsideClick: false,
          allowEscapeKey: false,
          showConfirmButton: false,
          didOpen: () => Swal.showLoading()
        });
        enviando = true;
        form.submit();
      }
    });
  });
}

// ── Mostrar resultado si viene de envío ──
<?php if (isset($_SESSION['resultado_modulo'])): ?>
  const resultado = <?= json_encode($_SESSION['resultado_modulo']) ?>;

  if (resultado.tipo === 'archivo' || resultado.tipo === 'mixto') {
    const html = resultado.tipo === 'archivo'
      ? `<div style="text-align:center; padding:20px;">
           <div style="font-size:4rem; margin:20px 0;"><i class="fa-solid fa-clipboard-check"></i></div>
           <h3 style="color:#9b7cb8;">Respuestas enviadas</h3>
           <p style="color:#666;">Un administrador revisará tus archivos y asignará tu calificación.</p>
         </div>`
      : `<div style="text-align:center; padding:20px;">
           <div style="font-size:4rem; color:#9b7cb8; font-weight:bold;">${resultado.porcentaje}%</div>
           <p style="color:#666; margin-top:10px;">Respondiste correctamente ${resultado.correctas} de ${resultado.total} preguntas de opción múltiple.</p>
           <p style="color:#9b7cb8;"><i class="fa-solid fa-file"></i> Tus archivos fueron enviados y serán revisados.</p>
         </div>`;

    Swal.fire({ title: '¡Cuestionario enviado!', html, icon: 'success', confirmButtonText: 'Continuar', confirmButtonColor: '#9b7cb8' });

  } else {
    const aprobado = resultado.porcentaje >= 70;
    const color = aprobado ? '#28a745' : '#ff6b6b';

    Swal.fire({
      title: aprobado ? '¡Felicidades!' : 'Resultado del cuestionario',
      html: `
        <div style="text-align:center; padding:20px;">
          <h3 style="color:#9b7cb8;">Cuestionario del módulo</h3>
          <div style="font-size:4rem; color:${color}; font-weight:bold; margin:20px 0;">${resultado.porcentaje}%</div>
          <div style="background:${aprobado ? 'rgba(40,167,69,0.1)' : 'rgba(255,107,107,0.1)'}; border-radius:10px; border-left:4px solid ${color}; padding:15px; margin-top:15px;">
            <strong style="color:${color}; font-size:1.2rem;">${aprobado ? '¡APROBADO!' : 'NO APROBADO'}</strong>
            <p style="margin-top:8px; color:#666;">${aprobado ? 'Superaste el 70% mínimo requerido' : 'Necesitas al menos 70% para aprobar'}</p>
          </div>
        </div>`,
      icon: aprobado ? 'success' : 'warning',
      confirmButtonText: 'Continuar',
      confirmButtonColor: '#9b7cb8'
    });
  }
  <?php unset($_SESSION['resultado_modulo']); ?>
<?php endif; ?>

// ── Menú de usuario ──
const userToggle = document.getElementById('userToggle');
const userDropdown = document.getElementById('userDropdown');
if (userToggle && userDropdown) {
  userToggle.addEventListener('click', e => { e.stopPropagation(); userDropdown.classList.toggle('show'); });
  document.addEventListener('click', e => {
    if (!userToggle.contains(e.target) && !userDropdown.contains(e.target)) userDropdown.classList.remove('show');
  });
}
</script>
</body>
</html>