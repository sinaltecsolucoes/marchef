<?php
@session_start();
session_destroy();
if ($_SESSION['tipoUsuario'] != 'Admin') {
    echo "<script language='javascript'>
        window.location='../' </script>";
    exit();
}
?>