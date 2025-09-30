<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('HTTP/1.0 403 Forbidden');
    die("Acceso denegado. Debe iniciar sesión para descargar archivos.");
}

if (!isset($_GET['file'])) {
    header('HTTP/1.0 400 Bad Request');
    die("Parámetro de archivo no especificado.");
}

$filepath = $_GET['file'];

// Validar que el archivo esté en un directorio permitido
$allowed_dirs = ['uploads/', 'uploads/trimestral/', 'uploads/ctz/', 'uploads/ctz/escuelas/', 'consolidated/', 'consolidated/trimestral/', 'consolidated/ctz/'];
$is_allowed = false;

foreach ($allowed_dirs as $dir) {
    if (strpos($filepath, $dir) === 0) {
        $is_allowed = true;
        break;
    }
}

if (!$is_allowed) {
    header('HTTP/1.0 403 Forbidden');
    die("Acceso al archivo no permitido.");
}

// Prevenir directory traversal
$realpath = realpath($filepath);
if ($realpath === false || strpos($realpath, realpath('.')) !== 0) {
    header('HTTP/1.0 403 Forbidden');
    die("Ruta de archivo inválida.");
}

if (!file_exists($realpath)) {
    header('HTTP/1.0 404 Not Found');
    die("Archivo no encontrado.");
}

// Validar que el usuario tenga permisos para descargar el archivo
$user_school_id = $_SESSION['user']['escuela_id'];
$user_rol = $_SESSION['user']['rol'];

// Si no es admin, verificar que el archivo pertenezca a su escuela o sea un archivo CTZ maestro
if ($user_rol !== 'admin') {
    $filename = basename($realpath);
    $file_dir = dirname($realpath);
    
    // Permitir archivos CTZ maestro (documentos para que las escuelas descarguen)
    $is_ctz_maestro = (strpos($filepath, 'uploads/ctz/') !== false && 
                       strpos($filename, 'ctz_documento_') !== false);
    
    // Permitir archivos de la escuela del usuario
    $is_user_school_file = (strpos($filename, $user_school_id) !== false);
    
    // Permitir archivos consolidados (estos son generados por el admin para todos)
    $is_consolidated = (strpos($filepath, 'consolidated/') !== false);
    
    if (!$is_ctz_maestro && !$is_user_school_file && !$is_consolidated) {
        header('HTTP/1.0 403 Forbidden');
        die("No tiene permisos para descargar este archivo.");
    }
}

// Configurar headers para descarga
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.basename($realpath).'"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($realpath));

// Limpiar buffer de salida y enviar archivo
if (ob_get_level()) {
    ob_end_clean();
}
readfile($realpath);
exit;
?>