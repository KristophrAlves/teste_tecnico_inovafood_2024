<?php
// require_once('../config/config.php');

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = 'root'; 
$DB_NAME = 'avaliacao_db';

try {
    //CONECTA
    $conexao = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS);

    //SELECIONA O BANCO DE DADOS 
    $banco = mysqli_select_db($conexao, $DB_NAME);

    //mysqli_set_charset($conexao,'utf8');

} catch (Exception $e) {
    echo 'Erro: ',  $e->getMessage(), "\n";
}
