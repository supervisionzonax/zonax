<?php
// Evitar cache para desarrollo
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Establecer zona horaria
date_default_timezone_set('America/Hermosillo');

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

// Iniciar sesión
session_start();

// Incluir PhpSpreadsheet
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ============================
// FUNCIONES DEL SISTEMA
// ============================

/**
 * Determinar turno actual según la hora
 */
function getTurnoActual() {
    $hora_actual = date('H:i');
    return ($hora_actual < '13:40') ? 'matutino' : 'vespertino';
}

/**
 * Validar si está dentro del horario permitido para subir archivos
 */
function validarHorarioSubida($turno) {
    $hora_actual = date('H:i');
    
    if ($turno == 'matutino' && $hora_actual > '13:40') {
        return false;
    }
    
    return true;
}

/**
 * Enviar correo con PHPMailer
 */
function enviarCorreo($destinatarios, $asunto, $cuerpo, $archivoAdjunto = null) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'zonaxsuper@gmail.com';
        $mail->Password   = 'nrku xhbf rssf saul';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        
        // Remitente
        $mail->setFrom('zonaxsuper@gmail.com', 'Sistema de Asistencia Zona X');
        
        // Destinatarios
        foreach ($destinatarios as $destinatario) {
            $mail->addAddress($destinatario);
        }
        
        // Contenido
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpo;
        $mail->AltBody = strip_tags($cuerpo);
        
        // Adjuntar archivo si se proporciona
        if ($archivoAdjunto && file_exists($archivoAdjunto)) {
            $mail->addAttachment($archivoAdjunto);
        }
        
        // Enviar correo
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error al enviar correo: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Enviar concentrado por correo - MODIFICADO PARA DIFERENTES TIPOS
 */
function enviarConcentradoPorCorreo($rutaArchivo, $tipo, $turno = '') {
    global $conn;
    
    // Obtener solo destinatarios ACTIVOS de la base de datos
    $destinatarios = array();
    $sql = "SELECT email FROM destinatarios WHERE activo = 1";
    $result = $conn->query($sql);
    
    while($row = $result->fetch_assoc()) {
        $destinatarios[] = $row['email'];
    }
    
    if (empty($destinatarios)) {
        return "No hay destinatarios activos configurados";
    }
    
    // Diferentes asuntos y cuerpos según el tipo
    if ($tipo == 'asistencia') {
        $asunto = "Concentrado de Asistencia - Turno " . ucfirst($turno) . " - " . date('d/m/Y');
        $cuerpo = "
            <h2>Concentrado de Asistencia Diaria</h2>
            <p>Se adjunta el concentrado del turno <strong>" . ucfirst($turno) . "</strong> 
            correspondiente a la fecha <strong>" . date('d/m/Y') . "</strong>.</p>
            <p>Este reporte contiene la asistencia del personal docente y administrativo 
            de las escuelas de la Zona X.</p>
            <p>Saludos,<br><strong>Sistema de Asistencia Zona X</strong></p>
        ";
    } elseif ($tipo == 'trimestral') {
        $asunto = "Reporte Trimestral de Asistencia - " . date('d/m/Y');
        $cuerpo = "
            <h2>Reporte Trimestral de Asistencia</h2>
            <p>Se adjunta el reporte trimestral consolidado de asistencia 
            correspondiente al período actual.</p>
            <p>Este documento contiene los resúmenes trimestrales de todas las escuelas 
            de la Zona X con estadísticas completas de asistencia.</p>
            <p>Saludos,<br><strong>Sistema de Asistencia Zona X</strong></p>
        ";
    } elseif ($tipo == 'ctz') {
        $asunto = "Concentrado de Documentos CTE - " . date('d/m/Y');
        $cuerpo = "
            <h2>Concentrado de Consejos Técnicos Escolares</h2>
            <p>Se adjunta el concentrado de documentos CTE correspondiente 
            a la fecha <strong>" . date('d/m/Y') . "</strong>.</p>
            <p>Este archivo contiene los documentos de todas las escuelas 
            relacionadas con los Consejos Técnicos Escolares.</p>
            <p>Saludos,<br><strong>Sistema de Asistencia Zona X</strong></p>
        ";
    } else {
        $asunto = "Documento del Sistema - " . date('d/m/Y');
        $cuerpo = "
            <h2>Documento del Sistema</h2>
            <p>Se adjunta documento generado por el sistema.</p>
            <p>Saludos,<br><strong>Sistema de Asistencia Zona X</strong></p>
        ";
    }
    
    if (enviarCorreo($destinatarios, $asunto, $cuerpo, $rutaArchivo)) {
        return "Realizado exitosamente.";
    } else {
        return "Error";
    }
}

/**
 * Eliminar consolidado después del envío - MODIFICADO PARA DIFERENTES TABLAS
 */
function deleteConsolidatedAfterSend($id, $filepath, $tipo = 'asistencia') {
    global $conn;
    
    // Eliminar archivo físico
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    
    // Eliminar registro de la base de datos según el tipo
    if ($tipo == 'asistencia') {
        $sql = "DELETE FROM consolidados WHERE id = $id";
    } elseif ($tipo == 'trimestral') {
        $sql = "DELETE FROM consolidados_trimestrales WHERE id = $id";
    } elseif ($tipo == 'ctz') {
        $sql = "DELETE FROM consolidados_ctz WHERE id = $id";
    }
    
    if (isset($sql)) {
        $conn->query($sql);
    }
}

/**
 * Obtener archivo CTZ maestro para todos los usuarios
 */
function getArchivoCTZMaestro($conn) {
    $archivo_ctz_maestro = null;
    try {
        // Verificar si la tabla existe primero
        $table_check = "SHOW TABLES LIKE 'archivos_ctz'";
        $result_check = $conn->query($table_check);
        
        if ($result_check->num_rows > 0) {
            $sql = "SELECT * FROM archivos_ctz WHERE tipo = 'documento' ORDER BY fecha_subida DESC LIMIT 1";
            $result = $conn->query($sql);
            
            if ($result && $result->num_rows > 0) {
                $archivo_ctz_maestro = $result->fetch_assoc();
                
                // VERIFICAR QUE EL ARCHIVO EXISTA FÍSICAMENTE
                if ($archivo_ctz_maestro && !file_exists($archivo_ctz_maestro['ruta_archivo'])) {
                    error_log("Archivo CTE no encontrado: " . $archivo_ctz_maestro['ruta_archivo']);
                    return null;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error al obtener archivo CTE maestro: " . $e->getMessage());
    }
    return $archivo_ctz_maestro;
}

/**
 * Consolidar archivos de asistencia diaria
 */
function consolidarArchivosAsistencia($turno) {
    global $conn;
    
    try {
        $hoy = date('Y-m-d');
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0); // Eliminar hoja por defecto
        
        // Obtener archivos del turno específico de hoy
        $sql = "SELECT a.*, u.nombre as escuela_nombre 
                FROM archivos a 
                LEFT JOIN usuarios u ON a.escuela_id = u.escuela_id 
                WHERE DATE(a.fecha_subida) = '$hoy' AND a.turno = '$turno' 
                ORDER BY a.escuela_id";
        $result = $conn->query($sql);
        
        if ($result->num_rows === 0) {
            return "No hay archivos para consolidar del turno $turno";
        }
        
        $hoja_index = 0;
        while($archivo = $result->fetch_assoc()) {
            if (!file_exists($archivo['ruta_archivo'])) {
                continue; // Saltar archivos que no existen físicamente
            }
            
            // Cargar archivo de cada escuela
            $archivo_spreadsheet = IOFactory::load($archivo['ruta_archivo']);
            $worksheet = $archivo_spreadsheet->getActiveSheet();
            
            // Crear nueva hoja en el consolidado
            $nueva_hoja = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, $archivo['escuela_nombre']);
            $spreadsheet->addSheet($nueva_hoja, $hoja_index);
            
            // Copiar contenido
            foreach ($worksheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                
                $fila_destino = $row->getRowIndex();
                foreach ($cellIterator as $cell) {
                    $columna_destino = $cell->getColumn();
                    $nueva_hoja->setCellValue($columna_destino . $fila_destino, $cell->getCalculatedValue());
                }
            }
            
            // Agregar encabezado con nombre de escuela
            $nueva_hoja->insertNewRowBefore(1, 2);
            $nueva_hoja->setCellValue('A1', 'ESCUELA: ' . $archivo['escuela_nombre']);
            $nueva_hoja->setCellValue('A2', 'TURNO: ' . strtoupper($turno) . ' - FECHA: ' . date('d/m/Y'));
            
            $hoja_index++;
        }
        
        if ($hoja_index === 0) {
            return "No se pudieron cargar archivos válidos para consolidar";
        }
        
        // Guardar archivo consolidado
        $target_dir = "uploads/consolidados/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $nombre_archivo = "CONSOLIDADO_" . strtoupper($turno) . "_" . date('Y-m-d') . ".xlsx";
        $ruta_archivo = $target_dir . $nombre_archivo;
        
        $writer = new Xlsx($spreadsheet);
        $writer->save($ruta_archivo);
        
        // Guardar en base de datos
        $sql_consolidado = "INSERT INTO consolidados (turno, nombre_archivo, ruta_archivo, fecha_creacion) 
                           VALUES ('$turno', '$nombre_archivo', '$ruta_archivo', NOW())";
        
        if ($conn->query($sql_consolidado)) {
            return "success:" . $nombre_archivo;
        } else {
            return "Error al guardar en base de datos: " . $conn->error;
        }
        
    } catch (Exception $e) {
        return "Error al consolidar archivos: " . $e->getMessage();
    }
}

/**
 * Consolidar archivos trimestrales
 */
function consolidarArchivosTrimestrales() {
    global $conn;
    
    try {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0); // Eliminar hoja por defecto
        
        // Obtener todos los archivos trimestrales
        $sql = "SELECT a.*, u.nombre as escuela_nombre 
                FROM archivos_trimestrales a 
                LEFT JOIN usuarios u ON a.escuela_id = u.escuela_id 
                ORDER BY a.escuela_id";
        $result = $conn->query($sql);
        
        if ($result->num_rows === 0) {
            return "No hay archivos trimestrales para consolidar";
        }
        
        $hoja_index = 0;
        while($archivo = $result->fetch_assoc()) {
            if (!file_exists($archivo['ruta_archivo'])) {
                continue;
            }
            
            // Cargar archivo de cada escuela
            $archivo_spreadsheet = IOFactory::load($archivo['ruta_archivo']);
            $worksheet = $archivo_spreadsheet->getActiveSheet();
            
            // Crear nueva hoja en el consolidado
            $nueva_hoja = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, $archivo['escuela_nombre']);
            $spreadsheet->addSheet($nueva_hoja, $hoja_index);
            
            // Copiar contenido
            foreach ($worksheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                
                $fila_destino = $row->getRowIndex();
                foreach ($cellIterator as $cell) {
                    $columna_destino = $cell->getColumn();
                    $nueva_hoja->setCellValue($columna_destino . $fila_destino, $cell->getCalculatedValue());
                }
            }
            
            // Agregar encabezado
            $nueva_hoja->insertNewRowBefore(1, 2);
            $nueva_hoja->setCellValue('A1', 'ESCUELA: ' . $archivo['escuela_nombre']);
            $nueva_hoja->setCellValue('A2', 'REPORTE TRIMESTRAL - FECHA: ' . date('d/m/Y'));
            
            $hoja_index++;
        }
        
        if ($hoja_index === 0) {
            return "No se pudieron cargar archivos trimestrales válidos";
        }
        
        // Guardar archivo consolidado
        $target_dir = "uploads/consolidados_trimestrales/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $nombre_archivo = "CONSOLIDADO_TRIMESTRAL_" . date('Y-m-d') . ".xlsx";
        $ruta_archivo = $target_dir . $nombre_archivo;
        
        $writer = new Xlsx($spreadsheet);
        $writer->save($ruta_archivo);
        
        // Guardar en base de datos
        $sql_consolidado = "INSERT INTO consolidados_trimestrales (nombre_archivo, ruta_archivo, fecha_creacion) 
                           VALUES ('$nombre_archivo', '$ruta_archivo', NOW())";
        
        if ($conn->query($sql_consolidado)) {
            return "success:" . $nombre_archivo;
        } else {
            return "Error al guardar en base de datos: " . $conn->error;
        }
        
    } catch (Exception $e) {
        return "Error al consolidar archivos trimestrales: " . $e->getMessage();
    }
}

