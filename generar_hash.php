<?php
// Script para generar el hash de una contraseña. Uso: php generar_hash.php <contraseña>

$password = $argv[1] ?? null;
if (!$password) {
    fwrite(STDERR, "Uso: php generar_hash.php <contraseña>\n");
    exit(1);
}
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "=== GENERADOR DE HASH DE CONTRASEÑA ===\n\n";
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
