<?php
// Script para crear las tablas necesarias en la base de datos

// Conexión a la base de datos
$servername = "sql100.infinityfree.com";
$username = "if0_39926607";
$password = "Bltj8s30sxrlli";
$dbname = "if0_39926607_asistencia_db";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

echo "<h2>Creando tablas en la base de datos...</h2>";

// Tabla para archivos CTZ (documentos base)
$sql_ctz = "CREATE TABLE IF NOT EXISTS archivos_ctz (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL DEFAULT 'documento',
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(500) NOT NULL,
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql_ctz)) {
    echo "<p style='color: green;'>✓ Tabla 'archivos_ctz' creada exitosamente</p>";
} else {
    echo "<p style='color: red;'>✗ Error creando tabla 'archivos_ctz': " . $conn->error . "</p>";
}

// Tabla para archivos CTZ de las escuelas
$sql_ctz_escuelas = "CREATE TABLE IF NOT EXISTS archivos_ctz_escuelas (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    escuela_id VARCHAR(20) NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(500) NOT NULL,
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (escuela_id) REFERENCES usuarios(escuela_id)
)";

if ($conn->query($sql_ctz_escuelas)) {
    echo "<p style='color: green;'>✓ Tabla 'archivos_ctz_escuelas' creada exitosamente</p>";
} else {
    echo "<p style='color: red;'>✗ Error creando tabla 'archivos_ctz_escuelas': " . $conn->error . "</p>";
}

// Tabla para consolidados CTZ
$sql_consolidados_ctz = "CREATE TABLE IF NOT EXISTS consolidados_ctz (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(500) NOT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql_consolidados_ctz)) {
    echo "<p style='color: green;'>✓ Tabla 'consolidados_ctz' creada exitosamente</p>";
} else {
    echo "<p style='color: red;'>✗ Error creando tabla 'consolidados_ctz': " . $conn->error . "</p>";
}

// Verificar si la tabla de usuarios existe y tiene la estructura correcta
$sql_check_usuarios = "SHOW TABLES LIKE 'usuarios'";
$result = $conn->query($sql_check_usuarios);

if ($result->num_rows > 0) {
    echo "<p style='color: green;'>✓ Tabla 'usuarios' ya existe</p>";
    
    // Verificar si la columna escuela_id existe en usuarios
    $sql_check_escuela_id = "SHOW COLUMNS FROM usuarios LIKE 'escuela_id'";
    $result_escuela = $conn->query($sql_check_escuela_id);
    
    if ($result_escuela->num_rows == 0) {
        echo "<p style='color: orange;'>⚠ La tabla 'usuarios' no tiene la columna 'escuela_id'. Necesitas agregarla manualmente.</p>";
    } else {
        echo "<p style='color: green;'>✓ Columna 'escuela_id' existe en la tabla 'usuarios'</p>";
    }
} else {
    echo "<p style='color: red;'>✗ La tabla 'usuarios' no existe. Debes crearla primero.</p>";
}

// Verificar otras tablas necesarias
$tables_to_check = ['archivos', 'archivos_trimestrales', 'consolidados', 'consolidados_trimestrales', 'destinatarios'];

foreach ($tables_to_check as $table) {
    $sql_check = "SHOW TABLES LIKE '$table'";
    $result = $conn->query($sql_check);
    
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>✓ Tabla '$table' existe</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Tabla '$table' no existe</p>";
    }
}

echo "<h3>Proceso completado.</h3>";
echo "<p>Si hay errores, revisa los mensajes arriba y corrige manualmente en tu panel de control de MySQL.</p>";

$conn->close();
?>