/**
 * Consolidar archivos CTZ
 */
function consolidarArchivosCTZ() {
    global $conn;
    
    try {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0); // Eliminar hoja por defecto
        
        // Obtener todos los archivos CTZ de escuelas
        $sql = "SELECT a.*, u.nombre as escuela_nombre 
                FROM archivos_ctz_escuelas a 
                LEFT JOIN usuarios u ON a.escuela_id = u.escuela_id 
                ORDER BY a.escuela_id";
        $result = $conn->query($sql);
        
        if ($result->num_rows === 0) {
            return "No hay archivos CTE para consolidar";
        }
        
        $hoja_index = 0;
        while($archivo = $result->fetch_assoc()) {
            if (!file_exists($archivo['ruta_archivo'])) {
                continue;
            }
            
            // Cargar archivo de cada escuela
            $archivo_spreadsheet = IOFactory::load($archivo['ruta_archivo']);
            $worksheet = $archivo_spreadsheet->getActiveSheet();
            
            // Crear nueva hoja en el consolidado
            $nueva_hoja = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, $archivo['escuela_nombre']);
            $spreadsheet->addSheet($nueva_hoja, $hoja_index);
            
            // Copiar contenido
            foreach ($worksheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                
                $fila_destino = $row->getRowIndex();
                foreach ($cellIterator as $cell) {
                    $columna_destino = $cell->getColumn();
                    $nueva_hoja->setCellValue($columna_destino . $fila_destino, $cell->getCalculatedValue());
                }
            }
            
            // Agregar encabezado
            $nueva_hoja->insertNewRowBefore(1, 2);
            $nueva_hoja->setCellValue('A1', 'ESCUELA: ' . $archivo['escuela_nombre']);
            $nueva_hoja->setCellValue('A2', 'CONSEJO TÉCNICO ESCOLAR - FECHA: ' . date('d/m/Y'));
            
            $hoja_index++;
        }
        
        if ($hoja_index === 0) {
            return "No se pudieron cargar archivos CTE válidos";
        }
        
        // Guardar archivo consolidado
        $target_dir = "uploads/consolidados_ctz/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $nombre_archivo = "CONSOLIDADO_CTZ_" . date('Y-m-d') . ".xlsx";
        $ruta_archivo = $target_dir . $nombre_archivo;
        
        $writer = new Xlsx($spreadsheet);
        $writer->save($ruta_archivo);
        
        // Guardar en base de datos
        $sql_consolidado = "INSERT INTO consolidados_ctz (nombre_archivo, ruta_archivo, fecha_creacion) 
                           VALUES ('$nombre_archivo', '$ruta_archivo', NOW())";
        
        if ($conn->query($sql_consolidado)) {
            return "success:" . $nombre_archivo;
        } else {
            return "Error al guardar en base de datos: " . $conn->error;
        }
        
    } catch (Exception $e) {
        return "Error al consolidar archivos CTE: " . $e->getMessage();
    }
}

// ============================
// PROCESAMIENTO DE FORMULARIOS
// ============================

// Procesar login
$login_error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    $sql = "SELECT * FROM usuarios WHERE email = '$email'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user;
            header("Location: index.php");
            exit();
        } else {
            $login_error = "Contraseña incorrecta";
        }
    } else {
        $login_error = "Usuario no encontrado";
    }
}

// Procesar consolidación de archivos
if (isset($_GET['action'])) {
    if (!isset($_SESSION['user']) || $_SESSION['user']['rol'] !== 'admin') {
        die("No autorizado");
    }
    
    $action = $_GET['action'];
    
    if ($action == 'merge' && isset($_GET['turno'])) {
        $turno = $_GET['turno'];
        $resultado = consolidarArchivosAsistencia($turno);
        
        if (strpos($resultado, 'success:') === 0) {
            $nombre_archivo = str_replace('success:', '', $resultado);
            echo "<script>alert('Archivos consolidados exitosamente: $nombre_archivo'); window.location.href = 'index.php#asistencia';</script>";
        } else {
            echo "<script>alert('Error: $resultado'); window.location.href = 'index.php#asistencia';</script>";
        }
        exit();
    }
    
    if ($action == 'merge_trimestral') {
        $resultado = consolidarArchivosTrimestrales();
        
        if (strpos($resultado, 'success:') === 0) {
            $nombre_archivo = str_replace('success:', '', $resultado);
            echo "<script>alert('Reportes trimestrales consolidados exitosamente: $nombre_archivo'); window.location.href = 'index.php#trimestral';</script>";
        } else {
            echo "<script>alert('Error: $resultado'); window.location.href = 'index.php#trimestral';</script>";
        }
        exit();
    }
    
    if ($action == 'merge_ctz') {
        $resultado = consolidarArchivosCTZ();
        
        if (strpos($resultado, 'success:') === 0) {
            $nombre_archivo = str_replace('success:', '', $resultado);
            echo "<script>alert('Documentos CTE consolidados exitosamente: $nombre_archivo'); window.location.href = 'index.php#ctz';</script>";
        } else {
            echo "<script>alert('Error: $resultado'); window.location.href = 'index.php#ctz';</script>";
        }
        exit();
    }
    
    // Envío de consolidados
    if ($action == 'send_consolidated' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        
        // Obtener información del consolidado
        $sql = "SELECT * FROM consolidados WHERE id = $id";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            $consolidado = $result->fetch_assoc();
            $resultado = enviarConcentradoPorCorreo($consolidado['ruta_archivo'], 'asistencia', $consolidado['turno']);
            
            if ($resultado == "Realizado exitosamente.") {
                deleteConsolidatedAfterSend($id, $consolidado['ruta_archivo'], 'asistencia');
                echo "<script>alert('Concentrado enviado exitosamente y eliminado del sistema'); window.location.href = 'index.php#asistencia';</script>";
            } else {
                echo "<script>alert('Error al enviar: $resultado'); window.location.href = 'index.php#asistencia';</script>";
            }
        }
        exit();
    }
    
    if ($action == 'send_consolidated_trimestral' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        
        $sql = "SELECT * FROM consolidados_trimestrales WHERE id = $id";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            $consolidado = $result->fetch_assoc();
            $resultado = enviarConcentradoPorCorreo($consolidado['ruta_archivo'], 'trimestral');
            
            if ($resultado == "Realizado exitosamente.") {
                deleteConsolidatedAfterSend($id, $consolidado['ruta_archivo'], 'trimestral');
                echo "<script>alert('Reporte trimestral enviado exitosamente y eliminado del sistema'); window.location.href = 'index.php#trimestral';</script>";
            } else {
                echo "<script>alert('Error al enviar: $resultado'); window.location.href = 'index.php#trimestral';</script>";
            }
        }
        exit();
    }
    
    if ($action == 'send_consolidated_ctz' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        
        $sql = "SELECT * FROM consolidados_ctz WHERE id = $id";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            $consolidado = $result->fetch_assoc();
            $resultado = enviarConcentradoPorCorreo($consolidado['ruta_archivo'], 'ctz');
            
            if ($resultado == "Realizado exitosamente.") {
                deleteConsolidatedAfterSend($id, $consolidado['ruta_archivo'], 'ctz');
                echo "<script>alert('Documento CTE enviado exitosamente y eliminado del sistema'); window.location.href = 'index.php#ctz';</script>";
            } else {
                echo "<script>alert('Error al enviar: $resultado'); window.location.href = 'index.php#ctz';</script>";
            }
        }
        exit();
    }
}

// Procesar gestión de destinatarios - VERSIÓN MEJORADA CON REFRESH
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action_destinatarios'])) {
    if (!isset($_SESSION['user']) || $_SESSION['user']['rol'] !== 'admin') {
        die("No autorizado");
    }
    
    $action = $_POST['action_destinatarios'];
    
    if ($action == 'agregar' && isset($_POST['email'])) {
        $email = $conn->real_escape_string($_POST['email']);
        
        $sql_check = "SELECT id FROM destinatarios WHERE email = '$email'";
        $result_check = $conn->query($sql_check);
        
        if ($result_check->num_rows > 0) {
            echo "<script>alert('El correo electrónico ya existe en la lista de destinatarios.'); window.location.href = 'index.php#asistencia';</script>";
        } else {
            $sql = "INSERT INTO destinatarios (email, activo) VALUES ('$email', 1)";
            if ($conn->query($sql)) {
                echo "<script>alert('Destinatario agregado correctamente.'); window.location.href = 'index.php#asistencia';</script>";
            } else {
                echo "<script>alert('Error al agregar destinatario: " . $conn->error . "'); window.location.href = 'index.php#asistencia';</script>";
            }
        }
        exit();
    }
    elseif ($action == 'actualizar_seleccion' && isset($_POST['destinatarios_seleccionados'])) {
        $sql_deactivate = "UPDATE destinatarios SET activo = 0";
        $conn->query($sql_deactivate);
        
        $selected_ids = $_POST['destinatarios_seleccionados'];
        foreach ($selected_ids as $id) {
            $safe_id = intval($id);
            $sql_activate = "UPDATE destinatarios SET activo = 1 WHERE id = $safe_id";
            $conn->query($sql_activate);
        }
        
        echo "<script>alert('Selección de destinatarios actualizada correctamente.'); window.location.href = 'index.php#asistencia';</script>";
        exit();
    }
    elseif ($action == 'editar' && isset($_POST['id']) && isset($_POST['email'])) {
        $id = intval($_POST['id']);
        $email = $conn->real_escape_string($_POST['email']);
        
        $sql = "UPDATE destinatarios SET email = '$email' WHERE id = $id";
        if ($conn->query($sql)) {
            echo "<script>alert('Destinatario actualizado correctamente.'); window.location.href = 'index.php#asistencia';</script>";
        } else {
            echo "<script>alert('Error al actualizar destinatario: " . $conn->error . "'); window.location.href = 'index.php#asistencia';</script>";
        }
        exit();
    }
    elseif ($action == 'eliminar' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        
        $sql = "DELETE FROM destinatarios WHERE id = $id";
        if ($conn->query($sql)) {
            echo "<script>alert('Destinatario eliminado correctamente.'); window.location.href = 'index.php#asistencia';</script>";
        } else {
            echo "<script>alert('Error al eliminar destinatario: " . $conn->error . "'); window.location.href = 'index.php#asistencia';</script>";
        }
        exit();
    }
}

// Procesar eliminación de archivos - CORREGIDO
if (isset($_GET['delete_file'])) {
    if (!isset($_SESSION['user']) || $_SESSION['user']['rol'] !== 'admin') {
        die("No autorizado");
    }
    
    $fileId = intval($_GET['delete_file']);
    
    // Obtener información del archivo
    $sql = "SELECT ruta_archivo FROM archivos WHERE id = $fileId";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $file = $result->fetch_assoc();
        $filePath = $file['ruta_archivo'];
        
        // Eliminar archivo físico
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Eliminar registro de la base de datos
        $sql_delete = "DELETE FROM archivos WHERE id = $fileId";
        if ($conn->query($sql_delete)) {
            echo "<script>alert('Archivo eliminado correctamente'); window.location.href = 'index.php';</script>";
            exit();
        } else {
            echo "<script>alert('Error al eliminar el archivo de la base de datos'); window.location.href = 'index.php';</script>";
        }
    } else {
        echo "<script>alert('Archivo no encontrado'); window.location.href = 'index.php';</script>";
    }
}

