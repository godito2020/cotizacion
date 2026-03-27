<?php
/**
 * Script para generar iconos PNG para PWA
 * Ejecutar una vez: php generate_icons.php
 * Requiere GD library
 */

$sizes = [72, 96, 128, 144, 152, 192, 384, 512];
$iconDir = __DIR__;

// Colores del gradiente
$primaryColor = [13, 110, 253]; // #0d6efd
$secondaryColor = [10, 88, 202]; // #0a58ca

foreach ($sizes as $size) {
    $image = imagecreatetruecolor($size, $size);

    // Habilitar alpha blending
    imagealphablending($image, true);
    imagesavealpha($image, true);

    // Fondo transparente
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparent);

    // Color del fondo (azul)
    $bgColor = imagecolorallocate($image, $primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $white = imagecolorallocate($image, 255, 255, 255);
    $green = imagecolorallocate($image, 40, 167, 69);
    $lightBlue = imagecolorallocate($image, 173, 206, 254);

    // Dibujar círculo de fondo
    $center = $size / 2;
    $radius = ($size / 2) - 4;
    imagefilledellipse($image, $center, $center, $radius * 2, $radius * 2, $bgColor);

    // Dibujar documento (rectángulo blanco)
    $docWidth = $size * 0.45;
    $docHeight = $size * 0.55;
    $docX = ($size - $docWidth) / 2;
    $docY = ($size - $docHeight) / 2 - $size * 0.05;

    // Documento con esquina doblada
    $points = [
        $docX, $docY + $size * 0.08, // Top left (rounded)
        $docX + $docWidth * 0.7, $docY,  // Before corner
        $docX + $docWidth, $docY + $docHeight * 0.15, // After corner
        $docX + $docWidth, $docY + $docHeight, // Bottom right
        $docX, $docY + $docHeight, // Bottom left
    ];

    imagefilledrectangle($image,
        (int)$docX,
        (int)$docY,
        (int)($docX + $docWidth),
        (int)($docY + $docHeight),
        $white
    );

    // Líneas en el documento
    $lineY = $docY + $docHeight * 0.25;
    $lineHeight = max(2, $size * 0.03);
    for ($i = 0; $i < 4; $i++) {
        $lineWidth = ($i == 1 || $i == 3) ? $docWidth * 0.6 : $docWidth * 0.75;
        imagefilledrectangle($image,
            (int)($docX + $docWidth * 0.12),
            (int)$lineY,
            (int)($docX + $docWidth * 0.12 + $lineWidth),
            (int)($lineY + $lineHeight),
            $lightBlue
        );
        $lineY += $docHeight * 0.12;
    }

    // Símbolo de dólar
    $dollarSize = $size * 0.15;
    $dollarX = $docX + $docWidth * 0.65;
    $dollarY = $docY + $docHeight * 0.7;
    imagefilledellipse($image, (int)$dollarX, (int)$dollarY, (int)$dollarSize, (int)$dollarSize, $green);

    // Guardar imagen
    $filename = $iconDir . '/icon-' . $size . 'x' . $size . '.png';
    imagepng($image, $filename);
    imagedestroy($image);

    echo "Created: $filename\n";
}

// También crear favicon
$faviconSizes = [16, 32, 48];
foreach ($faviconSizes as $size) {
    $image = imagecreatetruecolor($size, $size);
    imagealphablending($image, true);
    imagesavealpha($image, true);

    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparent);

    $bgColor = imagecolorallocate($image, $primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $white = imagecolorallocate($image, 255, 255, 255);

    $center = $size / 2;
    $radius = ($size / 2) - 1;
    imagefilledellipse($image, $center, $center, $radius * 2, $radius * 2, $bgColor);

    // Simple document shape
    $docWidth = $size * 0.5;
    $docHeight = $size * 0.6;
    $docX = ($size - $docWidth) / 2;
    $docY = ($size - $docHeight) / 2;
    imagefilledrectangle($image, (int)$docX, (int)$docY, (int)($docX + $docWidth), (int)($docY + $docHeight), $white);

    $filename = $iconDir . '/favicon-' . $size . 'x' . $size . '.png';
    imagepng($image, $filename);
    imagedestroy($image);

    echo "Created: $filename\n";
}

echo "\nAll icons generated successfully!\n";
echo "You can also create better icons manually using the SVG template in icon.svg\n";
