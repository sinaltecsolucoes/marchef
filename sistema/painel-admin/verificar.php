<?php
@session_start();

if (@$_SESSION['tipoUsuario'] != 'Admin') {
    echo "<script language='javascript'>
        window.location='../' </script>";
    exit();
}
?>