// Procesar eliminación de archivos trimestrales - CORREGIDO
if (isset($_GET['delete_file_trimestral'])) {
    if (!isset($_SESSION['user']) || $_SESSION['user']['rol'] !== 'admin') {
        die("No autorizado");
    }
    
    $fileId = intval($_GET['delete_file_trimestral']);
    
    // Obtener información del archivo
    $sql = "SELECT ruta_archivo FROM archivos_trimestrales WHERE id = $fileId";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $file = $result->fetch_assoc();
        $filePath = $file['ruta_archivo'];
        
        // Eliminar archivo físico
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Eliminar registro de la base de datos
        $sql_delete = "DELETE FROM archivos_trimestrales WHERE id = $fileId";
        if ($conn->query($sql_delete)) {
            echo "<script>alert('Archivo trimestral eliminado correctamente'); window.location.href = 'index.php#trimestral';</script>";
            exit();
        } else {
            echo "<script>alert('Error al eliminar el archivo de la base de datos'); window.location.href = 'index.php#trimestral';</script>";
        }
    } else {
        echo "<script>alert('Archivo no encontrado'); window.location.href = 'index.php#trimestral';</script>";
    }
}

// Procesar eliminación de archivos CTZ - CORREGIDO
if (isset($_GET['delete_file_ctz'])) {
    if (!isset($_SESSION['user']) || $_SESSION['user']['rol'] !== 'admin') {
        die("No autorizado");
    }
    
    $fileId = intval($_GET['delete_file_ctz']);
    
    // Obtener información del archivo
    $sql = "SELECT ruta_archivo FROM archivos_ctz WHERE id = $fileId";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $file = $result->fetch_assoc();
        $filePath = $file['ruta_archivo'];
        
        // Eliminar archivo físico
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Eliminar registro de la base de datos
        $sql_delete = "DELETE FROM archivos_ctz WHERE id = $fileId";
        if ($conn->query($sql_delete)) {
            echo "<script>alert('Documento CTE eliminado correctamente'); window.location.href = 'index.php#ctz';</script>";
            exit();
        } else {
            echo "<script>alert('Error al eliminar el archivo de la base de datos'); window.location.href = 'index.php#ctz';</script>";
        }
    } else {
        echo "<script>alert('Archivo no encontrado'); window.location.href = 'index.php#ctz';</script>";
    }
}

// Procesar eliminación de archivos CTZ escuelas - CORREGIDO
if (isset($_GET['delete_file_ctz_escuela'])) {
    if (!isset($_SESSION['user']) || $_SESSION['user']['rol'] !== 'admin') {
        die("No autorizado");
    }
    
    $fileId = intval($_GET['delete_file_ctz_escuela']);
    
    // Obtener información del archivo
    $sql = "SELECT ruta_archivo FROM archivos_ctz_escuelas WHERE id = $fileId";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $file = $result->fetch_assoc();
        $filePath = $file['ruta_archivo'];
        
        // Eliminar archivo físico
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Eliminar registro de la base de datos
        $sql_delete = "DELETE FROM archivos_ctz_escuelas WHERE id = $fileId";
        if ($conn->query($sql_delete)) {
            echo "<script>alert('Documento CTE de escuela eliminado correctamente'); window.location.href = 'index.php#ctz';</script>";
            exit();
        } else {
            echo "<script>alert('Error al eliminar el archivo de la base de datos'); window.location.href = 'index.php#ctz';</script>";
        }
    } else {
        echo "<script>alert('Archivo no encontrado'); window.location.href = 'index.php#ctz';</script>";
    }
}

// Procesar subida de archivos diarios - CON REFRESH MEJORADO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["excelFile"]) && isset($_POST["turno"]) && $_POST["turno"] != 'trimestral') {
    if (!isset($_SESSION['user'])) {
        die("No autenticado");
    }
    
    $turno_subida = $_POST["turno"];
    
    // Validar horario
    if (!validarHorarioSubida($turno_subida)) {
        echo "<script>alert('El turno matutino ha finalizado. No se pueden subir archivos para este turno.'); window.location.href = 'index.php';</script>";
        exit();
    }
    
    // Validar tipo de archivo
    $allowed_types = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/octet-stream'];
    $file_type = $_FILES["excelFile"]["type"];
    
    if (!in_array($file_type, $allowed_types)) {
        echo "<script>alert('Solo se permiten archivos Excel.'); window.location.href = 'index.php';</script>";
        exit();
    }
    
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $schoolId = $_POST["schoolId"];
    $turno = $_POST["turno"];
    $fecha = date('Y-m-d');
    $target_file = $target_dir . $schoolId . "_" . $turno . "_" . $fecha . "_" . basename($_FILES["excelFile"]["name"]);
    
    if (move_uploaded_file($_FILES["excelFile"]["tmp_name"], $target_file)) {
        try {
            $spreadsheet = IOFactory::load($target_file);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Guardar en base de datos
            $nombre_archivo = basename($_FILES['excelFile']['name']);
            $ruta_archivo = $target_file;
            $escuela_id = $schoolId;
            
            // Verificar si ya existe un archivo para este turno y escuela hoy
            $sql_check = "SELECT id FROM archivos WHERE escuela_id = '$escuela_id' AND turno = '$turno' AND DATE(fecha_subida) = '$fecha'";
            $result_check = $conn->query($sql_check);
            
            if ($result_check->num_rows > 0) {
                // Actualizar archivo existente
                $row = $result_check->fetch_assoc();
                $file_id = $row['id'];
                $sql = "UPDATE archivos SET nombre_archivo = '$nombre_archivo', ruta_archivo = '$ruta_archivo', fecha_subida = NOW() WHERE id = $file_id";
            } else {
                // Insertar nuevo archivo
                $sql = "INSERT INTO archivos (escuela_id, turno, nombre_archivo, ruta_archivo, fecha_subida) 
                        VALUES ('$escuela_id', '$turno', '$nombre_archivo', '$ruta_archivo', NOW())";
            }
            
            if ($conn->query($sql)) {
                // REFRESH COMPLETO CORREGIDO - REDIRECCIÓN DIRECTA
                header("Location: index.php?success=1");
                exit();
            } else {
                echo "<script>alert('Error al guardar en base de datos: " . $conn->error . "'); window.location.href = 'index.php';</script>";
            }
        } catch (Exception $e) {
            echo "<script>alert('Error al procesar el archivo Excel: " . $e->getMessage() . "'); window.location.href = 'index.php';</script>";
        }
    } else {
        echo "<script>alert('Error al subir archivo'); window.location.href = 'index.php';</script>";
    }
}

// Procesar subida de archivos trimestrales - CON REFRESH MEJORADO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["excelFile"]) && isset($_POST["turno"]) && $_POST["turno"] == 'trimestral') {
    if (!isset($_SESSION['user'])) {
        die("No autenticado");
    }
    
    // Validar tipo de archivo
    $allowed_types = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/octet-stream'];
    $file_type = $_FILES["excelFile"]["type"];
    
    if (!in_array($file_type, $allowed_types)) {
        echo "<script>alert('Solo se permiten archivos Excel.'); window.location.href = 'index.php#trimestral';</script>";
        exit();
    }
    
    $target_dir = "uploads/trimestral/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $schoolId = $_POST["schoolId"];
    $fecha = date('Y-m-d');
    $target_file = $target_dir . $schoolId . "_trimestral_" . $fecha . "_" . basename($_FILES["excelFile"]["name"]);
    
    if (move_uploaded_file($_FILES["excelFile"]["tmp_name"], $target_file)) {
        // Guardar en base de datos
        $nombre_archivo = basename($_FILES['excelFile']['name']);
        $ruta_archivo = $target_file;
        $escuela_id = $schoolId;
        
        // Verificar si ya existe un archivo para esta escuela
        $sql_check = "SELECT id FROM archivos_trimestrales WHERE escuela_id = '$escuela_id'";
        $result_check = $conn->query($sql_check);
        
        if ($result_check->num_rows > 0) {
            // Actualizar archivo existente
            $row = $result_check->fetch_assoc();
            $file_id = $row['id'];
            $sql = "UPDATE archivos_trimestrales SET nombre_archivo = '$nombre_archivo', ruta_archivo = '$ruta_archivo', fecha_subida = NOW() WHERE id = $file_id";
        } else {
            // Insertar nuevo archivo
            $sql = "INSERT INTO archivos_trimestrales (escuela_id, nombre_archivo, ruta_archivo, fecha_subida) 
                    VALUES ('$escuela_id', '$nombre_archivo', '$ruta_archivo', NOW())";
        }
        
        if ($conn->query($sql)) {
            // REFRESH COMPLETO CORREGIDO - REDIRECCIÓN DIRECTA
            header("Location: index.php#trimestral&success=1");
            exit();
        } else {
            echo "<script>alert('Error al guardar en base de datos: " . $conn->error . "'); window.location.href = 'index.php#trimestral';</script>";
        }
    } else {
        echo "<script>alert('Error al subir archivo'); window.location.href = 'index.php#trimestral';</script>";
    }
}

// Procesar subida de archivos CTZ - CORREGIDO: Redirección mejorada CON REFRESH
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["ctzFile"]) && isset($_POST["action"]) && $_POST["action"] == 'upload_ctz') {
    if (!isset($_SESSION['user']) || $_SESSION['user']['rol'] !== 'admin') {
        die("No autorizado");
    }
    
    $allowed_types = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/octet-stream'];
    $file_type = $_FILES["ctzFile"]["type"];
    
    if (!in_array($file_type, $allowed_types)) {
        echo "<script>alert('Solo se permiten archivos Excel.'); window.location.href = 'index.php#ctz';</script>";
        exit();
    }
    
    $target_dir = "uploads/ctz/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $fecha = date('Y-m-d');
    $target_file = $target_dir . "ctz_documento_" . $fecha . "_" . basename($_FILES["ctzFile"]["name"]);
    
    if (move_uploaded_file($_FILES["ctzFile"]["tmp_name"], $target_file)) {
        // Guardar en base de datos
        $nombre_archivo = basename($_FILES['ctzFile']['name']);
        $ruta_archivo = $target_file;
        
        // Verificar si ya existe un archivo CTZ
        $sql_check = "SELECT id FROM archivos_ctz WHERE tipo = 'documento'";
        $result_check = $conn->query($sql_check);
        
        if ($result_check->num_rows > 0) {
            // Actualizar archivo existente
            $row = $result_check->fetch_assoc();
            $file_id = $row['id'];
            $sql = "UPDATE archivos_ctz SET nombre_archivo = '$nombre_archivo', ruta_archivo = '$ruta_archivo', fecha_subida = NOW() WHERE id = $file_id";
        } else {
            // Insertar nuevo archivo
            $sql = "INSERT INTO archivos_ctz (tipo, nombre_archivo, ruta_archivo, fecha_subida) 
                    VALUES ('documento', '$nombre_archivo', '$ruta_archivo', NOW())";
        }
        
        if ($conn->query($sql)) {
            // REFRESH COMPLETO CORREGIDO - REDIRECCIÓN DIRECTA
            header("Location: index.php#ctz&success=1");
            exit();
        } else {
            echo "<script>alert('Error al guardar en base de datos: " . $conn->error . "'); window.location.href = 'index.php#ctz';</script>";
            exit();
        }
    } else {
        echo "<script>alert('Error al subir archivo'); window.location.href = 'index.php#ctz';</script>";
        exit();
    }
}

