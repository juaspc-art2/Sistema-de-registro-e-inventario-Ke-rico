<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crud php y MySQL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/estilo.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/98eb9eed10.js" crossorigin="anonymous"></script>
</head>

<body>
    <script>
        function eliminar() {
            var respuesta = confirm("¿Está seguro de querer eliminar el registro?");
            return respuesta
        }
    </script>

    <h1 class="text-center p-3">Ke-rico</h1>

    <?php
    include "modelo/conexion.php";
    include "controlador/eliminar_persona.php";
    ?>

    <div class="container-fluid row">
        <form class="col-4 p-3" method="POST">
            <h3 class="text-center text-secondary">Registro de personas</h3>

            <?php
            include "controlador/registro_persona.php";
            ?>

            <div class="mb-3">
                <label for="exampleInputEmail1" class="form-label">Nombre:</label>
                <input type="text" class="form-control" name="nombre">
            </div>

            <div class="mb-3">
                <label for="exampleInputEmail1" class="form-label">Apellido:</label>
                <input type="text" class="form-control" name="apellido">
            </div>

            <div class="mb-3">
                <label for="exampleInputEmail1" class="form-label">Documento:</label>
                <input type="text" class="form-control" name="documento">
            </div>

            <div class="mb-3">
                <label for="exampleInputEmail1" class="form-label">Fecha de nacimiento:</label>
                <input type="date" class="form-control" name="fecha">
            </div>

            <div class="mb-3">
                <label for="exampleInputEmail1" class="form-label">Correo:</label>
                <input type="text" class="form-control" name="correo">
            </div>

            <div class="mb-3">
                <label for="exampleInputEmail1" class="form-label">Cargo:</label>
                <input type="text" class="form-control" name="cargo">
            </div>

            <button type="submit" class="btn btn-primary" name="btnregistrar" value="ok">
                Registrar
            </button>
        </form>

        <div class="col-8 p-4">
            <table class="table">
                <thead class="bg-info">
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Nombre</th>
                        <th scope="col">Apellido</th>
                        <th scope="col">Documento</th>
                        <th scope="col">Fecha de nacimiento</th>
                        <th scope="col">Correo</th>
                        <th scope="col">Cargo</th>
                        <th scope="col"></th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $sql = $conexion->query("select * from tb_persona");

                    while ($datos = $sql->fetch_object()) { ?>
                        <tr>
                            <td><?= $datos->id ?></td>
                            <td><?= $datos->nombre ?></td>
                            <td><?= $datos->Apellido ?></td>
                            <td><?= $datos->documento ?></td>
                            <td><?= $datos->fecha_nac ?></td>
                            <td><?= $datos->correo ?></td>
                            <td><?= $datos->cargo ?></td>
                            <td>
                                <a href="modificar_persona.php?id=<?= $datos->id ?>" class="btn btn-small btn-warning">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>

                                <a onclick="return eliminar()" href="index.php?id=<?= $datos->id ?>" class="btn btn-small btn-danger">
                                    <i class="fa-solid fa-trash-can"></i>
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>