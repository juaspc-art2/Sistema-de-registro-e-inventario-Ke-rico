<?php
if (!empty($_POST["btnregistrar"])) {
    if (
        !empty($_POST["nombre"]) and
        !empty($_POST["apellido"]) and
        !empty($_POST["documento"]) and
        !empty($_POST["fecha"]) and
        !empty($_POST["correo"]) and
        !empty($_POST["cargo"])
    ) {
        $nombre = $_POST["nombre"];
        $apellido = $_POST["apellido"];
        $documento = $_POST["documento"];
        $fecha = $_POST["fecha"];
        $correo = $_POST["correo"];
        $cargo = $_POST["cargo"];

        $sql = $conexion->query(
            "INSERT INTO tb_persona
            (nombre, apellido, documento, fecha_nac, correo, cargo)
            VALUES
            ('$nombre', '$apellido', '$documento', '$fecha', '$correo', '$cargo')"
        );

        if ($sql == 1) {
            echo '<div class="alert alert-success">Persona registrada correctamente</div>';
        } else {
            echo '<div class="alert alert-danger">Error al registrar persona</div>';
        }
    } else {
        echo '<div class="alert alert-warning">Uno o más campos están vacíos</div>';
    }
}
?>