// Procesar subida de archivos CTZ por escuelas - CORREGIDO: Redirección mejorada CON REFRESH
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["ctzFile"]) && isset($_POST["action"]) && $_POST["action"] == 'upload_ctz_escuela') {
    if (!isset($_SESSION['user'])) {
        die("No autenticado");
    }
    
    $allowed_types = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/octet-stream'];
    $file_type = $_FILES["ctzFile"]["type"];
    
    if (!in_array($file_type, $allowed_types)) {
        echo "<script>alert('Solo se permiten archivos Excel.'); window.location.href = 'index.php#ctz';</script>";
        exit();
    }
    
    $target_dir = "uploads/ctz/escuelas/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $schoolId = $_POST["schoolId"];
    $fecha = date('Y-m-d');
    $target_file = $target_dir . $schoolId . "_ctz_" . $fecha . "_" . basename($_FILES["ctzFile"]["name"]);
    
    if (move_uploaded_file($_FILES["ctzFile"]["tmp_name"], $target_file)) {
        // Guardar en base de datos
        $nombre_archivo = basename($_FILES['ctzFile']['name']);
        $ruta_archivo = $target_file;
        $escuela_id = $schoolId;
        
        // Verificar si ya existe un archivo CTZ para esta escuela
        $sql_check = "SELECT id FROM archivos_ctz_escuelas WHERE escuela_id = '$escuela_id'";
        $result_check = $conn->query($sql_check);
        
        if ($result_check->num_rows > 0) {
            // Actualizar archivo existente
            $row = $result_check->fetch_assoc();
            $file_id = $row['id'];
            $sql = "UPDATE archivos_ctz_escuelas SET nombre_archivo = '$nombre_archivo', ruta_archivo = '$ruta_archivo', fecha_subida = NOW() WHERE id = $file_id";
        } else {
            // Insertar nuevo archivo
            $sql = "INSERT INTO archivos_ctz_escuelas (escuela_id, nombre_archivo, ruta_archivo, fecha_subida) 
                    VALUES ('$escuela_id', '$nombre_archivo', '$ruta_archivo', NOW())";
        }
        
        if ($conn->query($sql)) {
            // REFRESH COMPLETO CORREGIDO - REDIRECCIÓN DIRECTA
            header("Location: index.php#ctz&success=1");
            exit();
        } else {
            echo "<script>alert('Error al guardar en base de datos: " . $conn->error . "'); window.location.href = 'index.php#ctz';</script>";
            exit();
        }
    } else {
        echo "<script>alert('Error al subir archivo'); window.location.href = 'index.php#ctz';</script>";
        exit();
    }
}

// Cerrar sesión
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// ============================
// OBTENER DATOS PARA LA VISTA
// ============================

// Obtener turno actual
$turno_actual = getTurnoActual();

// Obtener archivos subidos hoy
$archivos_hoy = array();
if (isset($_SESSION['user'])) {
    $hoy = date('Y-m-d');
    $sql = "SELECT * FROM archivos WHERE DATE(fecha_subida) = '$hoy'";
    $result = $conn->query($sql);
    
    while($row = $result->fetch_assoc()) {
        $archivos_hoy[$row['escuela_id']][$row['turno']] = $row;
    }
}

// Obtener historial (última semana)
$historial = array();
$semana_pasada = date('Y-m-d', strtotime('-7 days'));
$sql = "SELECT a.*, u.nombre as escuela_nombre 
        FROM archivos a 
        LEFT JOIN usuarios u ON a.escuela_id = u.escuela_id 
        WHERE a.fecha_subida >= '$semana_pasada' 
        ORDER BY a.fecha_subida DESC";
$result = $conn->query($sql);

while($row = $result->fetch_assoc()) {
    $historial[] = $row;
}

