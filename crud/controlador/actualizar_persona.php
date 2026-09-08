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
        $id = $_POST["id"];
        $nombre = $_POST["nombre"];
        $apellido = $_POST["apellido"];
        $documento = $_POST["documento"];
        $fecha = $_POST["fecha"];
        $correo = $_POST["correo"];
        $cargo = $_POST["cargo"];

        $sql = $conexion->query(
            "UPDATE tb_persona SET
            nombre='$nombre',
            apellido='$apellido',
            documento='$documento',
            fecha_nac='$fecha',
            correo='$correo',
            cargo='$cargo'
            WHERE id=$id"
        );

        if ($sql == 1) {
            header("location:index.php");
        } else {
            echo "<div class='alert alert-danger'>Error al modificar Usuario</div>";
        }
    } else {
        echo "<div class='alert alert-warning'>campos vacios</div>";
    }
}
?>