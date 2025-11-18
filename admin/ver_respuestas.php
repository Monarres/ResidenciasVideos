<?php
session_start();
require_once("../conexion.php");

// Seguridad: solo accesible por administradores
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Procesar calificación de archivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calificar'])) {
    $id_respuesta = $_POST['id_respuesta_archivo'];
    $calificacion = $_POST['calificacion'];
    $comentario = $_POST['comentario'] ?? '';
    
    $sql = "UPDATE respuestas_archivo SET 
            calificacion = ?, 
            comentario_calificacion = ?,
            fecha_calificacion = NOW(),
            calificado_por = ?
            WHERE id_respuesta_archivo = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$calificacion, $comentario, $_SESSION['id_usuario'], $id_respuesta]);
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
    exit;
}

// Obtener áreas
$areas = $pdo->query("SELECT id_carpeta, nombre FROM carpetas WHERE id_padre IS NULL ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// Obtener filtros
$id_area = $_GET['id_area'] ?? null;
$id_usuario = $_GET['id_usuario'] ?? null;

// Obtener usuarios según área seleccionada
$usuarios = [];
if ($id_area) {
    $sql = "SELECT DISTINCT u.id_usuario, u.nombre 
            FROM usuarios u
            WHERE u.id_carpeta = ?
            ORDER BY u.nombre ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_area]);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener respuestas organizadas por módulo
