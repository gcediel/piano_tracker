<?php
// Script para generar hash correcto de la contraseña por defecto

$password = 'piano2026';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "=== GENERADOR DE HASH DE CONTRASEÑA ===\n\n";
echo "Contraseña: piano2026\n";
echo "Hash generado: $hash\n\n";

// Verificar que funciona
if (password_verify($password, $hash)) {
    echo "✓ Verificación exitosa: El hash es válido\n\n";
} else {
    echo "✗ ERROR: El hash NO es válido\n\n";
}

echo "Copia este hash en tu base de datos:\n\n";
echo "UPDATE configuracion SET valor = '$hash' WHERE clave = 'password_hash';\n\n";
?>
