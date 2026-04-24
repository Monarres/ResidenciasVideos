<?php
session_start();
require_once("../conexion.php");

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    if (isset($_GET['action']) || isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
    } else {
        header('Location: ../login.php');
    }
    exit;
}

// ============================================================
// ACCIÓN: OBTENER preguntas (AJAX GET)
// ============================================================
if (isset($_GET['action']) && $_GET['action'] === 'obtener') {
    header('Content-Type: application/json');

    $id_carpeta = intval($_GET['id'] ?? 0);

    $stmtC = $pdo->prepare("SELECT instrucciones_cuestionario FROM carpetas WHERE id_carpeta = ?");
    $stmtC->execute([$id_carpeta]);
    $carpeta = $stmtC->fetch(PDO::FETCH_ASSOC);

    if (!$carpeta) {
        echo json_encode(['success' => false, 'message' => 'Módulo no encontrado']);
        exit;
    }

    $stmtQ = $pdo->prepare("SELECT * FROM cuestionario_modulo WHERE id_carpeta = ? ORDER BY orden ASC");
    $stmtQ->execute([$id_carpeta]);
    $preguntas = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'instrucciones' => $carpeta['instrucciones_cuestionario'] ?? '',
        'preguntas' => $preguntas
    ]);
    exit;
}