// Obtener historial trimestral (última semana) - con manejo de errores
$historial_trimestral = array();
try {
    $sql_trimestral_historial = "SELECT a.*, u.nombre as escuela_nombre 
            FROM archivos_trimestrales a 
            LEFT JOIN usuarios u ON a.escuela_id = u.escuela_id 
            WHERE a.fecha_subida >= '$semana_pasada' 
            ORDER BY a.fecha_subida DESC";
    $result_trimestral_historial = $conn->query($sql_trimestral_historial);
    
    if ($result_trimestral_historial) {
        while($row = $result_trimestral_historial->fetch_assoc()) {
            $historial_trimestral[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error al obtener historial trimestral: " . $e->getMessage());
}

// Obtener historial CTZ (última semana) - con manejo de errores
$historial_ctz = array();
try {
    // Verificar si la tabla existe primero
    $table_check = "SHOW TABLES LIKE 'archivos_ctz_escuelas'";
    $result_check = $conn->query($table_check);
    
    if ($result_check->num_rows > 0) {
        $sql_ctz_historial = "SELECT a.*, u.nombre as escuela_nombre 
                FROM archivos_ctz_escuelas a 
                LEFT JOIN usuarios u ON a.escuela_id = u.escuela_id 
                WHERE a.fecha_subida >= '$semana_pasada' 
                ORDER BY a.fecha_subida DESC";
        $result_ctz_historial = $conn->query($sql_ctz_historial);
        
        if ($result_ctz_historial) {
            while($row = $result_ctz_historial->fetch_assoc()) {
                $historial_ctz[] = $row;
            }
        }
    }
} catch (Exception $e) {
    error_log("Error al obtener historial CTE: " . $e->getMessage());
}

// Obtener archivos consolidados
$consolidados = array();
if (isset($_SESSION['user']) && $_SESSION['user']['rol'] === 'admin') {
    $sql = "SELECT * FROM consolidados ORDER BY fecha_creacion DESC LIMIT 10";
    $result = $conn->query($sql);
    
    while($row = $result->fetch_assoc()) {
        $consolidados[] = $row;
    }
}

// Obtener destinatarios
$destinatarios = array();
if (isset($_SESSION['user']) && $_SESSION['user']['rol'] === 'admin') {
    $sql = "SELECT * FROM destinatarios ORDER BY id";
    $result = $conn->query($sql);
    
    while($row = $result->fetch_assoc()) {
        $destinatarios[] = $row;
    }
}

// Obtener archivos trimestrales
$archivos_trimestrales = array();
if (isset($_SESSION['user'])) {
    $sql_trimestral = "SELECT * FROM archivos_trimestrales WHERE 1=1";
    
    // Si no es admin, filtrar por su escuela
    if ($_SESSION['user']['rol'] !== 'admin') {
        $sql_trimestral .= " AND escuela_id = '" . $_SESSION['user']['escuela_id'] . "'";
    }
    
    $sql_trimestral .= " ORDER BY fecha_subida DESC";
    $result_trimestral = $conn->query($sql_trimestral);
    
    while($row = $result_trimestral->fetch_assoc()) {
        $archivos_trimestrales[] = $row;
    }
}

// Obtener consolidados trimestrales (solo para admin)
$consolidados_trimestrales = array();
if (isset($_SESSION['user']) && $_SESSION['user']['rol'] === 'admin') {
    $sql = "SELECT * FROM consolidados_trimestrales ORDER BY fecha_creacion DESC LIMIT 10";
    $result = $conn->query($sql);
    
    while($row = $result->fetch_assoc()) {
        $consolidados_trimestrales[] = $row;
    }
}

// Obtener archivo CTZ (solo para admin) - CON CORRECCIÓN
$archivo_ctz = null;
if (isset($_SESSION['user']) && $_SESSION['user']['rol'] === 'admin') {
    $archivo_ctz = getArchivoCTZMaestro($conn);
}

// Obtener archivo CTZ maestro para usuarios normales también - NUEVO
$archivo_ctz_maestro_global = null;
if (isset($_SESSION['user'])) {
    $archivo_ctz_maestro_global = getArchivoCTZMaestro($conn);
}

// Obtener archivos CTZ por escuelas - con manejo de errores
$archivos_ctz_escuelas = array();
if (isset($_SESSION['user'])) {
    try {
        // Verificar si la tabla existe primero
        $table_check = "SHOW TABLES LIKE 'archivos_ctz_escuelas'";
        $result_check = $conn->query($table_check);
        
        if ($result_check->num_rows > 0) {
            $sql_ctz = "SELECT a.*, u.nombre as escuela_nombre 
                    FROM archivos_ctz_escuelas a 
                    LEFT JOIN usuarios u ON a.escuela_id = u.escuela_id 
                    WHERE 1=1";
            
            // Si no es admin, filtrar por su escuela
            if ($_SESSION['user']['rol'] !== 'admin') {
                $sql_ctz .= " AND a.escuela_id = '" . $_SESSION['user']['escuela_id'] . "'";
            }
            
            $sql_ctz .= " ORDER BY a.fecha_subida DESC";
            $result_ctz = $conn->query($sql_ctz);
            
            if ($result_ctz) {
                while($row = $result_ctz->fetch_assoc()) {
                    $archivos_ctz_escuelas[] = $row;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error al obtener archivos CTE escuelas: " . $e->getMessage());
    }
}

// Obtener consolidados CTZ (solo para admin) - con manejo de errores
$consolidados_ctz = array();
if (isset($_SESSION['user']) && $_SESSION['user']['rol'] === 'admin') {
    try {
        // Verificar si la tabla existe primero
        $table_check = "SHOW TABLES LIKE 'consolidados_ctz'";
        $result_check = $conn->query($table_check);
        
        if ($result_check->num_rows > 0) {
            $sql = "SELECT * FROM consolidados_ctz ORDER BY fecha_creacion DESC LIMIT 10";
            $result = $conn->query($sql);
            
            if ($result) {
                while($row = $result->fetch_assoc()) {
                    $consolidados_ctz[] = $row;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error al obtener consolidados CTE: " . $e->getMessage());
    }
}

// Mostrar mensaje de éxito si existe
if (isset($_GET['success'])) {
    echo "<script>alert('Operación realizada exitosamente');</script>";
}

// Configuración para JavaScript
$js_config = [
    'schoolName' => isset($_SESSION['user']) ? $_SESSION['user']['nombre'] : '',
    'isAdmin' => isset($_SESSION['user']) && $_SESSION['user']['rol'] === 'admin',
    'hasSession' => isset($_SESSION['user'])
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisión - Zona X</title>
    <link rel="icon" href="assets/logoweb.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=<?php echo time(); ?>">
    <script>
        // Configuración para JavaScript
        window.appConfig = <?php echo json_encode($js_config); ?>;
    </script>
</head>
<body>
    <!-- Header -->
    <header>
        <?php if (isset($_SESSION['user'])): ?>
        <div class="hamburger-container">
            <button class="hamburger-menu" id="hamburgerMenu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
        <?php endif; ?>
        
        <div class="container header-content">
            <div class="logo" onclick="window.location.href='index.php'">
                <img src="assets/logo.png" alt="Logo SEC Sonora" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI1MCIgaGVpZ2h0PSI1MCIgdmlld0JveD0iMCAwIDI0IDI4IiBmaWxsPSJub25lIiBzdHJva2U9IiM3YTFjNGEiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InRvdW5kIj48cGF0aCBkPSJNMTAgMTNhNSA1IDAgMCAwIDcgMGwzLTMiPjxwYXRoIGQ9Ik0xNCAxMWE1IDUgMCAwIDAtNyAwbC0zIDMiPjxwYXRoIGQ9Ik0yIDhhOCA4IDAgMCAxIDEwLjg3NDcuNDQ5IiAvPjxwYXRoIGQ9Ik01IDRoMTRhMiAyIDAgMCAxIDIgMnY0YTIgMiAwIDAgMS0yIDJINWEyIDIgMCAwIDEtMi0yVjZhMiAyIDAgMCAxIDItMnoiLz48L3N2Zz4='">
                <div class="logo-text">
                    <h1>SUPERVISIÓN ZONA X</h1>
                    <p>C. Mtra. Griselda Zaiz Cruz</p>
                </div>
            </div>
            <div class="auth-buttons">
                <?php if (isset($_SESSION['user'])): ?>
                    <button class="btn btn-outline" onclick="location.href='?logout=true'">Cerrar Sesión</button>
                <?php else: ?>
                    <button class="btn btn-primary" id="loginToggleBtn">Iniciar Sesión</button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Navigation Tabs -->
    <?php if (isset($_SESSION['user'])): ?>
    <div class="container nav-container">
        <ul class="nav-tabs" id="navTabs">
            <li><a href="#asistencia" class="tab-link active" data-tab="asistencia">Asistencia</a></li>
            <li><a href="#eventos" class="tab-link" data-tab="eventos">Eventos</a></li>
            <li><a href="#trimestral" class="tab-link" data-tab="trimestral">Trimestral</a></li>
            <li><a href="#ctz" class="tab-link" data-tab="ctz">C.T.E.</a></li>
            <li><a href="#repositorio" class="tab-link" data-tab="repositorio">Repositorio</a></li>
        </ul>
    </div>
    <?php endif; ?>
    
    <!-- Main Content -->
    <div class="container">
        <?php if (!isset($_SESSION['user'])): ?>
            <!-- Banner Section (visible por defecto) -->
            <div class="banner-container" id="bannerContainer">
                <img src="assets/banner.png" alt="Banner Supervisión Zona X" class="banner-image" onerror="this.style.display='none'">
            </div>
            <!-- Login Form (oculto por defecto) -->
            <div id="loginPage" class="login-container" style="display: none;">
                <div class="login-box">
                    <div class="login-header">
                        <h2>Iniciar Sesión</h2>
                        <p>Ingrese sus credenciales para acceder al sistema</p>
                        <?php if (!empty($login_error)): ?>
                            <div class="error-message"><?php echo $login_error; ?></div>
                        <?php endif; ?>
                    </div>
                    <form id="loginForm" method="POST" action="">
                        <div class="form-group">
                            <label for="email">Correo Electrónico</label>
                            <input type="email" id="email" name="email" class="form-control" placeholder="usuario@zonax.com" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Contraseña</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Ingrese su contraseña" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Ingresar</button>
                    </form>
                    <div class="login-footer">
                        <p>¿Problemas para acceder?</p>
                        <p>Soporte cel. 6622293879</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Asistencia Tab -->
            <div id="asistencia" class="tab-content active">
                <!-- Dashboard -->
                <div class="hero">
                    <h2>Reportes de Asistencia Diaria</h2>
                    <p id="welcomeMessage">Bienvenida <?php echo $_SESSION['user']['nombre']; ?></p>
                    <div id="turnInfo" class="turn-info">
                        <h3>Turno Actual: <span id="currentTurn"><?php echo ucfirst($turno_actual); ?></span></h3>
                        <p>Horario: <span id="turnTime"><?php echo ($turno_actual == 'matutino') ? '07:30 AM' : '01:40 PM'; ?></span></p>
                        <p id="nextTurnInfo"><?php echo ($turno_actual == 'matutino') ? 'Próximo turno: Vespertino a las 13:40' : 'Turno vespertino en curso'; ?></p>
                    </div>
                </div>

                <?php if ($_SESSION['user']['rol'] === 'admin'): ?>
                    <!-- Vista Admin -->
                    <div class="dashboard" id="schoolDashboard">
                        <?php
                        $schools = [
                            '26DST0006K' => 'Secundaria Técnica #06',
                            '26DST0060K' => 'Secundaria Técnica #60', 
                            '26DST0072K' => 'Secundaria Técnica #72'
                        ];
                        
                        foreach ($schools as $id => $name): 
                            $hasMatutino = isset($archivos_hoy[$id]['matutino']);
                            $hasVespertino = isset($archivos_hoy[$id]['vespertino']);
                        ?>
                            <div class="card" id="card-<?php echo $id; ?>">
                                <div class="card-header"><?php echo $name; ?></div>
                                <div class="card-body">
                                    <div class="school-info">
                                        <div class="school-icon"><i class="fas fa-school"></i></div>
                                        <div class="school-details">
                                            <h3><?php echo $id; ?></h3>
                                            <p>Reportes del día <?php echo date('d/m/Y'); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="turno-status">
                                        <h4>Turno Matutino</h4>
                                        <div class="upload-status" id="status-<?php echo $id; ?>-matutino">
                                            <div class="status-container">
                                                <span class="status <?php echo $hasMatutino ? 'status-completed' : 'status-pending'; ?>">
                                                    <?php echo $hasMatutino ? 'Completado' : 'Pendiente'; ?>
                                                </span>
                                            </div>
                                            <div class="status-icons">
                                                <?php if ($hasMatutino): ?>
                                                    <button class="view-file-btn" onclick="viewFile('<?php echo $archivos_hoy[$id]['matutino']['ruta_archivo']; ?>', '<?php echo $name; ?> - Matutino', <?php echo $archivos_hoy[$id]['matutino']['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-success btn-sm" onclick="downloadFile('<?php echo $archivos_hoy[$id]['matutino']['ruta_archivo']; ?>', '<?php echo $archivos_hoy[$id]['matutino']['nombre_archivo']; ?>')">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                    <button class="btn btn-primary btn-sm" onclick="openUploadModal('matutino', '<?php echo $id; ?>')">Reemplazar</button>
                                                <?php else: ?>
                                                    <button class="btn btn-primary btn-sm upload-btn" id="uploadBtnMatutino<?php echo $id; ?>" onclick="openUploadModal('matutino', '<?php echo $id; ?>')">Subir Reporte</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <h4>Turno Vespertino</h4>
                                        <div class="upload-status" id="status-<?php echo $id; ?>-vespertino">
                                            <div class="status-container">
                                                <span class="status <?php echo $hasVespertino ? 'status-completed' : 'status-pending'; ?>">
                                                    <?php echo $hasVespertino ? 'Completado' : 'Pendiente'; ?>
                                                </span>
                                            </div>
                                            <div class="status-icons">
                                                <?php if ($hasVespertino): ?>
                                                    <button class="view-file-btn" onclick="viewFile('<?php echo $archivos_hoy[$id]['vespertino']['ruta_archivo']; ?>', '<?php echo $name; ?> - Vespertino', <?php echo $archivos_hoy[$id]['vespertino']['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-success btn-sm" onclick="downloadFile('<?php echo $archivos_hoy[$id]['vespertino']['ruta_archivo']; ?>', '<?php echo $archivos_hoy[$id]['vespertino']['nombre_archivo']; ?>')">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                    <button class="btn btn-primary btn-sm" onclick="openUploadModal('vespertino', '<?php echo $id; ?>')">Reemplazar</button>
                                                <?php else: ?>
                                                    <button class="btn btn-primary btn-sm upload-btn" id="uploadBtnVespertino<?php echo $id; ?>" onclick="openUploadModal('vespertino', '<?php echo $id; ?>')">Subir Reporte</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- Vista Escuela -->
                    <?php 
                    $schoolId = $_SESSION['user']['escuela_id'];
                    $schoolName = $_SESSION['user']['nombre'];
                    $hasMatutino = isset($archivos_hoy[$schoolId]['matutino']);
                    $hasVespertino = isset($archivos_hoy[$schoolId]['vespertino']);
                    ?>
                    <div class="school-view-container">
                        <div class="card school-view-card" id="card-<?php echo $schoolId; ?>">
                            <div class="card-header"><?php echo $schoolName; ?></div>
                            <div class="card-body">
                                <div class="school-info">
                                    <div class="school-icon"><i class="fas fa-school"></i></div>
                                    <div class="school-details">
                                        <h3><?php echo $schoolId; ?></h3>
                                        <p>Reportes del día <?php echo date('d/m/Y'); ?></p>
                                    </div>
                                </div>
                                
                                <div class="turno-status">
                                    <?php if ($turno_actual == 'matutino' || $_SESSION['user']['rol'] === 'admin'): ?>
                                    <h4>Turno Matutino</h4>
                                    <div class="upload-status" id="status-<?php echo $schoolId; ?>-matutino">
                                        <div class="status-container">
                                            <span class="status <?php echo $hasMatutino ? 'status-completed' : 'status-pending'; ?>">
                                                <?php echo $hasMatutino ? 'Completado' : 'Pendiente'; ?>
                                            </span>
                                        </div>
                                        <div class="status-icons">
                                            <?php if ($hasMatutino): ?>
                                                <button class="view-file-btn" onclick="viewFile('<?php echo $archivos_hoy[$schoolId]['matutino']['ruta_archivo']; ?>', 'Matutino', <?php echo $archivos_hoy[$schoolId]['matutino']['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-success btn-sm" onclick="downloadFile('<?php echo $archivos_hoy[$schoolId]['matutino']['ruta_archivo']; ?>', '<?php echo $archivos_hoy[$schoolId]['matutino']['nombre_archivo']; ?>')">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                                <?php if ($turno_actual == 'matutino'): ?>
                                                    <button class="btn btn-primary btn-sm" onclick="openUploadModal('matutino', '<?php echo $schoolId; ?>')">Reemplazar</button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if ($turno_actual == 'matutino'): ?>
                                                    <button class="btn btn-primary btn-sm upload-btn" id="uploadBtnMatutinoSchool" onclick="openUploadModal('matutino', '<?php echo $schoolId; ?>')">Subir Reporte</button>
                                                <?php else: ?>
                                                    <span class="status status-expired">Turno finalizado</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($turno_actual == 'vespertino' || $_SESSION['user']['rol'] === 'admin'): ?>
                                    <h4>Turno Vespertino</h4>
                                    <div class="upload-status" id="status-<?php echo $schoolId; ?>-vespertino">
                                        <div class="status-container">
                                            <span class="status <?php echo $hasVespertino ? 'status-completed' : 'status-pending'; ?>">
                                                <?php echo $hasVespertino ? 'Completado' : 'Pendiente'; ?>
                                            </span>
                                        </div>
                                        <div class="status-icons">
                                            <?php if ($hasVespertino): ?>
                                                <button class="view-file-btn" onclick="viewFile('<?php echo $archivos_hoy[$schoolId]['vespertino']['ruta_archivo']; ?>', 'Vespertino', <?php echo $archivos_hoy[$schoolId]['vespertino']['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-success btn-sm" onclick="downloadFile('<?php echo $archivos_hoy[$schoolId]['vespertino']['ruta_archivo']; ?>', '<?php echo $archivos_hoy[$schoolId]['vespertino']['nombre_archivo']; ?>')">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                                <?php if ($turno_actual == 'vespertino'): ?>
                                                    <button class="btn btn-primary btn-sm" onclick="openUploadModal('vespertino', '<?php echo $schoolId; ?>')">Reemplazar</button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if ($turno_actual == 'vespertino'): ?>
                                                    <button class="btn btn-primary btn-sm upload-btn" id="uploadBtnVespertinoSchool" onclick="openUploadModal('vespertino', '<?php echo $schoolId; ?>')">Subir Reporte</button>
                                                <?php else: ?>
                                                    <span class="status status-expired">Turno no iniciado</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($_SESSION['user']['rol'] === 'admin'): ?>
                    <div id="adminPanel">
                        <div class="merge-section">
                            <h3>Consolidar Reportes</h3>
                            <div class="merge-action">
                                <?php
                                $hora_actual = date('H:i');
                                $turno_consolidar = ($hora_actual < '09:00') ? 'matutino' : 'vespertino';
                                $texto_turno = ($hora_actual < '09:00') ? 'Matutinos' : 'Vespertinos';
                                ?>
                                <button class="btn consolidate-btn" id="mergeBtn" onclick="mergeFiles('<?php echo $turno_consolidar; ?>')">
                                    Adjuntar Archivos <?php echo $texto_turno; ?>
                                </button>
                                <div class="spinner" id="mergeSpinner"></div>
                            </div>
                            
                            <?php if (!empty($consolidados)): ?>
                                <div class="consolidados-list">
                                    <h4>Archivos consolidados recientes:</h4>
                                    <ul>
                                        <?php foreach ($consolidados as $consolidado): ?>
                                            <li>
                                                <span class="file-name-truncate"><?php echo $consolidado['nombre_archivo']; ?></span> 
                                                (<?php echo date('d/m/Y H:i', strtotime($consolidado['fecha_creacion'])); ?>)
                                                <button class="btn btn-success" onclick="downloadFile('<?php echo $consolidado['ruta_archivo']; ?>', '<?php echo $consolidado['nombre_archivo']; ?>')">
                                                    <i class="fas fa-download"></i> Descargar
                                                </button>
                                                <button class="btn btn-outline" onclick="viewFile('<?php echo $consolidado['ruta_archivo']; ?>', 'Consolidado <?php echo $consolidado['turno']; ?>')">
                                                    <i class="fas fa-eye"></i> Vista previa
                                                </button>
                                                <button class="btn btn-primary" onclick="sendConsolidated(<?php echo $consolidado['id']; ?>)">
                                                    <i class="fas fa-paper-plane"></i> Enviar
                                                </button>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Gestión de destinatarios - ESTILO UNIFICADO -->
                        <div class="destinatarios-container">
                            <h3>Gestión de Destinatarios</h3>
                            <p>Seleccione los correos a los que se enviarán los concentrados</p>
                            
                            <!-- Formulario para agregar destinatario -->
                            <form method="POST" action="" class="form-inline">
                                <input type="hidden" name="action_destinatarios" value="agregar">
                                <input type="email" name="email" class="form-control" placeholder="Correo electrónico" required>
                                <button type="submit" class="btn btn-primary">Agregar</button>
                            </form>
                            
                            <!-- Lista de destinatarios con checkboxes -->
                            <div class="destinatarios-list">
                                <?php if (!empty($destinatarios)): ?>
                                    <form method="POST" action="" id="destinatariosForm">
                                        <input type="hidden" name="action_destinatarios" value="actualizar_seleccion">
                                        <div class="destinatarios-header">
                                            <h4>Destinatarios Disponibles</h4>
                                            <div class="destinatarios-actions">
                                                <button type="button" class="btn btn-sm btn-outline" onclick="seleccionarTodos()">Seleccionar Todos</button>
                                                <button type="button" class="btn btn-sm btn-outline" onclick="deseleccionarTodos()">Limpiar Selección</button>
                                            </div>
                                        </div>
                                        
                                        <?php foreach ($destinatarios as $destinatario): ?>
                                            <div class="destinatario-item">
                                                <label class="destinatario-checkbox">
                                                    <input type="checkbox" name="destinatarios_seleccionados[]" 
                                                           value="<?php echo $destinatario['id']; ?>" 
                                                           <?php echo $destinatario['activo'] ? 'checked' : ''; ?>>
                                                    <span class="checkmark"></span>
                                                </label>
                                                <div class="destinatario-info">
                                                    <div class="destinatario-email"><?php echo htmlspecialchars($destinatario['email']); ?></div>
                                                    <div class="destinatario-status">
                                                        <span class="status-badge <?php echo $destinatario['activo'] ? 'status-active' : 'status-inactive'; ?>">
                                                            <?php echo $destinatario['activo'] ? 'Activo' : 'Inactivo'; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="destinatario-actions">
                                                    <button type="button" class="btn-icon" onclick="editarDestinatario(<?php echo $destinatario['id']; ?>, '<?php echo $destinatario['email']; ?>')" title="Editar">
                                                        <i class="fas fa-edit" style="color: #521426;"></i>
                                                    </button>
                                                    <button type="button" class="btn-icon" onclick="eliminarDestinatario(<?php echo $destinatario['id']; ?>)" title="Eliminar">
                                                        <i class="fas fa-trash" style="color: #521426;"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <div class="destinatarios-footer">
                                            <button type="submit" class="btn btn-primary">Guardar Selección</button>
                                            <span class="selection-info" id="selectionInfo">0 seleccionados</span>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <p>No hay destinatarios registrados.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="history-section">
                            <h3>Historial de Reportes (Última Semana)</h3>
                            <div class="history-table-container">
                                <table class="history-table">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Escuela</th>
                                            <th class="desktop-only">Turno</th>
                                            <th class="desktop-only">Archivo</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($historial as $reporte): ?>
                                            <tr>
                                                <td data-label="Fecha/Hora">
                                                    <?php echo date('d/m/Y H:i', strtotime($reporte['fecha_subida'])); ?>
                                                    <span class="mobile-turno-info"><?php echo ucfirst($reporte['turno']); ?></span>
                                                </td>
                                                <td data-label="Escuela"><?php echo $reporte['escuela_nombre']; ?></td>
                                                <td class="desktop-only" data-label="Turno"><?php echo ucfirst($reporte['turno']); ?></td>
                                                <td class="desktop-only" data-label="Archivo"><span class="file-name-truncate"><?php echo $reporte['nombre_archivo']; ?></span></td>
                                                <td data-label="Acciones" style="white-space: nowrap;">
                                                    <button class="btn-icon" onclick="viewFile('<?php echo $reporte['ruta_archivo']; ?>', '<?php echo $reporte['escuela_nombre']; ?>', <?php echo $reporte['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn-icon btn-success-icon" onclick="downloadFile('<?php echo $reporte['ruta_archivo']; ?>', '<?php echo $reporte['nombre_archivo']; ?>')">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                    <?php if ($_SESSION['user']['rol'] === 'admin'): ?>
                                                        <button class="btn-icon" onclick="deleteFile(<?php echo $reporte['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Eventos Tab -->
            <div id="eventos" class="tab-content">
                <div class="hero eventos-hero">
                    <h2>Eventos y Reuniones</h2>
                    <p>Próximos eventos y actividades de la zona escolar</p>
                </div>
                <div class="events-section">
                    <div class="events-grid">
                        <div class="event-card">
                            <div class="event-image" style="background-image: url('assets/evento2.png')"></div>
                            <div class="event-content">
                                <div class="event-date">16 de Septiembre, 2025</div>
                                <h3 class="event-title">Independencia de México</h3>
                                <p class="event-description">Actividades conmemorativas en las escuelas.</p>
                                <button class="btn btn-outline">Ver Detalles</button>
                            </div>
                        </div>
                        <div class="event-card">
                            <div class="event-image" style="background-image: url('assets/evento3.png')"></div>
                            <div class="event-content">
                                <div class="event-date">23 de Septiembre, 2025</div>
                                <h3 class="event-title">Junta Previa Primer CTE</h3>
                                <p class="event-description">En sus correos podrán ver la Agenda.</p>
                                <button class="btn btn-outline">Ver Detalles</button>
                            </div>
                        </div>
                        <div class="event-card">
                            <div class="event-image" style="background-image: url('assets/evento1.png')"></div>
                            <div class="event-content">
                                <div class="event-date">26 de Septiembre, 2025</div>
                                <h3 class="event-title">Primer Consejo Técnico Escolar</h3>
                                <p class="event-description">Recuerden. Tema: "Comunidad de Aprendizaje".</p>
                                <button class="btn btn-outline">Ver Detalles</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reportes Trimestrales Tab -->
            <div id="trimestral" class="tab-content">
                <div class="hero trimestral-hero">
                    <h2>Reportes Trimestrales</h2>
                    <p>Gestión de reportes trimestrales de asistencia</p>
                </div>
                
                <?php if ($_SESSION['user']['rol'] === 'admin'): ?>
                    <!-- Vista Admin para reportes trimestrales -->
                    <div class="dashboard" id="trimestralDashboard">
                        <?php
                        foreach ($schools as $id => $name): 
                            $hasTrimestral = false;
                            foreach ($archivos_trimestrales as $archivo) {
                                if ($archivo['escuela_id'] == $id) {
                                    $hasTrimestral = true;
                                    $archivo_trimestral = $archivo;
                                    break;
                                }
                            }
                        ?>
                            <div class="card" id="card-trimestral-<?php echo $id; ?>">
                                <div class="card-header"><?php echo $name; ?> - Reporte Trimestral</div>
                                <div class="card-body">
                                    <div class="school-info">
                                        <div class="school-icon"><i class="fas fa-chart-bar"></i></div>
                                        <div class="school-details">
                                            <h3><?php echo $id; ?></h3>
                                            <p>Reporte trimestral</p>
                                        </div>
                                    </div>
                                    
                                    <div class="turno-status">
                                        <div class="upload-status" id="status-trimestral-<?php echo $id; ?>">
                                            <div class="status-container">
                                                <span class="status <?php echo $hasTrimestral ? 'status-completed' : 'status-pending'; ?>">
                                                    <?php echo $hasTrimestral ? 'Completado' : 'Pendiente'; ?>
                                                </span>
                                                <?php if ($hasTrimestral): ?>
                                                    <span class="file-date"><?php echo date('d/m/Y', strtotime($archivo_trimestral['fecha_subida'])); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="status-icons">
                                                <?php if ($hasTrimestral): ?>
                                                    <button class="view-file-btn" onclick="viewFileTrimestral('<?php echo $archivo_trimestral['ruta_archivo']; ?>', '<?php echo $name; ?> - Trimestral', <?php echo $archivo_trimestral['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-success btn-sm" onclick="downloadFile('<?php echo $archivo_trimestral['ruta_archivo']; ?>', '<?php echo $archivo_trimestral['nombre_archivo']; ?>')">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                    <button class="btn btn-primary btn-sm" onclick="openUploadModalTrimestral('<?php echo $id; ?>')">Reemplazar</button>
                                                <?php else: ?>
                                                    <button class="btn btn-primary btn-sm upload-btn" onclick="openUploadModalTrimestral('<?php echo $id; ?>')">Subir Reporte Trimestral</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Panel de administración para trimestrales -->
                    <div class="merge-section">
                        <h3>Consolidar Reportes Trimestrales</h3>
                        <div class="merge-action">
                            <button class="btn consolidate-btn" id="mergeBtnTrimestral" onclick="mergeFilesTrimestral()">
                                Consolidar Reportes Trimestrales
                            </button>
                            <div class="spinner" id="mergeSpinnerTrimestral"></div>
                        </div>
                        
                        <?php if (!empty($consolidados_trimestrales)): ?>
                            <div class="consolidados-list">
                                <h4>Archivos trimestrales consolidados recientes:</h4>
                                <ul>
                                    <?php foreach ($consolidados_trimestrales as $consolidado): ?>
                                        <li>
                                            <span class="file-name-truncate"><?php echo $consolidado['nombre_archivo']; ?></span> 
                                            (<?php echo date('d/m/Y H:i', strtotime($consolidado['fecha_creacion'])); ?>)
                                            <button class="btn btn-success" onclick="downloadFile('<?php echo $consolidado['ruta_archivo']; ?>', '<?php echo $consolidado['nombre_archivo']; ?>')">
                                                <i class="fas fa-download"></i> Descargar
                                            </button>
                                            <button class="btn btn-outline" onclick="viewFile('<?php echo $consolidado['ruta_archivo']; ?>', 'Consolidado Trimestral')">
                                                <i class="fas fa-eye"></i> Vista previa
                                            </button>
                                            <button class="btn btn-primary" onclick="sendConsolidatedTrimestral(<?php echo $consolidado['id']; ?>)">
                                                <i class="fas fa-paper-plane"></i> Enviar
                                            </button>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Gestión de destinatarios para trimestral - ESTILO UNIFICADO -->
                    <div class="destinatarios-container">
                        <h3>Gestión de Destinatarios</h3>
                        <p>Lista de correos a los que se enviarán los concentrados trimestrales</p>
                        
                        <!-- Formulario para agregar destinatario -->
                        <form method="POST" action="" class="form-inline">
                            <input type="hidden" name="action_destinatarios" value="agregar">
                            <input type="email" name="email" class="form-control" placeholder="Correo electrónico" required>
                            <button type="submit" class="btn btn-primary">Agregar</button>
                        </form>
                        
                        <!-- Lista de destinatarios con checkboxes -->
                        <div class="destinatarios-list">
                            <?php if (!empty($destinatarios)): ?>
                                <form method="POST" action="" id="destinatariosFormTrimestral">
                                    <input type="hidden" name="action_destinatarios" value="actualizar_seleccion">
                                    <div class="destinatarios-header">
                                        <h4>Destinatarios Disponibles</h4>
                                        <div class="destinatarios-actions">
                                            <button type="button" class="btn btn-sm btn-outline" onclick="seleccionarTodos()">Seleccionar Todos</button>
                                            <button type="button" class="btn btn-sm btn-outline" onclick="deseleccionarTodos()">Limpiar Selección</button>
                                        </div>
                                    </div>
                                    
                                    <?php foreach ($destinatarios as $destinatario): ?>
                                        <div class="destinatario-item">
                                            <label class="destinatario-checkbox">
                                                <input type="checkbox" name="destinatarios_seleccionados[]" 
                                                       value="<?php echo $destinatario['id']; ?>" 
                                                       <?php echo $destinatario['activo'] ? 'checked' : ''; ?>>
                                                <span class="checkmark"></span>
                                            </label>
                                            <div class="destinatario-info">
                                                <div class="destinatario-email"><?php echo htmlspecialchars($destinatario['email']); ?></div>
                                                <div class="destinatario-status">
                                                    <span class="status-badge <?php echo $destinatario['activo'] ? 'status-active' : 'status-inactive'; ?>">
                                                        <?php echo $destinatario['activo'] ? 'Activo' : 'Inactivo'; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="destinatario-actions">
                                                <button type="button" class="btn-icon" onclick="editarDestinatario(<?php echo $destinatario['id']; ?>, '<?php echo $destinatario['email']; ?>')" title="Editar">
                                                    <i class="fas fa-edit" style="color: #521426;"></i>
                                                </button>
                                                <button type="button" class="btn-icon" onclick="eliminarDestinatario(<?php echo $destinatario['id']; ?>)" title="Eliminar">
                                                    <i class="fas fa-trash" style="color: #521426;"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <div class="destinatarios-footer">
                                        <button type="submit" class="btn btn-primary">Guardar Selección</button>
                                        <span class="selection-info" id="selectionInfoTrimestral">0 seleccionados</span>
                                    </div>
                                </form>
                            <?php else: ?>
                                <p>No hay destinatarios registrados.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="history-section">
                        <h3>Historial de Reportes Trimestrales (Última Semana)</h3>
                        <div class="history-table-container">
                            <table class="history-table">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Escuela</th>
                                        <th class="desktop-only">Archivo</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($historial_trimestral as $reporte): ?>
                                        <tr>
                                            <td data-label="Fecha/Hora">
                                                <?php echo date('d/m/Y H:i', strtotime($reporte['fecha_subida'])); ?>
                                            </td>
                                            <td data-label="Escuela"><?php echo $reporte['escuela_nombre']; ?></td>
                                            <td class="desktop-only" data-label="Archivo"><span class="file-name-truncate"><?php echo $reporte['nombre_archivo']; ?></span></td>
                                            <td data-label="Acciones" style="white-space: nowrap;">
                                                <button class="btn-icon" onclick="viewFileTrimestral('<?php echo $reporte['ruta_archivo']; ?>', '<?php echo $reporte['escuela_nombre']; ?>', <?php echo $reporte['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-icon btn-success-icon" onclick="downloadFile('<?php echo $reporte['ruta_archivo']; ?>', '<?php echo $reporte['nombre_archivo']; ?>')">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                                <?php if ($_SESSION['user']['rol'] === 'admin'): ?>
                                                    <button class="btn-icon" onclick="deleteFileTrimestral(<?php echo $reporte['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Vista Escuela para reportes trimestrales -->
                    <?php 
                    $hasTrimestral = !empty($archivos_trimestrales);
                    $archivo_trimestral = $hasTrimestral ? $archivos_trimestrales[0] : null;
                    ?>
                    <div class="school-view-container">
                        <div class="card school-view-card" id="card-trimestral-<?php echo $schoolId; ?>">
                            <div class="card-header"><?php echo $schoolName; ?> - Reporte Trimestral</div>
                            <div class="card-body">
                                <div class="school-info">
                                    <div class="school-icon"><i class="fas fa-chart-bar"></i></div>
                                    <div class="school-details">
                                        <h3><?php echo $schoolId; ?></h3>
                                        <p>Reporte trimestral</p>
                                    </div>
                                </div>
                                
                                <div class="turno-status">
                                    <div class="upload-status" id="status-trimestral-<?php echo $schoolId; ?>">
                                        <div class="status-container">
                                            <span class="status <?php echo $hasTrimestral ? 'status-completed' : 'status-pending'; ?>">
                                                <?php echo $hasTrimestral ? 'Completado' : 'Pendiente'; ?>
                                            </span>
                                            <?php if ($hasTrimestral): ?>
                                                <span class="file-date"><?php echo date('d/m/Y', strtotime($archivo_trimestral['fecha_subida'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="status-icons">
                                            <?php if ($hasTrimestral): ?>
                                                <button class="view-file-btn" onclick="viewFileTrimestral('<?php echo $archivo_trimestral['ruta_archivo']; ?>', 'Reporte Trimestral', <?php echo $archivo_trimestral['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-success btn-sm" onclick="downloadFile('<?php echo $archivo_trimestral['ruta_archivo']; ?>', '<?php echo $archivo_trimestral['nombre_archivo']; ?>')">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                                <button class="btn btn-primary btn-sm" onclick="openUploadModalTrimestral('<?php echo $schoolId; ?>')">Reemplazar</button>
                                            <?php else: ?>
                                                <button class="btn btn-primary btn-sm upload-btn" onclick="openUploadModalTrimestral('<?php echo $schoolId; ?>')">Subir Reporte Trimestral</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- CTZ Tab -->
            <div id="ctz" class="tab-content">
                <div class="hero ctz-hero">
                    <h2>Consejos Técnicos Escolares</h2>
                    <p>Información y recursos para los consejos técnicos escolares</p>
                </div>
                
                <div class="ctz-section">
                    <div class="ctz-content">
                        <h3>Próximas Sesiones</h3>
                        <div class="ctz-item">
                            <div class="ctz-fecha">
                                <div class="ctz-dia">26</div>
                                <div class="ctz-mes">Sep</div>
                            </div>
                            <div class="ctz-info">
                                <h4>Primer CTE</h4>
                                <p class="ctz-horario">08:00 AM - 12:00 PM | En cada Escuela.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($_SESSION['user']['rol'] === 'admin'): ?>
                    <!-- Documento CTZ para descargar (Admin) -->
                    <div class="ctz-documents">
                        <h3>Estadística CTE</h3>
                        <p>Archivo disponible para que las escuelas descarguen y contesten</p>
                        
                        <div class="document-upload-section">
                            <?php if ($archivo_ctz): ?>
                                <div class="upload-status">
                                    <div class="status-container">
                                        <span class="status status-completed">Documento disponible</span>
                                        <span class="file-date"><?php echo date('d/m/Y', strtotime($archivo_ctz['fecha_subida'])); ?></span>
                                    </div>
                                    <div class="status-icons">
                                        <button class="btn btn-success" onclick="downloadFile('<?php echo $archivo_ctz['ruta_archivo']; ?>', '<?php echo $archivo_ctz['nombre_archivo']; ?>')">
                                            <i class="fas fa-download"></i> Descargar
                                        </button>
                                        <button class="btn btn-primary" onclick="openUploadModalCTZ()">
                                            <i class="fas fa-upload"></i> Reemplazar
                                        </button>
                                        <button class="btn btn-danger" onclick="deleteFileCTZ(<?php echo $archivo_ctz['id']; ?>)">
                                            <i class="fas fa-trash"></i> Eliminar
                                        </button>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="upload-status">
                                    <div class="status-container">
                                        <span class="status status-pending">No hay documento disponible</span>
                                    </div>
                                    <div class="status-icons">
                                        <button class="btn btn-primary" onclick="openUploadModalCTZ()">
                                            <i class="fas fa-upload"></i> Subir Documento
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Vista Admin para CTZ -->
                    <div class="dashboard" id="ctzDashboard">
                        <?php
                        foreach ($schools as $id => $name): 
                            $hasCTZ = false;
                            foreach ($archivos_ctz_escuelas as $archivo) {
                                if ($archivo['escuela_id'] == $id) {
                                    $hasCTZ = true;
                                    $archivo_ctz_escuela = $archivo;
                                    break;
                                }
                            }
                        ?>
                            <div class="card" id="card-ctz-<?php echo $id; ?>">
                                <div class="card-header"><?php echo $name; ?> - CTE</div>
                                <div class="card-body">
                                    <div class="school-info">
                                        <div class="school-icon"><i class="fas fa-file-alt"></i></div>
                                        <div class="school-details">
                                            <h3><?php echo $id; ?></h3>
                                            <p>Documento CTE contestado</p>
                                        </div>
                                    </div>
                                    
                                    <div class="turno-status">
                                        <div class="upload-status" id="status-ctz-<?php echo $id; ?>">
                                            <div class="status-container">
                                                <span class="status <?php echo $hasCTZ ? 'status-completed' : 'status-pending'; ?>">
                                                    <?php echo $hasCTZ ? 'Completado' : 'Pendiente'; ?>
                                                </span>
                                                <?php if ($hasCTZ): ?>
                                                    <span class="file-date"><?php echo date('d/m/Y', strtotime($archivo_ctz_escuela['fecha_subida'])); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="status-icons">
                                                <?php if ($hasCTZ): ?>
                                                    <button class="view-file-btn" onclick="viewFileCTZ('<?php echo $archivo_ctz_escuela['ruta_archivo']; ?>', '<?php echo $name; ?> - CTZ', <?php echo $archivo_ctz_escuela['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-success btn-sm" onclick="downloadFile('<?php echo $archivo_ctz_escuela['ruta_archivo']; ?>', '<?php echo $archivo_ctz_escuela['nombre_archivo']; ?>')">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                    <button class="btn btn-primary btn-sm" onclick="openUploadModalCTZEscuela('<?php echo $id; ?>')">Reemplazar</button>
                                                <?php else: ?>
                                                    <button class="btn btn-primary btn-sm upload-btn" onclick="openUploadModalCTZEscuela('<?php echo $id; ?>')">Subir CTE Contestado</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Panel de administración para CTZ -->
                    <div class="merge-section">
                        <h3>Consolidar Documentos CTE</h3>
                        <div class="merge-action">
                            <button class="btn consolidate-btn" id="mergeBtnCTZ" onclick="mergeFilesCTZ()">
                                Consolidar Documentos CTE
                            </button>
                            <div class="spinner" id="mergeSpinnerCTZ"></div>
                        </div>
                        
                        <?php if (!empty($consolidados_ctz)): ?>
                            <div class="consolidados-list">
                                <h4>Documentos CTE consolidados recientes:</h4>
                                <ul>
                                    <?php foreach ($consolidados_ctz as $consolidado): ?>
                                        <li>
                                            <span class="file-name-truncate"><?php echo $consolidado['nombre_archivo']; ?></span> 
                                            (<?php echo date('d/m/Y H:i', strtotime($consolidado['fecha_creacion'])); ?>)
                                            <button class="btn btn-success" onclick="downloadFile('<?php echo $consolidado['ruta_archivo']; ?>', '<?php echo $consolidado['nombre_archivo']; ?>')">
                                                <i class="fas fa-download"></i> Descargar
                                            </button>
                                            <button class="btn btn-outline" onclick="viewFile('<?php echo $consolidado['ruta_archivo']; ?>', 'Consolidado CTZ')">
                                                <i class="fas fa-eye"></i> Vista previa
                                            </button>
                                            <button class="btn btn-primary" onclick="sendConsolidatedCTZ(<?php echo $consolidado['id']; ?>)">
                                                <i class="fas fa-paper-plane"></i> Enviar
                                            </button>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Gestión de destinatarios para CTZ - ESTILO UNIFICADO -->
                    <div class="destinatarios-container">
                        <h3>Gestión de Destinatarios</h3>
                        <p>Lista de correos a los que se enviarán los documentos CTE</p>
                        
                        <!-- Formulario para agregar destinatario -->
                        <form method="POST" action="" class="form-inline">
                            <input type="hidden" name="action_destinatarios" value="agregar">
                            <input type="email" name="email" class="form-control" placeholder="Correo electrónico" required>
                            <button type="submit" class="btn btn-primary">Agregar</button>
                        </form>
                        
                        <!-- Lista de destinatarios con checkboxes -->
                        <div class="destinatarios-list">
                            <?php if (!empty($destinatarios)): ?>
                                <form method="POST" action="" id="destinatariosFormCTZ">
                                    <input type="hidden" name="action_destinatarios" value="actualizar_seleccion">
                                    <div class="destinatarios-header">
                                        <h4>Destinatarios Disponibles</h4>
                                        <div class="destinatarios-actions">
                                            <button type="button" class="btn btn-sm btn-outline" onclick="seleccionarTodos()">Seleccionar Todos</button>
                                            <button type="button" class="btn btn-sm btn-outline" onclick="deseleccionarTodos()">Limpiar Selección</button>
                                        </div>
                                    </div>
                                    
                                    <?php foreach ($destinatarios as $destinatario): ?>
                                        <div class="destinatario-item">
                                            <label class="destinatario-checkbox">
                                                <input type="checkbox" name="destinatarios_seleccionados[]" 
                                                       value="<?php echo $destinatario['id']; ?>" 
                                                       <?php echo $destinatario['activo'] ? 'checked' : ''; ?>>
                                                <span class="checkmark"></span>
                                            </label>
                                            <div class="destinatario-info">
                                                <div class="destinatario-email"><?php echo htmlspecialchars($destinatario['email']); ?></div>
                                                <div class="destinatario-status">
                                                    <span class="status-badge <?php echo $destinatario['activo'] ? 'status-active' : 'status-inactive'; ?>">
                                                        <?php echo $destinatario['activo'] ? 'Activo' : 'Inactivo'; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="destinatario-actions">
                                                <button type="button" class="btn-icon" onclick="editarDestinatario(<?php echo $destinatario['id']; ?>, '<?php echo $destinatario['email']; ?>')" title="Editar">
                                                    <i class="fas fa-edit" style="color: #521426;"></i>
                                                </button>
                                                <button type="button" class="btn-icon" onclick="eliminarDestinatario(<?php echo $destinatario['id']; ?>)" title="Eliminar">
                                                    <i class="fas fa-trash" style="color: #521426;"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <div class="destinatarios-footer">
                                        <button type="submit" class="btn btn-primary">Guardar Selección</button>
                                        <span class="selection-info" id="selectionInfoCTZ">0 seleccionados</span>
                                    </div>
                                </form>
                            <?php else: ?>
                                <p>No hay destinatarios registrados.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="history-section">
                        <h3>Historial de Documentos CTE (Última Semana)</h3>
                        <div class="history-table-container">
                            <table class="history-table">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Escuela</th>
                                        <th class="desktop-only">Archivo</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($historial_ctz as $reporte): ?>
                                        <tr>
                                            <td data-label="Fecha/Hora">
                                                <?php echo date('d/m/Y H:i', strtotime($reporte['fecha_subida'])); ?>
                                            </td>
                                            <td data-label="Escuela"><?php echo $reporte['escuela_nombre']; ?></td>
                                            <td class="desktop-only" data-label="Archivo"><span class="file-name-truncate"><?php echo $reporte['nombre_archivo']; ?></span></td>
                                            <td data-label="Acciones" style="white-space: nowrap;">
                                                <button class="btn-icon" onclick="viewFileCTZ('<?php echo $reporte['ruta_archivo']; ?>', '<?php echo $reporte['escuela_nombre']; ?>', <?php echo $reporte['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-icon btn-success-icon" onclick="downloadFile('<?php echo $reporte['ruta_archivo']; ?>', '<?php echo $reporte['nombre_archivo']; ?>')">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                                <?php if ($_SESSION['user']['rol'] === 'admin'): ?>
                                                    <button class="btn-icon" onclick="deleteFileCTZEscuela(<?php echo $reporte['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Vista Escuela para CTZ -->
                    <div class="ctz-documents">
                        <h3>Documento CTE</h3>
                        <p>Descargue el documento, contéstelo y súbalo nuevamente</p>
                        
                        <?php 
                        // USAR la variable global que ya obtuvimos antes
                        $archivo_ctz_maestro = $archivo_ctz_maestro_global;
                        ?>
                        
                        <?php if ($archivo_ctz_maestro): ?>
                            <div class="upload-status">
                                <div class="status-container">
                                    <span class="status status-completed">Documento disponible</span>
                                    <span class="file-date"><?php echo date('d/m/Y', strtotime($archivo_ctz_maestro['fecha_subida'])); ?></span>
                                </div>
                                <div class="status-icons">
                                    <button class="btn btn-success" onclick="downloadFile('<?php echo $archivo_ctz_maestro['ruta_archivo']; ?>', '<?php echo $archivo_ctz_maestro['nombre_archivo']; ?>')">
                                        <i class="fas fa-download"></i> Descargar Documento
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="upload-status">
                                <div class="status-container">
                                    <span class="status status-pending">No hay documento disponible</span>
                                </div>
                                <div class="status-icons">
                                    <span class="status-info">Esperando que el administrador suba el documento</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php 
                    $hasCTZ = !empty($archivos_ctz_escuelas);
                    $archivo_ctz_escuela = $hasCTZ ? $archivos_ctz_escuelas[0] : null;
                    ?>
                    <div class="school-view-container">
                        <div class="card school-view-card" id="card-ctz-<?php echo $schoolId; ?>">
                            <div class="card-header"><?php echo $schoolName; ?> - CTZ Contestado</div>
                            <div class="card-body">
                                <div class="school-info">
                                    <div class="school-icon"><i class="fas fa-file-alt"></i></div>
                                    <div class="school-details">
                                        <h3><?php echo $schoolId; ?></h3>
                                        <p>Documento CTE contestado</p>
                                    </div>
                                </div>
                                
                                <div class="turno-status">
                                    <div class="upload-status" id="status-ctz-<?php echo $schoolId; ?>">
                                        <div class="status-container">
                                            <span class="status <?php echo $hasCTZ ? 'status-completed' : 'status-pending'; ?>">
                                                <?php echo $hasCTZ ? 'Completado' : 'Pendiente'; ?>
                                            </span>
                                            <?php if ($hasCTZ): ?>
                                                <span class="file-date"><?php echo date('d/m/Y', strtotime($archivo_ctz_escuela['fecha_subida'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="status-icons">
                                            <?php if ($hasCTZ): ?>
                                                <button class="view-file-btn" onclick="viewFileCTZ('<?php echo $archivo_ctz_escuela['ruta_archivo']; ?>', 'CTZ Contestado', <?php echo $archivo_ctz_escuela['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-success btn-sm" onclick="downloadFile('<?php echo $archivo_ctz_escuela['ruta_archivo']; ?>', '<?php echo $archivo_ctz_escuela['nombre_archivo']; ?>')">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                                <button class="btn btn-primary btn-sm" onclick="openUploadModalCTZEscuela('<?php echo $schoolId; ?>')">Reemplazar</button>
                                            <?php else: ?>
                                                <button class="btn btn-primary btn-sm upload-btn" onclick="openUploadModalCTZEscuela('<?php echo $schoolId; ?>')">Subir CTE Contestado</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            
            <!-- Repositorio Tab -->
            <div id="repositorio" class="tab-content">
                <div class="hero repositorio-hero">
                    <h2>Repositorio de Documentos</h2>
                    <p>Documentos y recursos disponibles para la zona escolar</p>
                </div>
                <div class="repository-section">
                    <div class="repository-grid">
                        <div class="resource-card">
                            <div class="resource-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <h3 class="resource-title">Formatos Oficiales</h3>
                            <p class="resource-description">Documentos oficiales para uso de la Zona X.</p>
                            <button class="btn btn-primary" onclick="window.open('https://drive.google.com/drive/folders/1bkQ6LIfvC6fSNmJssYcK_ZIyotR7gfUE?dmr=1&ec=wgc-drive-hero-goto')">Ver Más</button>
                        </div>
                        <div class="resource-card">
                            <div class="resource-icon">
                                <i class="fas fa-camera"></i>
                            </div>
                            <h3 class="resource-title">Evidencias</h3>
                            <p class="resource-description">Una carpeta por escuela.</p>
                            <button class="btn btn-primary" onclick="window.open('https://drive.google.com/drive/folders/1mwXPRyxYvAbbgC8_TowrcG4nLiiwX8nD?dmr=1&ec=wgc-drive-hero-goto')">Ver Más</button>
                        </div>
                        <div class="resource-card">
                            <div class="resource-icon">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <h3 class="resource-title">Calendario SEC</h3>
                            <p class="resource-description">Ciclo Escolar 2025-2026.</p>
                            <button class="btn btn-primary" onclick="window.open('https://educacion.sonora.gob.mx/media/attachments/2025/07/22/calendario-escolar-2025-2026-web.pdf')">Descargar</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Upload Modal -->
    <div class="modal" id="uploadModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="uploadModalTitle">Subir Reporte</h3>
                <button type="button" class="btn-icon close-modal" onclick="closeUploadModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="uploadForm" method="POST" action="" enctype="multipart/form-data" onsubmit="handleUploadSubmit(event)">
                <input type="hidden" id="schoolIdInput" name="schoolId" value="<?php echo isset($_SESSION['user']) ? $_SESSION['user']['escuela_id'] : ''; ?>">
                <input type="hidden" id="turnoInput" name="turno" value="">
                <div class="form-group modal-form-group">
                    <label for="excelFile">Seleccione archivo Excel</label>
                    <input type="file" id="excelFile" name="excelFile" accept=".xlsx, .xls" required>
                    <p class="file-type-warning">Solo se permiten archivos .xlsx or .xls</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeUploadModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="uploadSubmitBtn">Subir Archivo</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Upload Modal CTZ -->
    <div class="modal" id="uploadModalCTZ">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Subir Documento CTE</h3>
                <button type="button" class="btn-icon close-modal" onclick="closeUploadModalCTZ()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_ctz">
                <div class="form-group modal-form-group">
                    <label for="ctzFile">Seleccione archivo Excel</label>
                    <input type="file" id="ctzFile" name="ctzFile" accept=".xlsx, .xls" required>
                    <p class="file-type-warning">Solo se permiten archivos .xlsx or .xls</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeUploadModalCTZ()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Subir Documento</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Upload Modal CTZ Escuela -->
    <div class="modal" id="uploadModalCTZEscuela">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="uploadModalCTZTitle">Subir CTE Contestado</h3>
                <button type="button" class="btn-icon close-modal" onclick="closeUploadModalCTZEscuela()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_ctz_escuela">
                <input type="hidden" id="ctzSchoolIdInput" name="schoolId" value="">
                <div class="form-group modal-form-group">
                    <label for="ctzFileEscuela">Seleccione archivo Excel contestado</label>
                    <input type="file" id="ctzFileEscuela" name="ctzFile" accept=".xlsx, .xls" required>
                    <p class="file-type-warning">Solo se permiten archivos .xlsx or .xls</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeUploadModalCTZEscuela()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Subir Documento</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View File Modal -->
    <div class="modal" id="viewFileModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="viewFileModalTitle">Documento Subido</h3>
                <button type="button" class="btn-icon close-modal" onclick="closeViewFileModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="fileViewContent">
                    <p>Aquí se mostrará el documento subido.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeViewFileModal()">Cerrar</button>
                <button type="button" class="btn btn-success" onclick="downloadCurrentFile()">Descargar Archivo</button>
                <button type="button" class="btn btn-danger" id="deleteFileBtn" style="display:none;" onclick="deleteCurrentFile()">
                    <i class="fas fa-trash"></i> Eliminar Archivo
                </button>
            </div>
        </div>
    </div>

    <!-- Edit Destinatario Modal -->
    <div class="modal" id="editDestinatarioModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Editar Destinatario</h3>
                <button type="button" class="btn-icon close-modal" onclick="closeEditDestinatarioModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="" id="editDestinatarioForm">
                <input type="hidden" name="action_destinatarios" value="editar">
                <input type="hidden" name="id" id="editDestinatarioId" value="">
                <div class="form-group">
                    <label for="editEmail">Correo electrónico</label>
                    <input type="email" id="editEmail" name="email" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeEditDestinatarioModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h4>Secretaría de Educación y Cultura del Estado de Sonora.</h4>
                <p>Dirección de Educación de Secundarias Técnicas.</p>
                <p>Hermosillo, Sonora, México</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2025 Supervisión Zona X - Todos los Derechos Reservados.</p>
        </div>
    </footer>

    <script src="script.js?v=<?php echo time(); ?>"></script>
</body>
</html>