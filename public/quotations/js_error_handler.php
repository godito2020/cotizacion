<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Monitor de Errores JavaScript</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .error-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .success-box {
            background: #d4edda;
            border: 1px solid #28a745;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
        }
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>Monitor de Errores JavaScript - create.php</h1>

    <iframe src="create.php" width="100%" height="400px" id="testFrame"></iframe>

    <h2>Errores Capturados:</h2>
    <div id="errorLog"></div>

    <script>
        const errorLog = document.getElementById('errorLog');
        let errorCount = 0;

        // Capturar errores de la ventana principal
        window.addEventListener('error', function(e) {
            logError('Window Error', e.message, e.filename, e.lineno, e.colno);
        });

        // Capturar errores del iframe
        document.getElementById('testFrame').addEventListener('load', function() {
            try {
                const iframeWindow = this.contentWindow;

                iframeWindow.addEventListener('error', function(e) {
                    logError('IFrame Error', e.message, e.filename, e.lineno, e.colno, e.error);
                });

                // Override console.error para capturar errores de consola
                const originalError = iframeWindow.console.error;
                iframeWindow.console.error = function(...args) {
                    logError('Console Error', args.join(' '), '', 0, 0);
                    originalError.apply(iframeWindow.console, args);
                };

                addLog('success', 'Monitoreo activo - esperando errores...');

            } catch (e) {
                addLog('error', 'No se puede acceder al iframe (posible CORS): ' + e.message);
            }
        });

        function logError(type, message, filename, lineno, colno, errorObj) {
            errorCount++;
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-box';
            errorDiv.innerHTML = `
                <h3>Error #${errorCount}: ${type}</h3>
                <p><strong>Mensaje:</strong> ${message}</p>
                ${filename ? `<p><strong>Archivo:</strong> ${filename}</p>` : ''}
                ${lineno ? `<p><strong>Línea:</strong> ${lineno}${colno ? `:${colno}` : ''}</p>` : ''}
                ${errorObj && errorObj.stack ? `<pre>${errorObj.stack}</pre>` : ''}
                <p><small>${new Date().toLocaleTimeString()}</small></p>
            `;
            errorLog.insertBefore(errorDiv, errorLog.firstChild);

            // Guardar en el servidor
            saveErrorToLog({
                type,
                message,
                filename,
                lineno,
                colno,
                stack: errorObj ? errorObj.stack : null,
                timestamp: new Date().toISOString()
            });
        }

        function addLog(type, message) {
            const logDiv = document.createElement('div');
            logDiv.className = type === 'success' ? 'success-box' : 'error-box';
            logDiv.innerHTML = `
                <p>${message}</p>
                <p><small>${new Date().toLocaleTimeString()}</small></p>
            `;
            errorLog.appendChild(logDiv);
        }

        function saveErrorToLog(errorData) {
            // Guardar el error en el log del servidor
            fetch('save_js_error.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(errorData)
            }).catch(e => console.log('No se pudo guardar el error en el servidor', e));
        }
    </script>
</body>
</html>
