<?php

$servername = "sql100.infinityfree.com";
$username = "if0_39926607";
$password = "Bltj8s30sxrlli";
$dbname = "if0_39926607_asistencia_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Configurar charset para evitar problemas con caracteres especiales
$conn->set_charset("utf8mb4");

$usuarios = array(
    array(
        'email' => 'supervision@zonax.com',
        'password' => 'admin123',
        'nombre' => 'Supervisora',
        'rol' => 'admin',
        'escuela_id' => NULL
    ),
    array(
        'email' => 'st06@zonax.com',
        'password' => 'admin',  // Cambiado de 'sec6' a 'admin'
        'nombre' => 'E.S.T. #6',
        'rol' => 'school',
        'escuela_id' => '26DST0006K'
    ),
    array(
        'email' => 'st60@zonax.com',
        'password' => 'admin',
        'nombre' => 'E.S.T. #60',
        'rol' => 'school',
        'escuela_id' => '26DST0060K'
    ),
    array(
        'email' => 'st72@zonax.com',
        'password' => 'admin',
        'nombre' => 'E.S.T. #72',
        'rol' => 'school',
        'escuela_id' => '26DST0072K'
    )
);

echo "<h2>Actualizando contraseñas...</h2>";

// Primero, eliminar el usuario duplicado st06@zonax.com si existe
$delete_duplicate = $conn->prepare("DELETE FROM usuarios WHERE email = ?");
$delete_email = "st06@zonax.com";
$delete_duplicate->bind_param("s", $delete_email);
$delete_duplicate->execute();
$delete_duplicate->close();

foreach ($usuarios as $usuario) {
    $password_hash = password_hash($usuario['password'], PASSWORD_DEFAULT);
    
    // Usar consultas preparadas para evitar inyección SQL
    $sql_check = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
    $sql_check->bind_param("s", $usuario['email']);
    $sql_check->execute();
    $result = $sql_check->get_result();
    
    if ($result->num_rows > 0) {
        // UPDATE con consulta preparada
        $sql = $conn->prepare("UPDATE usuarios SET 
                password = ?, 
                nombre = ?, 
                rol = ?, 
                escuela_id = ? 
                WHERE email = ?");
        
        $sql->bind_param("sssss", 
            $password_hash, 
            $usuario['nombre'], 
            $usuario['rol'], 
            $usuario['escuela_id'], 
            $usuario['email']
        );
    } else {
        // INSERT con consulta preparada
        $sql = $conn->prepare("INSERT INTO usuarios (email, password, nombre, rol, escuela_id) 
                VALUES (?, ?, ?, ?, ?)");
        
        $sql->bind_param("sssss", 
            $usuario['email'], 
            $password_hash, 
            $usuario['nombre'], 
            $usuario['rol'], 
            $usuario['escuela_id']
        );
    }
    
    if ($sql->execute()) {
        echo "<p>✓ Usuario <strong>" . $usuario['email'] . "</strong> actualizado correctamente</p>";
        echo "<p>Contraseña: <strong>" . $usuario['password'] . "</strong></p>";
        echo "<p>Hash generado: " . $password_hash . "</p><hr>";
    } else {
        echo "<p style='color: red;'>✗ Error al actualizar " . $usuario['email'] . ": " . $conn->error . "</p>";
    }
    
    // Cerrar statement
    if (isset($sql)) {
        $sql->close();
    }
    $sql_check->close();
}

echo "<h2>Proceso completado.</h2>";
echo "<p>Ahora todas las escuelas tienen la contraseña: <strong>admin</strong></p>";
echo "<p>Supervisión tiene la contraseña: <strong>admin123</strong></p>";
echo "<p><strong>IMPORTANTE:</strong> Elimina este archivo del servidor por seguridad.</p>";

$conn->close();
?>