$respuestas_por_modulo = [];
$datos_graficas = [];
if ($id_usuario) {
    // RESPUESTAS DE CUESTIONARIO (opción múltiple - tipo_pregunta = 'incisos')
    $sql = "SELECT 
                v.titulo AS video,
                v.id_video,
                c.pregunta,
                c.id_cuestionario,
                c.tipo_pregunta,
                ru.respuesta,
                c.respuesta_correcta,
                cp.nombre AS modulo,
                cp.id_carpeta AS id_modulo,
                'cuestionario' AS tipo_respuesta,
                NULL AS archivo,
                NULL AS calificacion,
                NULL AS id_respuesta_archivo,
                NULL AS comentario_calificacion,
                ru.fecha_creacion AS orden_fecha
            FROM respuestas_usuario ru
            JOIN cuestionarios c ON ru.id_cuestionario = c.id_cuestionario
            JOIN videos v ON c.id_video = v.id_video
            JOIN carpetas cp ON v.id_carpeta = cp.id_carpeta
            WHERE ru.id_usuario = ? AND c.tipo_pregunta = 'incisos'";
    
    // RESPUESTAS DE ARCHIVO (tipo_pregunta = 'archivo')
    $sql .= " UNION ALL
            SELECT 
                v.titulo AS video,
                v.id_video,
                c.pregunta,
                c.id_cuestionario,
                c.tipo_pregunta,
                NULL AS respuesta,
                NULL AS respuesta_correcta,
                cp.nombre AS modulo,
                cp.id_carpeta AS id_modulo,
                'archivo' AS tipo_respuesta,
                ra.ruta_archivo AS archivo,
                ra.calificacion,
                ra.id_respuesta_archivo,
                ra.comentario_calificacion,
                ra.fecha_subida AS orden_fecha
            FROM respuestas_archivo ra
            JOIN cuestionarios c ON ra.id_cuestionario = c.id_cuestionario
            JOIN videos v ON c.id_video = v.id_video
            JOIN carpetas cp ON v.id_carpeta = cp.id_carpeta
            WHERE ra.id_usuario = ? AND c.tipo_pregunta = 'archivo'";
    
    $sql .= " ORDER BY id_modulo, id_video, orden_fecha";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_usuario, $id_usuario]);
    $todas_respuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organizar por módulo y preparar datos para gráficas
    foreach ($todas_respuestas as $resp) {
        $modulo = $resp['modulo'];
        if (!isset($respuestas_por_modulo[$modulo])) {
            $respuestas_por_modulo[$modulo] = [];
            $datos_graficas[$modulo] = [
                'correctas' => 0,
                'incorrectas' => 0,
                'pendientes' => 0
            ];
        }
        $respuestas_por_modulo[$modulo][] = $resp;
        
        // Preparar datos para la gráfica
        if ($resp['tipo_respuesta'] === 'cuestionario') {
            $es_correcto = strtoupper($resp['respuesta'] ?? '') === strtoupper($resp['respuesta_correcta'] ?? '');
            if ($es_correcto) {
                $datos_graficas[$modulo]['correctas']++;
            } else {
                $datos_graficas[$modulo]['incorrectas']++;
            }
        } else {
            // Respuesta de archivo
            if ($resp['calificacion'] === null) {
                $datos_graficas[$modulo]['pendientes']++;
            } elseif ($resp['calificacion'] >= 7) {
                $datos_graficas[$modulo]['correctas']++;
            } else {
                $datos_graficas[$modulo]['incorrectas']++;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Respuestas de Usuarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
  padding-bottom: 40px;
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
  max-width: 1400px;
  padding: 20px 15px;
}
.card {
  border: none;
  border-radius: 15px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
  background: white;
  margin-bottom: 20px;
}
.form-control, .form-select {
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
.module-section {
  margin-bottom: 40px;
}
.module-header {
  background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
  color: white;
  padding: 15px 20px;
  border-radius: 15px;
  margin-bottom: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.module-header h4 {
  margin: 0;
  font-weight: 600;
  font-size: 1.3rem;
}
.stats-badge {
  background: white;
  color: #9b7cb8;
  padding: 8px 15px;
  border-radius: 20px;
  font-weight: 600;
  font-size: 0.9rem;
}
.module-content {
  display: flex;
  gap: 20px;
  align-items: flex-start;
}
.chart-container {
  background: #fff;
  padding: 20px;
  border-radius: 10px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.08);
  flex: 0 0 350px;
}
.chart-container h5 {
  font-size: 16px;
  margin-bottom: 15px;
  color: #9b7cb8;
  font-weight: 600;
}
.chart-wrapper {
  position: relative;
  height: 300px;
}
.table-container {
  flex: 1;
  min-width: 0;
}
.table {
  border-radius: 15px;
  overflow: hidden;
  margin-bottom: 0;
}
.table thead {
  background: linear-gradient(135deg, rgba(245, 163, 199, 0.3), rgba(155, 124, 184, 0.3));
  color: #9b7cb8;
}
.table thead th {
  border: none;
  padding: 15px;
  font-weight: 600;
}
.table tbody td {
  padding: 12px 15px;
  vertical-align: middle;
}
.table tbody tr {
  transition: 0.3s;
}
.table tbody tr:hover {
  background: rgba(155, 124, 184, 0.05);
}
.correct {
  background: #d4edda;
  color: #155724;
  font-weight: 600;
  padding: 5px 10px;
  border-radius: 10px;
  font-size: 1.2rem;
}
.incorrect {
  background: #f8d7da;
  color: #721c24;
  font-weight: 600;
  padding: 5px 10px;
  border-radius: 10px;
  font-size: 1.2rem;
}
.pending {
  background: #fff3cd;
  color: #856404;
  font-weight: 600;
  padding: 5px 10px;
  border-radius: 10px;
  font-size: 1.2rem;
}
.filter-card {
  background: linear-gradient(135deg, rgba(245, 163, 199, 0.1), rgba(155, 124, 184, 0.1));
  border: 2px solid #9b7cb8;
}
.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: #9b7cb8;
}
.empty-state .icon {
  font-size: 80px;
  opacity: 0.3;
}
.file-response-row {
  background: rgba(255, 243, 205, 0.2);
}
.btn-calificar {
  background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
  border: none;
  color: white;
  font-weight: 500;
  border-radius: 15px;
  padding: 5px 15px;
  font-size: 0.85rem;
  transition: 0.3s;
}
.btn-calificar:hover {
  transform: scale(1.05);
  box-shadow: 0 3px 10px rgba(155, 124, 184, 0.3);
}
.btn-ver-archivo {
  background: #17a2b8;
  border: none;
  color: white;
  font-weight: 500;
  border-radius: 15px;
  padding: 5px 15px;
  font-size: 0.85rem;
  transition: 0.3s;
  text-decoration: none;
  display: inline-block;
}
.btn-ver-archivo:hover {
  background: #138496;
  color: white;
  transform: scale(1.05);
}
.calificacion-display {
  font-weight: 700;
  font-size: 1.1rem;
  padding: 5px 12px;
  border-radius: 10px;
  display: inline-block;
}
.calificacion-aprobado {
  background: #d4edda;
  color: #155724;
}
.calificacion-reprobado {
  background: #f8d7da;
  color: #721c24;
}
.modal-content {
  border-radius: 20px;
  border: none;
}
.modal-header {
  background: linear-gradient(135deg, #f5a3c7, #9b7cb8);
  color: white;
  border-radius: 20px 20px 0 0;
}
.btn-close {
  filter: brightness(0) invert(1);
}
.comentario-box {
  background: #f8f9fa;
  border-left: 4px solid #9b7cb8;
  padding: 10px;
  border-radius: 5px;
  margin-top: 5px;
  font-size: 0.85rem;
}
@media (max-width: 1200px) {
  .module-content {
    flex-direction: column;
  }
  .chart-container {
    flex: 1;
    width: 100%;
  }
}
@media (max-width: 768px) {
  body {
    padding-top: 90px;
  }
  .top-header {
    margin: 10px;
    border-radius: 15px;
  }
  .top-header h2 {
    font-size: 1.2rem;
  }
  .btn-logout {
    padding: 6px 15px;
    font-size: 0.9rem;
  }
  .chart-wrapper {
    height: 250px;
  }
  .table {
    font-size: 0.85rem;
  }
  .module-header {
    flex-direction: column;
    gap: 10px;
    text-align: center;
  }
}
</style>
</head>
<body>
<div class="top-header">
  <div class="container-fluid">
    <h2>📊 Respuestas de Usuarios</h2>
    <div class="user-info">
      <a href="dashboard.php" class="btn-logout">⬅ Volver</a>
    </div>
  </div>
</div>
<div class="container">
  <!-- Filtros -->
  <div class="card filter-card p-4 mb-4">
    <h5 class="mb-3" style="color: #9b7cb8; font-weight: 600;">🔍 Filtrar Resultados</h5>
    <form method="get" id="filterForm">
      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label fw-bold">1️⃣ Seleccionar Área:</label>
          <select name="id_area" id="areaSelect" class="form-select" required>
            <option value="">-- Seleccionar área --</option>
            <?php foreach ($areas as $area): ?>
              <option value="<?= $area['id_carpeta'] ?>" <?= $id_area == $area['id_carpeta'] ? 'selected' : '' ?>>
                📁 <?= htmlspecialchars($area['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-5">
          <label class="form-label fw-bold">2️⃣ Seleccionar Usuario:</label>
          <select name="id_usuario" id="usuarioSelect" class="form-select" <?= !$id_area ? 'disabled' : '' ?> required>
            <option value="">-- Seleccionar usuario --</option>
            <?php foreach ($usuarios as $user): ?>
              <option value="<?= $user['id_usuario'] ?>" <?= $id_usuario == $user['id_usuario'] ? 'selected' : '' ?>>
                👤 <?= htmlspecialchars($user['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="submit" class="btn btn-primary w-100">Ver Respuestas</button>
        </div>
      </div>
    </form>
  </div>
  <!-- Mostrar respuestas por módulo -->
  <?php if ($id_usuario): ?>
    <?php if (!empty($respuestas_por_modulo)): ?>
      <?php 
      $modulo_index = 0;
      foreach ($respuestas_por_modulo as $modulo => $respuestas): 
        $total = count($respuestas);
        $correctas = 0;
        $pendientes = 0;
        
        foreach ($respuestas as $r) {
          if ($r['tipo_respuesta'] === 'cuestionario') {
            if (strtoupper($r['respuesta'] ?? '') === strtoupper($r['respuesta_correcta'] ?? '')) {
              $correctas++;
            }
          } else {
            if ($r['calificacion'] === null) {
              $pendientes++;
            } elseif ($r['calificacion'] >= 7) {
              $correctas++;
            }
          }
        }
        
        $evaluables = $total - $pendientes;
        $porcentaje = $evaluables > 0 ? round(($correctas / $evaluables) * 100) : 0;
        $modulo_index++;
      ?>
        <div class="module-section">
          <div class="module-header">
            <h4>📚 <?= htmlspecialchars($modulo) ?></h4>
            <div class="stats-badge">
              ✅ <?= $correctas ?>/<?= $evaluables ?> correctas (<?= $porcentaje ?>%)
              <?php if ($pendientes > 0): ?>
                | ⏳ <?= $pendientes ?> pendientes
              <?php endif; ?>
            </div>
          </div>
          
          <!-- Contenedor con gráfica y tabla lado a lado -->
          <div class="module-content">
            <!-- Gráfica del módulo -->
            <div class="chart-container">
              <h5>📊 Resultados por Pregunta</h5>
              <div class="chart-wrapper">
                <canvas id="chart<?= $modulo_index ?>"></canvas>
              </div>
            </div>
            
            <!-- Tabla detallada -->
            <div class="table-container">
              <div class="card p-0">
                <div class="table-responsive">
                  <table class="table table-hover mb-0">
                    <thead>
                      <tr>
                        <th style="width: 18%;">Video</th>
                        <th style="width: 32%;">Pregunta</th>
                        <th style="width: 12%;">Tipo</th>
                        <th style="width: 20%;">Respuesta/Archivo</th>
                        <th style="width: 18%; text-align: center;">Estado/Acción</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($respuestas as $r): ?>
                        <?php if ($r['tipo_respuesta'] === 'cuestionario'): ?>
                          <?php $es_correcto = strtoupper($r['respuesta'] ?? '') === strtoupper($r['respuesta_correcta'] ?? ''); ?>
                          <tr>
                            <td><strong>🎬 <?= htmlspecialchars($r['video']) ?></strong></td>
                            <td><?= htmlspecialchars($r['pregunta']) ?></td>
                            <td><span class="badge bg-primary">Cuestionario</span></td>
                            <td>
                              <span class="badge bg-secondary"><?= strtoupper($r['respuesta']) ?></span>
                              <br><small class="text-muted">Correcta: <?= strtoupper($r['respuesta_correcta'] ?? '') ?></small>
                            </td>
                            <td style="text-align: center;">
                              <span class="<?= $es_correcto ? 'correct' : 'incorrect' ?>">
                                <?= $es_correcto ? '✅' : '❌' ?>
                              </span>
                            </td>
                          </tr>
                        <?php else: ?>
                          <tr class="file-response-row">
                            <td><strong>🎬 <?= htmlspecialchars($r['video']) ?></strong></td>
                            <td>
                              <?= htmlspecialchars($r['pregunta']) ?>
                              <?php if ($r['comentario_calificacion']): ?>
                                <div class="comentario-box">
                                  <strong>💬 Comentario:</strong> <?= htmlspecialchars($r['comentario_calificacion']) ?>
                                </div>
                              <?php endif; ?>
                            </td>
                            <td><span class="badge bg-warning text-dark">📎 Archivo</span></td>
                            <td>
                              <a href="<?= htmlspecialchars($r['archivo']) ?>" target="_blank" class="btn-ver-archivo">
                                📥 Ver archivo
                              </a>
                              <?php if ($r['calificacion'] !== null): ?>
                                <br><small class="text-muted mt-1 d-block">
                                  Calificación: <strong><?= $r['calificacion'] ?>/10</strong>
                                </small>
                              <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                              <?php if ($r['calificacion'] === null): ?>
                                <span class="pending">⏳ Pendiente</span>
                                <br>
                                <button class="btn btn-calificar mt-2" onclick="abrirModalCalificacion(<?= $r['id_respuesta_archivo'] ?>, '<?= htmlspecialchars($r['pregunta'], ENT_QUOTES) ?>')">
                                  Calificar
                                </button>
                              <?php else: ?>
                                <?php 
                                $aprobado = $r['calificacion'] >= 7;
                                $clase = $aprobado ? 'calificacion-aprobado' : 'calificacion-reprobado';
                                $icono = $aprobado ? '✅' : '❌';
                                ?>
                                <span class="calificacion-display <?= $clase ?>">
                                  <?= $icono ?> <?= $r['calificacion'] ?>/10
                                </span>
                                <br>
                                <button class="btn btn-calificar mt-2" onclick="abrirModalCalificacion(<?= $r['id_respuesta_archivo'] ?>, '<?= htmlspecialchars($r['pregunta'], ENT_QUOTES) ?>', <?= $r['calificacion'] ?>, '<?= htmlspecialchars($r['comentario_calificacion'] ?? '', ENT_QUOTES) ?>')">
                                  Recalificar
                                </button>
                              <?php endif; ?>
                            </td>
                          </tr>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <!-- Resumen general -->
      <?php
        $total_general = 0;
        $correctas_general = 0;
        $pendientes_general = 0;
        
        foreach ($respuestas_por_modulo as $respuestas) {
          foreach ($respuestas as $r) {
            $total_general++;
            if ($r['tipo_respuesta'] === 'cuestionario') {
              if (strtoupper($r['respuesta'] ?? '') === strtoupper($r['respuesta_correcta'] ?? '')) {
                $correctas_general++;
              }
            } else {
              if ($r['calificacion'] === null) {
                $pendientes_general++;
              } elseif ($r['calificacion'] >= 7) {
                $correctas_general++;
              }
            }
          }
        }
        
        $evaluables_general = $total_general - $pendientes_general;
        $porcentaje_general = $evaluables_general > 0 ? round(($correctas_general / $evaluables_general) * 100) : 0;
      ?>
      <div class="card p-4" style="background: linear-gradient(135deg, rgba(245, 163, 199, 0.2), rgba(155, 124, 184, 0.2));">
        <h5 style="color: #9b7cb8; font-weight: 600; margin-bottom: 20px; text-align: center;">📈 Resumen General</h5>
        <div class="row text-center">
          <div class="col-md-3">
            <h3 style="color: #9b7cb8; font-weight: 700;"><?= $total_general ?></h3>
            <p class="mb-0 fw-bold">Total de Respuestas</p>
          </div>
          <div class="col-md-3">
            <h3 style="color: #28a745; font-weight: 700;"><?= $correctas_general ?></h3>
            <p class="mb-0 fw-bold">Respuestas Correctas</p>
          </div>
          <div class="col-md-3">
            <h3 style="color: #ffc107; font-weight: 700;"><?= $pendientes_general ?></h3>
            <p class="mb-0 fw-bold">Pendientes de Calificar</p>
          </div>
          <div class="col-md-3">
            <h3 style="color: #9b7cb8; font-weight: 700;"><?= $porcentaje_general ?>%</h3>
            <p class="mb-0 fw-bold">Porcentaje de Aciertos</p>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <div class="icon">📝</div>
        <h4>No hay respuestas registradas</h4>
        <p class="text-muted">Este usuario aún no ha respondido ningún cuestionario</p>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- Modal para calificar -->
<div class="modal fade" id="modalCalificar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">📝 Calificar Respuesta</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="calificar" value="1">
          <input type="hidden" name="id_respuesta_archivo" id="id_respuesta_archivo">
          
          <div class="mb-3">
            <label class="form-label fw-bold">Pregunta:</label>
            <p id="pregunta_texto" class="text-muted"></p>
          </div>
          
          <div class="mb-3">
            <label for="calificacion" class="form-label fw-bold">Calificación (0-10)</label>
            <input type="number" class="form-control" id="calificacion" name="calificacion" 
                   min="0" max="10" step="0.5" required>
          </div>
          
          <div class="mb-3">
            <label for="comentario" class="form-label fw-bold">Comentario (opcional)</label>
            <textarea class="form-control" id="comentario" name="comentario" 
                      rows="3" placeholder="Agregar retroalimentación..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">💾 Guardar Calificación</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Habilitar selector de usuario cuando se selecciona área
document.getElementById('areaSelect').addEventListener('change', function() {
  const usuarioSelect = document.getElementById('usuarioSelect');
  if (this.value) {
    window.location.href = `?id_area=${this.value}`;
  } else {
    usuarioSelect.disabled = true;
    usuarioSelect.innerHTML = '<option value="">-- Seleccionar usuario --</option>';
  }
});

// Función para abrir modal de calificación
function abrirModalCalificacion(idRespuesta, pregunta, calificacionActual = null, comentarioActual = '') {
  document.getElementById('id_respuesta_archivo').value = idRespuesta;
  document.getElementById('pregunta_texto').textContent = pregunta;
  
  if (calificacionActual !== null) {
    document.getElementById('calificacion').value = calificacionActual;
  } else {
    document.getElementById('calificacion').value = '';
  }
  
  document.getElementById('comentario').value = comentarioActual || '';
  
  const modal = new bootstrap.Modal(document.getElementById('modalCalificar'));
  modal.show();
}

// Crear gráficas para cada módulo
<?php if (!empty($datos_graficas)): ?>
  <?php 
  $chart_index = 0;
  foreach ($datos_graficas as $modulo => $datos): 
    $chart_index++;
    $total_datos = $datos['correctas'] + $datos['incorrectas'] + $datos['pendientes'];
  ?>
  (function() {
    const ctx = document.getElementById('chart<?= $chart_index ?>');
    if (ctx) {
      <?php if ($total_datos > 0): ?>
      new Chart(ctx, {
        type: 'pie',
        data: {
          labels: ['Correctas', 'Incorrectas', 'Pendientes'],
          datasets: [{
            data: [
              <?= $datos['correctas'] ?>,
              <?= $datos['incorrectas'] ?>,
              <?= $datos['pendientes'] ?>
            ],
            backgroundColor: [
              'rgba(40, 167, 69, 0.8)',
              'rgba(220, 53, 69, 0.8)',
              'rgba(255, 193, 7, 0.8)'
            ],
            borderColor: '#fff',
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          plugins: {
            legend: { 
              position: 'bottom',
              labels: {
                padding: 15,
                font: { size: 12 },
                filter: function(item, chart) {
                  // Ocultar elementos con valor 0
                  const data = chart.datasets[0].data;
                  const index = chart.labels.indexOf(item.text);
                  return data[index] > 0;
                }
              }
            },
            title: {
              display: true,
              text: 'Resultados del módulo',
              font: { size: 14 }
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  const label = context.label || '';
                  const value = context.parsed || 0;
                  const total = context.dataset.data.reduce((a, b) => a + b, 0);
                  const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                  return `${label}: ${value} (${percentage}%)`;
                }
              }
            }
          }
        }
      });
      <?php else: ?>
      // Mostrar mensaje cuando no hay datos
      const container = ctx.parentElement;
      container.innerHTML = '<div style="text-align: center; padding: 50px; color: #9b7cb8;"><p>No hay datos para mostrar</p></div>';
      <?php endif; ?>
    }
  })();
  <?php endforeach; ?>
<?php endif; ?>
</script>
</body>
</html>