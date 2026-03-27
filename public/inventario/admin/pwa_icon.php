<?php
/**
 * Configuración del Icono PWA para Inventario
 */

require_once __DIR__ . '/../../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

if (!Permissions::canManageInventorySessions($auth)) {
    $_SESSION['error_message'] = 'No tienes permisos para esta sección';
    header('Location: ' . BASE_URL . '/inventario/admin/index.php');
    exit;
}

$companyId = $auth->getCompanyId();
$uploadDir = __DIR__ . '/../../../uploads/pwa_icons';
$iconPath = $uploadDir . '/inventory_icon_' . $companyId . '.png';
$iconUrl = file_exists($iconPath) ? BASE_URL . '/uploads/pwa_icons/inventory_icon_' . $companyId . '.png' : null;

$message = '';
$messageType = '';

// Procesar subida de archivo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['pwa_icon']) && $_FILES['pwa_icon']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['pwa_icon'];

        // Validar tipo de archivo
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            $message = 'Tipo de archivo no permitido. Use PNG, JPG o WebP.';
            $messageType = 'danger';
        } else {
            // Crear directorio si no existe
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Procesar imagen - redimensionar a 512x512
            $sourceImage = null;
            switch ($mimeType) {
                case 'image/png':
                    $sourceImage = imagecreatefrompng($file['tmp_name']);
                    break;
                case 'image/jpeg':
                case 'image/jpg':
                    $sourceImage = imagecreatefromjpeg($file['tmp_name']);
                    break;
                case 'image/webp':
                    $sourceImage = imagecreatefromwebp($file['tmp_name']);
                    break;
            }

            if ($sourceImage) {
                // Crear imagen 512x512
                $newImage = imagecreatetruecolor(512, 512);

                // Preservar transparencia para PNG
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
                imagefilledrectangle($newImage, 0, 0, 512, 512, $transparent);
                imagealphablending($newImage, true);

                // Redimensionar manteniendo proporción
                $srcW = imagesx($sourceImage);
                $srcH = imagesy($sourceImage);
                $ratio = min(512 / $srcW, 512 / $srcH);
                $newW = (int)($srcW * $ratio);
                $newH = (int)($srcH * $ratio);
                $offsetX = (int)((512 - $newW) / 2);
                $offsetY = (int)((512 - $newH) / 2);

                imagecopyresampled($newImage, $sourceImage, $offsetX, $offsetY, 0, 0, $newW, $newH, $srcW, $srcH);

                // Guardar como PNG
                if (imagepng($newImage, $iconPath, 9)) {
                    $message = 'Icono actualizado correctamente. El cambio se reflejará en la próxima instalación del PWA.';
                    $messageType = 'success';
                    $iconUrl = BASE_URL . '/uploads/pwa_icons/inventory_icon_' . $companyId . '.png?v=' . time();
                } else {
                    $message = 'Error al guardar el icono.';
                    $messageType = 'danger';
                }

                imagedestroy($sourceImage);
                imagedestroy($newImage);
            } else {
                $message = 'Error al procesar la imagen.';
                $messageType = 'danger';
            }
        }
    } elseif (isset($_POST['delete_icon'])) {
        if (file_exists($iconPath)) {
            unlink($iconPath);
            $iconUrl = null;
            $message = 'Icono eliminado. Se usará el icono por defecto.';
            $messageType = 'success';
        }
    } else {
        $message = 'Por favor seleccione un archivo.';
        $messageType = 'warning';
    }
}

