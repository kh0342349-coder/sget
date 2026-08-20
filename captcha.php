<?php
session_start();

// Generar dos números aleatorios
$num1 = rand(1, 9);
$num2 = rand(1, 9);

// Guardar la suma correcta en la sesión
$_SESSION['captcha_answer'] = $num1 + $num2;

// Crear lienzo de imagen
$image = imagecreatetruecolor(110, 38);
$bg_color = imagecolorallocate($image, 241, 245, 249); // Fondo gris claro (Tailwind slate-100)
$text_color = imagecolorallocate($image, 15, 23, 42);   // Texto oscuro (Tailwind slate-900)
$line_color = imagecolorallocate($image, 148, 163, 184); // Líneas de ruido (Tailwind slate-400)

imagefilledrectangle($image, 0, 0, 110, 38, $bg_color);

// Agregar líneas de ruido suave
for ($i = 0; $i < 3; $i++) {
    imageline($image, rand(0, 110), rand(0, 38), rand(0, 110), rand(0, 38), $line_color);
}

// Escribir la operación matemática
$text = "$num1 + $num2 = ?";
imagestring($image, 5, 20, 10, $text, $text_color);

// Salida de la imagen GIF
header('Content-Type: image/gif');
imagegif($image);
imagedestroy($image);
?>