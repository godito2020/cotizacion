<!DOCTYPE html>
<html>
<head>
    <title>Debug Create.php</title>
</head>
<body>
    <h2>Diagnóstico de create.php</h2>

    <?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../../logs/create_debug.log');

    echo "<h3>1. Verificando archivo create.php</h3>";
    $createFile = __DIR__ . '/create.php';

    if (file_exists($createFile)) {
        echo "✓ El archivo existe<br>";
        echo "Ruta: " . $createFile . "<br>";
        echo "Tamaño: " . filesize($createFile) . " bytes<br>";

        // Intentar compilar el PHP sin ejecutarlo
        exec("php -l \"$createFile\" 2>&1", $output, $returnCode);
        echo "<h3>2. Verificación de sintaxis PHP</h3>";
        echo "Código de retorno: " . $returnCode . "<br>";
        echo "Salida:<br><pre>" . implode("\n", $output) . "</pre>";

        // Buscar posibles problemas en la línea 1987
        echo "<h3>3. Contenido alrededor de línea 1987</h3>";
        $lines = file($createFile);
        if (isset($lines[1986])) {
            echo "<pre>";
            for ($i = 1980; $i <= 1995 && isset($lines[$i]); $i++) {
                $lineNum = $i + 1;
                echo "Línea $lineNum: " . htmlspecialchars($lines[$i]);
            }
            echo "</pre>";
        } else {
            echo "El archivo tiene menos de 1987 líneas<br>";
            echo "Total de líneas: " . count($lines) . "<br>";
        }

        // Buscar etiquetas <script> sin cerrar
        echo "<h3>4. Verificando etiquetas &lt;script&gt;</h3>";
        $content = file_get_contents($createFile);
        $openTags = substr_count($content, '<script');
        $closeTags = substr_count($content, '</script>');
        echo "Etiquetas &lt;script&gt; abiertas: $openTags<br>";
        echo "Etiquetas &lt;/script&gt; cerradas: $closeTags<br>";

        if ($openTags !== $closeTags) {
            echo "<strong style='color: red;'>⚠️ PROBLEMA: Número de etiquetas no coincide!</strong><br>";
        } else {
            echo "✓ Las etiquetas coinciden<br>";
        }

    } else {
        echo "✗ El archivo no existe<br>";
    }

    echo "<h3>5. Log de errores</h3>";
    $logFile = __DIR__ . '/../../logs/create_debug.log';
    echo "Los errores se están guardando en: $logFile<br>";

    if (file_exists($logFile)) {
        echo "<h4>Contenido del log:</h4>";
        echo "<pre>" . htmlspecialchars(file_get_contents($logFile)) . "</pre>";
    } else {
        echo "El archivo de log aún no existe (se creará cuando haya un error)<br>";
    }

    echo "<hr>";
    echo '<a href="create.php">Ir a create.php</a>';
    ?>
</body>
</html>