$pageTitle = 'Icono PWA';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .icon-preview {
            width: 192px;
            height: 192px;
            border: 3px dashed #dee2e6;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            margin: 0 auto;
            overflow: hidden;
        }
        .icon-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .icon-preview.has-icon {
            border-style: solid;
            border-color: #0d6efd;
        }
        .upload-zone {
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        .upload-zone:hover, .upload-zone.dragover {
            border-color: #0d6efd;
            background: #f0f7ff;
        }
        .phone-mockup {
            width: 280px;
            height: 500px;
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            border-radius: 40px;
            padding: 20px;
            margin: 0 auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        .phone-screen {
            background: #0f0f23;
            border-radius: 24px;
            height: 100%;
            padding: 40px 20px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            align-content: start;
        }
        .app-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: #333;
            margin: 0 auto;
        }
        .app-icon.highlight {
            background: white;
            box-shadow: 0 4px 15px rgba(255,255,255,0.3);
        }
        .app-icon img {
            width: 100%;
            height: 100%;
            border-radius: 12px;
            object-fit: cover;
        }
        .app-label {
            font-size: 10px;
            color: white;
            text-align: center;
            margin-top: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg bg-dark navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= BASE_URL ?>/inventario/admin/index.php">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
            <span class="navbar-text text-white">
                <i class="fas fa-mobile-alt"></i> <?= htmlspecialchars($pageTitle) ?>
            </span>
        </div>
    </nav>

    <main class="container py-4">
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Formulario de subida -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-upload"></i> Subir Icono</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="upload-zone" id="uploadZone" onclick="document.getElementById('pwa_icon').click()">
                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                <p class="mb-2">Arrastra una imagen aquí o haz clic para seleccionar</p>
                                <small class="text-muted">PNG, JPG o WebP - Se redimensionará a 512x512</small>
                                <input type="file" name="pwa_icon" id="pwa_icon" accept="image/png,image/jpeg,image/webp"
                                       style="display: none" onchange="previewImage(this)">
                            </div>

                            <div class="mt-4">
                                <h6>Vista previa:</h6>
                                <div class="icon-preview <?= $iconUrl ? 'has-icon' : '' ?>" id="iconPreview">
                                    <?php if ($iconUrl): ?>
                                        <img src="<?= htmlspecialchars($iconUrl) ?>" alt="Icono actual" id="previewImg">
                                    <?php else: ?>
                                        <div class="text-muted text-center">
                                            <i class="fas fa-image fa-3x mb-2"></i>
                                            <p class="small mb-0">Sin icono</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Guardar Icono
                                </button>
                                <?php if ($iconUrl): ?>
                                    <button type="submit" name="delete_icon" value="1" class="btn btn-outline-danger"
                                            onclick="return confirm('¿Eliminar el icono personalizado?')">
                                        <i class="fas fa-trash"></i> Eliminar Icono
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="alert alert-info mt-4">
                    <h6><i class="fas fa-info-circle"></i> Recomendaciones</h6>
                    <ul class="mb-0 small">
                        <li>Use una imagen cuadrada de al menos 512x512 píxeles</li>
                        <li>Preferiblemente PNG con fondo transparente</li>
                        <li>El icono debe ser reconocible en tamaño pequeño</li>
                        <li>Después de cambiar el icono, desinstale y reinstale la PWA</li>
                    </ul>
                </div>
            </div>

            <!-- Preview del teléfono -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-mobile-alt"></i> Así se verá en el teléfono</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="phone-mockup">
                            <div class="phone-screen">
                                <!-- Apps ficticias -->
                                <div><div class="app-icon" style="background:#34C759"></div><div class="app-label">Mensajes</div></div>
                                <div><div class="app-icon" style="background:#FF9500"></div><div class="app-label">Notas</div></div>
                                <div><div class="app-icon" style="background:#5856D6"></div><div class="app-label">Ajustes</div></div>
                                <div><div class="app-icon" style="background:#FF2D55"></div><div class="app-label">Música</div></div>

                                <div><div class="app-icon" style="background:#007AFF"></div><div class="app-label">Safari</div></div>
                                <div><div class="app-icon" style="background:#5AC8FA"></div><div class="app-label">Fotos</div></div>
                                <div><div class="app-icon" style="background:#FF3B30"></div><div class="app-label">Mail</div></div>
                                <div>
                                    <div class="app-icon highlight" id="phoneAppIcon">
                                        <?php if ($iconUrl): ?>
                                            <img src="<?= htmlspecialchars($iconUrl) ?>" alt="Inventario">
                                        <?php else: ?>
                                            <div style="background:#0d6efd;width:100%;height:100%;border-radius:12px;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold">INV</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="app-label" style="color:#0d6efd;font-weight:bold">Inventario</div>
                                </div>
                            </div>
                        </div>
                        <p class="text-muted mt-3 small">
                            El icono aparecerá en la pantalla de inicio cuando instales la app
                        </p>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-download"></i> Instrucciones de Instalación</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <h6><i class="fab fa-android text-success"></i> Android</h6>
                                <ol class="small">
                                    <li>Abre Chrome</li>
                                    <li>Ve a la página de inventario</li>
                                    <li>Toca el menú (⋮)</li>
                                    <li>"Añadir a pantalla de inicio"</li>
                                </ol>
                            </div>
                            <div class="col-6">
                                <h6><i class="fab fa-apple"></i> iPhone</h6>
                                <ol class="small">
                                    <li>Abre Safari</li>
                                    <li>Ve a la página de inventario</li>
                                    <li>Toca Compartir</li>
                                    <li>"Añadir a inicio"</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('pwa_icon');

        // Drag and drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadZone.addEventListener(eventName, () => uploadZone.classList.add('dragover'));
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadZone.addEventListener(eventName, () => uploadZone.classList.remove('dragover'));
        });

        uploadZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length) {
                fileInput.files = files;
                previewImage(fileInput);
            }
        });

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('iconPreview');
                    const phoneIcon = document.getElementById('phoneAppIcon');

                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview" id="previewImg">`;
                    preview.classList.add('has-icon');

                    phoneIcon.innerHTML = `<img src="${e.target.result}" alt="Inventario">`;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
