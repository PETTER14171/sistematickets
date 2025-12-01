<?php

require 'app.php';

function incluirTemplate(string $nombre, array $datos = []) {
    if (!empty($datos)) {
        extract($datos, EXTR_SKIP);
    }

    include __DIR__ . "/templates/{$nombre}.php";
}


function estaAutenticado() : bool {
    session_start();
    $auth = $_SESSION['login'];
    if($auth){
        return true;
    }
    return false;
};