// ============================================================
// ACCIÓN: GUARDAR preguntas (AJAX POST)
// ============================================================
if (isset($_POST['action']) && $_POST['action'] === 'guardar') {
    header('Content-Type: application/json');

    try {
        if (!isset($_POST['id_carpeta'])) throw new Exception('Datos incompletos');

        $id_carpeta = intval($_POST['id_carpeta']);
        $instrucciones = trim($_POST['instrucciones'] ?? '');
        $preguntas = json_decode($_POST['preguntas'], true);

        if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('Error al procesar las preguntas');

        $pdo->beginTransaction();

        // Actualizar instrucciones en la carpeta
        $stmt = $pdo->prepare("UPDATE carpetas SET instrucciones_cuestionario = ? WHERE id_carpeta = ?");
        $stmt->execute([$instrucciones, $id_carpeta]);

        // IDs actuales en BD
        $stmtIds = $pdo->prepare("SELECT id_cuestionario_modulo FROM cuestionario_modulo WHERE id_carpeta = ?");
        $stmtIds->execute([$id_carpeta]);
        $idsEnBD = array_column($stmtIds->fetchAll(PDO::FETCH_ASSOC), 'id_cuestionario_modulo');

        // IDs que llegan del formulario
        $idsFormulario = array_filter(array_map(fn($p) => $p['id'] ?? null, $preguntas));

        // Eliminar los que ya no están
        foreach ($idsEnBD as $idBD) {
            if (!in_array($idBD, $idsFormulario)) {
                $pdo->prepare("DELETE FROM cuestionario_modulo WHERE id_cuestionario_modulo = ?")->execute([$idBD]);
            }
        }

        // Insertar o actualizar
        foreach ($preguntas as $orden => $p) {
            if (!isset($p['tipo'])) throw new Exception('Tipo de pregunta no especificado');

            if ($p['tipo'] === 'incisos') {
                if (empty($p['pregunta'])) throw new Exception('Una pregunta no puede estar vacía');
                if (!isset($p['opciones']) || count($p['opciones']) < 2) throw new Exception('Cada pregunta debe tener al menos 2 opciones');
                if (empty($p['respuesta_correcta'])) throw new Exception('Debes indicar la respuesta correcta');
                if (!isset($p['opciones'][$p['respuesta_correcta']])) throw new Exception('La respuesta correcta no corresponde a ninguna opción');

                $opciones_json = json_encode($p['opciones'], JSON_UNESCAPED_UNICODE);

                if (!empty($p['id'])) {
                    $stmt = $pdo->prepare("UPDATE cuestionario_modulo SET tipo_pregunta='incisos', pregunta=?, opciones_json=?, respuesta_correcta=?, instrucciones_archivo=NULL, orden=? WHERE id_cuestionario_modulo=? AND id_carpeta=?");
                    $stmt->execute([$p['pregunta'], $opciones_json, $p['respuesta_correcta'], $orden, $p['id'], $id_carpeta]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO cuestionario_modulo (id_carpeta, tipo_pregunta, pregunta, opciones_json, respuesta_correcta, orden) VALUES (?, 'incisos', ?, ?, ?, ?)");
                    $stmt->execute([$id_carpeta, $p['pregunta'], $opciones_json, $p['respuesta_correcta'], $orden]);
                }

            } elseif ($p['tipo'] === 'archivo') {
                if (empty($p['instrucciones'])) throw new Exception('La pregunta de tipo archivo debe tener instrucciones');

                if (!empty($p['id'])) {
                    $stmt = $pdo->prepare("UPDATE cuestionario_modulo SET tipo_pregunta='archivo', pregunta=?, instrucciones_archivo=?, opciones_json=NULL, respuesta_correcta=NULL, orden=? WHERE id_cuestionario_modulo=? AND id_carpeta=?");
                    $stmt->execute([$p['pregunta'], $p['instrucciones'], $orden, $p['id'], $id_carpeta]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO cuestionario_modulo (id_carpeta, tipo_pregunta, pregunta, instrucciones_archivo, orden) VALUES (?, 'archivo', ?, ?, ?)");
                    $stmt->execute([$id_carpeta, $p['pregunta'], $p['instrucciones'], $orden]);
                }
            } else {
                throw new Exception('Tipo de pregunta no válido');
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Cuestionario del módulo guardado correctamente']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================================================
// VISTA PRINCIPAL (HTML)
// ============================================================
if (!isset($_GET['id'])) {
    header('Location: ../index.php');
    exit;
}

$id_carpeta = intval($_GET['id']);

$stmt = $pdo->prepare("SELECT * FROM carpetas WHERE id_carpeta = ?");
$stmt->execute([$id_carpeta]);
$carpeta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$carpeta) {
    header('Location: ../index.php');
    exit;
}

$stmtQ = $pdo->prepare("SELECT * FROM cuestionario_modulo WHERE id_carpeta = ? ORDER BY orden ASC");
$stmtQ->execute([$id_carpeta]);
$preguntas = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuestionario del módulo — <?= htmlspecialchars($carpeta['nombre']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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

    /* Header Principal */
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
      gap: 15px;
      flex-wrap: wrap;
    }

    .top-header h2 {
      color: white;
      font-weight: 600;
      margin: 0;
      font-size: 1.5rem;
      flex: 1 1 auto;
      text-align: center;
      min-width: 200px;
    }

    .btn-volver {
      background: white;
      border: none;
      color: #9b7cb8;
      font-weight: 500;
      border-radius: 25px;
      padding: 8px 20px;
      transition: 0.3s;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      white-space: nowrap;
    }

    .btn-volver:hover {
      background: #f8f9fa;
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.2);
      color: #9b7cb8;
    }

    /* Contenedor Principal */
    .container {
      max-width: 900px;
      padding: 20px 15px;
    }

    .card-modulo {
      background: white;
      border-radius: 15px;
      padding: 30px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      border: none;
    }

    .section-title {
      color: #9b7cb8;
      font-weight: 700;
      font-size: 1.1rem;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .pregunta-card {
      background: linear-gradient(135deg, rgba(245, 163, 199, 0.1), rgba(155, 124, 184, 0.1));
      border: 1px solid #f0e4f3;
      border-radius: 12px;
      padding: 15px;
      margin-bottom: 12px;
      transition: all 0.2s ease;
      word-wrap: break-word;
    }

    .pregunta-card:hover {
      transform: translateX(5px);
      box-shadow: 0 2px 8px rgba(155, 124, 184, 0.2);
    }

    .pregunta-card small { color: #9b7cb8; font-weight: 600; }

    .btn-add {
      background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
      color: white;
      border: none;
      border-radius: 25px;
      padding: 12px 30px;
      font-weight: 600;
      cursor: pointer;
      font-size: 1rem;
      box-shadow: 0 4px 15px rgba(155, 124, 184, 0.3);
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .btn-add:hover {
      background: linear-gradient(135deg, #9b7cb8, #f5a3c7);
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(155, 124, 184, 0.4);
    }

    .badge-preguntas {
      background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
      color: white;
      border-radius: 20px;
      padding: 8px 15px;
      font-size: 0.85rem;
      font-weight: 500;
    }

    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #9b7cb8;
    }

    .instrucciones-box {
      background: linear-gradient(135deg, rgba(245,163,199,0.1), rgba(155,124,184,0.1));
      border: 2px solid #9b7cb8;
      border-radius: 15px;
      padding: 18px;
      margin-bottom: 25px;
    }

    .instrucciones-box p { margin: 0; color: #555; font-size: 0.95rem; }
    .instrucciones-box .label { color: #9b7cb8; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px; }

    /* Alert */
    .alert { border-radius: 15px; border: none; }

    /* SweetAlert2 Styles */
    .swal2-popup {
      border-radius: 20px !important;
      font-family: 'Poppins', sans-serif !important;
      padding: 30px !important;
      background: #ffffff !important;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2) !important;
      max-width: 900px !important;
    }

    .swal2-title {
      color: #9b7cb8 !important;
      font-weight: 700 !important;
      font-size: 1.8rem !important;
      margin-bottom: 20px !important;
    }

    .swal2-html-container {
      color: #666 !important;
      font-size: 1rem !important;
      font-weight: 500 !important;
      max-height: 500px !important;
      overflow-y: auto !important;
    }

    .swal2-confirm {
      background: linear-gradient(135deg, #f5a3c7, #9b7cb8) !important;
      border: none !important;
      border-radius: 25px !important;
      padding: 12px 35px !important;
      font-weight: 600 !important;
      font-size: 1rem !important;
      box-shadow: 0 4px 15px rgba(155, 124, 184, 0.4) !important;
      transition: all 0.3s ease !important;
    }

    .swal2-confirm:hover {
      background: linear-gradient(135deg, #9b7cb8, #f5a3c7) !important;
      transform: translateY(-2px) !important;
      box-shadow: 0 6px 20px rgba(155, 124, 184, 0.5) !important;
    }

    .swal2-cancel {
      background: white !important;
      border: 2px solid #9b7cb8 !important;
      border-radius: 25px !important;
      padding: 12px 35px !important;
      font-weight: 600 !important;
      font-size: 1rem !important;
      color: #9b7cb8 !important;
      transition: all 0.3s ease !important;
    }

    .swal2-cancel:hover {
      background: #f8f9fa !important;
      transform: translateY(-2px) !important;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1) !important;
    }

    .swal2-input,
    .swal2-textarea {
      border-radius: 15px !important;
      border: 2px solid #ddd !important;
      padding: 12px 20px !important;
      font-family: 'Poppins', sans-serif !important;
      font-size: 1rem !important;
      transition: all 0.3s ease !important;
    }

    .swal2-textarea { min-height: 100px !important; }

    .swal2-input:focus,
    .swal2-textarea:focus {
      border-color: #9b7cb8 !important;
      box-shadow: 0 0 0 4px rgba(155, 124, 184, 0.15) !important;
      outline: none !important;
    }

    /* Media Queries */
    @media (max-width: 768px) {
      body { padding-top: 140px; }

      .top-header {
        margin: 10px;
        padding: 15px 0;
        border-radius: 15px;
      }

      .top-header .container-fluid {
        flex-direction: column;
        padding: 0 15px;
        gap: 10px;
      }

      .top-header h2 { font-size: 1.2rem; text-align: center; width: 100%; }

      .btn-volver { padding: 6px 15px; font-size: 0.9rem; }

      .swal2-popup { padding: 20px !important; max-width: 95% !important; }
      .swal2-title { font-size: 1.4rem !important; }
      .swal2-html-container { font-size: 0.9rem !important; max-height: 400px !important; }
    }

    @media (max-width: 576px) {
      body { padding-top: 160px; }

      .top-header { margin: 8px; padding: 12px 0; }
      .top-header .container-fluid { padding: 0 10px; }
      .top-header h2 { font-size: 1rem; }

      .btn-volver { width: 100%; justify-content: center; padding: 8px 15px; font-size: 0.85rem; }

      .container { padding: 10px 8px; }

      .swal2-popup { padding: 15px !important; width: 95% !important; }
      .swal2-title { font-size: 1.2rem !important; }
      .swal2-confirm, .swal2-cancel { padding: 10px 25px !important; font-size: 0.9rem !important; }
      .swal2-input, .swal2-textarea { font-size: 0.9rem !important; padding: 10px 15px !important; }
    }
    </style>
</head>
<body>

<div class="top-header">
  <div class="container-fluid">
    <h2><i class="fa-solid fa-clipboard-list fa-beat"></i> Cuestionario del módulo — <?= htmlspecialchars($carpeta['nombre']) ?></h2>
    <div class="header-right">
      <a href="carpeta.php?id=<?= $id_carpeta ?>" class="btn-volver">
        <i class="fa-solid fa-angle-left" style="color: #B197FC;"></i>
        <span>Volver</span>
      </a>
    </div>
  </div>
</div>

<div class="container">
    <div class="card-modulo">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 style="color:#3d2b5e; margin:0;"><?= htmlspecialchars($carpeta['nombre']) ?></h4>
                <small class="text-muted">Cuestionario general al finalizar el módulo</small>
            </div>
            <span class="badge-preguntas">
                <?= count($preguntas) ?> pregunta<?= count($preguntas) != 1 ? 's' : '' ?>
            </span>
        </div>

        <?php if (!empty($carpeta['instrucciones_cuestionario'])): ?>
        <div class="instrucciones-box">
            <div class="label"><i class="fa-solid fa-circle-info"></i> Instrucciones del cuestionario</div>
            <p><?= nl2br(htmlspecialchars($carpeta['instrucciones_cuestionario'])) ?></p>
        </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="section-title">
                <i class="fa-solid fa-list-check"></i> Preguntas
            </div>
            <button class="btn-add" id="btnEditarCuestionario">
                <i class="fas fa-edit"></i> Editar cuestionario
            </button>
        </div>

        <?php if (count($preguntas) > 0): ?>
            <div id="listadoPreguntas">
                <?php foreach ($preguntas as $i => $p): ?>
                    <div class="pregunta-card">
                        <small><?= $p['tipo_pregunta'] === 'archivo' ? '<i class="fa-solid fa-paperclip"></i> Archivo' : '<i class="fa-solid fa-question"></i> Pregunta ' . ($i + 1) ?></small>
                        <p class="mb-2 mt-1" style="font-size:0.95rem;"><strong><?= htmlspecialchars($p['pregunta']) ?></strong></p>
                        <?php if ($p['tipo_pregunta'] === 'incisos'): ?>
                            <?php
                            $opciones = json_decode($p['opciones_json'], true);
                            if ($opciones) {
                                foreach ($opciones as $letra => $texto) {
                                    echo "<small class='text-muted d-block'>{$letra}) " . htmlspecialchars($texto) . "</small>";
                                }
                            }
                            ?>
                            <small class="d-block mt-2" style="color:#28a745; font-weight:600;">
                                ✓ Respuesta: <?= htmlspecialchars($p['respuesta_correcta']) ?>
                            </small>
                        <?php else: ?>
                            <small class="d-block mt-2" style="color:#9b7cb8; font-weight:600;">Pregunta de tipo archivo</small>
                            <?php if (!empty($p['instrucciones_archivo'])): ?>
                                <small class="d-block text-muted mt-1">Instrucciones: <?= htmlspecialchars($p['instrucciones_archivo']) ?></small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size:3rem; margin-bottom:15px;">📋</div>
                <h5>Sin preguntas aún</h5>
                <p>Usa el botón "Editar cuestionario" para agregar preguntas al módulo</p>
            </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function reordenarOpciones(contenedorOpciones, selectRespuesta) {
    const letras = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const opcionesItems = contenedorOpciones.querySelectorAll('.opcion-item');
    const respuestaSeleccionada = selectRespuesta.value;
    const mapeoLetras = {};
    const nuevasOpciones = [];

    opcionesItems.forEach((item, index) => {
        const letraAntigua = item.getAttribute('data-letra');
        const letraNueva = letras[index];
        mapeoLetras[letraAntigua] = letraNueva;
        nuevasOpciones.push({ letra: letraNueva, texto: item.querySelector('.opcion-texto').value });
        item.setAttribute('data-letra', letraNueva);
        item.querySelector('.input-group-text').textContent = letraNueva;
        item.querySelector('.opcion-texto').placeholder = `Opción ${letraNueva}`;
    });

    selectRespuesta.innerHTML = '<option value="">Selecciona la respuesta correcta</option>';
    nuevasOpciones.forEach(op => {
        const option = document.createElement('option');
        option.value = op.letra;
        option.textContent = op.letra;
        selectRespuesta.appendChild(option);
    });

    if (respuestaSeleccionada && mapeoLetras[respuestaSeleccionada]) {
        selectRespuesta.value = mapeoLetras[respuestaSeleccionada];
    }
}

document.getElementById('btnEditarCuestionario').addEventListener('click', async () => {

    Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    const res = await fetch(`cuestionario_modulo.php?action=obtener&id=<?= $id_carpeta ?>`);
    const data = await res.json();

    if (!data.success) {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
        return;
    }

    const preguntasExistentes = data.preguntas || [];
    const instruccionesActuales = data.instrucciones || '';

    const { value: formValues } = await Swal.fire({
        title: '<i class="fas fa-clipboard-list"></i> Cuestionario del módulo',
        html: `
            <div style="text-align:left; max-height:70vh; overflow-y:auto; padding: 0 10px;">
                <div style="background: linear-gradient(135deg, rgba(245,163,199,0.1), rgba(155,124,184,0.1)); border: 2px solid #9b7cb8; border-radius: 15px; padding: 15px; margin-bottom: 20px;">
                    <label class="fw-bold" style="color: #9b7cb8;"><i class="fas fa-info-circle"></i> Instrucciones generales:</label>
                    <textarea id="instruccionesModulo" class="swal2-textarea" placeholder="Instrucciones para el alumno (opcional)..." style="width:100%; margin-left:-5px; margin-top:8px; min-height:90px; border:2px solid #ddd;">${escapeHtml(instruccionesActuales)}</textarea>
                </div>
                <div id="contenedorPreguntas" style="margin-top:10px;"></div>
                <button type="button" class="btn mt-3" id="btnAgregarPregunta" style="background: linear-gradient(135deg, #f5a3c7, #9b7cb8); color:white; border:none; border-radius:15px; padding:10px 20px; font-weight:600;">
                    <i class="fas fa-plus-circle"></i> Añadir pregunta
                </button>
            </div>
        `,
        width: '900px',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save"></i> Guardar cuestionario',
        cancelButtonText: 'Cancelar',
        didOpen: () => {
            const cont = document.getElementById('contenedorPreguntas');

            function agregarOpcion(contenedorOpciones, selectRespuesta, letra, texto = '') {
                const nuevaOpcion = document.createElement('div');
                nuevaOpcion.className = 'opcion-item mb-2';
                nuevaOpcion.setAttribute('data-letra', letra);
                nuevaOpcion.innerHTML = `
                    <div class="input-group">
                        <span class="input-group-text" style="background:#9b7cb8; color:white; border-radius:10px 0 0 10px; font-weight:600;">${letra}</span>
                        <input type="text" class="form-control opcion-texto" placeholder="Opción ${letra}" value="${escapeHtml(texto)}" style="border-radius:0;">
                        <button type="button" class="btn btn-danger btn-eliminar-opcion" style="border-radius:0 10px 10px 0;">Eliminar</button>
                    </div>
                `;
                contenedorOpciones.appendChild(nuevaOpcion);
                if (!selectRespuesta.querySelector(`option[value="${letra}"]`)) {
                    const option = document.createElement('option');
                    option.value = letra;
                    option.textContent = letra;
                    selectRespuesta.appendChild(option);
                }
                nuevaOpcion.querySelector('.btn-eliminar-opcion').addEventListener('click', function () {
                    nuevaOpcion.remove();
                    reordenarOpciones(contenedorOpciones, selectRespuesta);
                });
            }

            function crearPregunta(datosPregunta = null) {
                const esArchivo = datosPregunta?.tipo_pregunta === 'archivo';
                const etiqueta = esArchivo
                    ? '<i class="fa-solid fa-paperclip" style="color:#B197FC;"></i> Archivo'
                    : '<i class="fa-solid fa-question" style="color:#B197FC;"></i> Pregunta';

                const div = document.createElement('div');
                div.className = 'pregunta-item';
                div.style.marginTop = '15px';
                div.innerHTML = `
                    <div style="border:2px solid #f0e4f3; border-radius:15px; padding:15px; background:linear-gradient(135deg,rgba(245,163,199,0.05),rgba(155,124,184,0.05));">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="fw-bold pregunta-label" style="color:#9b7cb8;">${etiqueta}</label>
                            <button type="button" class="btn btn-sm btn-danger btn-eliminar-pregunta" style="border-radius:15px;">Eliminar</button>
                        </div>
                        <input type="hidden" class="pregunta-id" value="${datosPregunta?.id_cuestionario_modulo || ''}">
                        <div class="mt-2 mb-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm tipo-btn ${!esArchivo ? 'active' : ''}" data-tipo="incisos" style="border-radius:10px; margin-right:5px;">✓ Opción múltiple</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm tipo-btn ${esArchivo ? 'active' : ''}" data-tipo="archivo" style="border-radius:10px;">📎 Subir archivo</button>
                        </div>
                        <div class="pregunta-texto-container mt-2" style="display:${esArchivo ? 'none' : 'block'};">
                            <input type="text" class="form-control pregunta-input" placeholder="Escribe la pregunta" value="${escapeHtml(datosPregunta?.pregunta || '')}" style="border-radius:15px;">
                        </div>
                        <div class="opciones-dinamicas mt-3" style="display:${esArchivo ? 'none' : 'block'};">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="fw-bold" style="color:#666;">Opciones de respuesta:</small>
                                <button type="button" class="btn btn-sm btn-agregar-opcion" style="background:#9b7cb8; color:white; border-radius:10px; padding:4px 12px;">Agregar opción</button>
                            </div>
                            <div class="contenedor-opciones"></div>
                            <small class="d-block mt-3 fw-bold" style="color:#666;">Respuesta correcta:</small>
                            <select class="form-control mt-2 select-respuesta" style="border-radius:15px;">
                                <option value="">Selecciona la respuesta correcta</option>
                            </select>
                        </div>
                        <div class="archivo-container mt-2" style="display:${esArchivo ? 'block' : 'none'};">
                            <textarea class="form-control instrucciones-archivo" placeholder="Instrucciones para el usuario..." style="border-radius:15px; min-height:100px;">${escapeHtml(datosPregunta?.instrucciones_archivo || '')}</textarea>
                        </div>
                    </div>
                `;

                cont.appendChild(div);

                if (!esArchivo && datosPregunta?.opciones_json) {
                    const opciones = JSON.parse(datosPregunta.opciones_json);
                    const contenedorOpciones = div.querySelector('.contenedor-opciones');
                    const selectRespuesta = div.querySelector('.select-respuesta');
                    Object.entries(opciones).forEach(([letra, texto]) => agregarOpcion(contenedorOpciones, selectRespuesta, letra, texto));
                    if (datosPregunta.respuesta_correcta) selectRespuesta.value = datosPregunta.respuesta_correcta;
                }

                div.querySelectorAll('.tipo-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        div.querySelectorAll('.tipo-btn').forEach(x => x.classList.remove('active'));
                        btn.classList.add('active');
                        const tipo = btn.dataset.tipo;
                        const label = div.querySelector('.pregunta-label');
                        div.querySelector('.pregunta-texto-container').style.display = tipo === 'incisos' ? 'block' : 'none';
                        div.querySelector('.opciones-dinamicas').style.display = tipo === 'incisos' ? 'block' : 'none';
                        div.querySelector('.archivo-container').style.display = tipo === 'archivo' ? 'block' : 'none';
                        label.innerHTML = tipo === 'incisos'
                            ? '<i class="fa-solid fa-question" style="color:#B197FC;"></i> Pregunta'
                            : '<i class="fa-solid fa-paperclip" style="color:#B197FC;"></i> Archivo';
                    });
                });

                div.querySelector('.btn-eliminar-pregunta').addEventListener('click', () => div.remove());

                div.querySelector('.btn-agregar-opcion').addEventListener('click', () => {
                    const letras = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                    const contenedorOpciones = div.querySelector('.contenedor-opciones');
                    const selectRespuesta = div.querySelector('.select-respuesta');
                    const opcionesActuales = contenedorOpciones.querySelectorAll('.opcion-item');
                    if (opcionesActuales.length >= 26) return;
                    agregarOpcion(contenedorOpciones, selectRespuesta, letras[opcionesActuales.length], '');
                });
            }

            preguntasExistentes.forEach(p => crearPregunta(p));
            document.getElementById('btnAgregarPregunta').addEventListener('click', () => crearPregunta());
        },
        preConfirm: () => {
            const instrucciones = document.getElementById('instruccionesModulo').value.trim();
            const preguntas = [];
            let errorValidacion = null;

            document.querySelectorAll('.pregunta-item').forEach((div, i) => {
                if (errorValidacion) return;
                const id = div.querySelector('.pregunta-id').value;
                const tipo = div.querySelector('.tipo-btn.active')?.dataset.tipo;

                if (tipo === 'incisos') {
                    const pregunta = div.querySelector('.pregunta-input').value.trim();
                    if (!pregunta) { errorValidacion = `La pregunta ${i+1} no puede estar vacía`; return; }

                    const opciones = {};
                    div.querySelectorAll('.opcion-item').forEach(item => {
                        const letra = item.getAttribute('data-letra');
                        const texto = item.querySelector('.opcion-texto').value.trim();
                        if (!texto) { errorValidacion = `La opción ${letra} de la pregunta ${i+1} no puede estar vacía`; return; }
                        opciones[letra] = texto;
                    });

                    if (Object.keys(opciones).length < 2) { errorValidacion = `La pregunta ${i+1} debe tener al menos 2 opciones`; return; }
                    const resp = div.querySelector('.select-respuesta').value;
                    if (!resp) { errorValidacion = `Debes seleccionar la respuesta correcta de la pregunta ${i+1}`; return; }
                    if (!opciones[resp]) { errorValidacion = `La respuesta correcta no existe entre las opciones de la pregunta ${i+1}`; return; }

                    preguntas.push({ id, tipo, pregunta, opciones, respuesta_correcta: resp });

                } else if (tipo === 'archivo') {
                    const instruccionesArchivo = div.querySelector('.instrucciones-archivo').value.trim();
                    if (!instruccionesArchivo) { errorValidacion = `La pregunta ${i+1} de tipo archivo debe tener instrucciones`; return; }
                    preguntas.push({ id, tipo, pregunta: instruccionesArchivo, instrucciones: instruccionesArchivo });
                }
            });

            if (errorValidacion) { Swal.showValidationMessage(errorValidacion); return false; }
            return { instrucciones, preguntas };
        }
    });

    if (!formValues) return;

    Swal.fire({ title: 'Guardando...', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });

    const formData = new FormData();
    formData.append('action', 'guardar');
    formData.append('id_carpeta', <?= $id_carpeta ?>);
    formData.append('instrucciones', formValues.instrucciones);
    formData.append('preguntas', JSON.stringify(formValues.preguntas));

    try {
        const res = await fetch('cuestionario_modulo.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            await Swal.fire({ icon: 'success', title: '¡Guardado!', text: data.message, confirmButtonText: 'Aceptar' });
            location.reload();
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message });
        }
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Error de conexión', text: err.message });
    }
});
</script>
</body>
</html>