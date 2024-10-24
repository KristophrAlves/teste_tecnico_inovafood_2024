<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Content-Type: application/json; charset=UTF-8");

require_once 'db/connect.php'; // Incluindo a conexão com o banco

// Chamada da função com o caminho do arquivo DOCX
if (isset($_FILES['file']) && $_FILES['file']['type'] === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
    $fileTmpPath = $_FILES['file']['tmp_name'];
    $fileName = $_FILES['file']['name'];
    
    // Mover o arquivo para uma pasta temporária
    $uploadFileDir = './uploads/';
    if (!is_dir($uploadFileDir)) {
        mkdir($uploadFileDir, 0777, true);
    }

    $dest_path = $uploadFileDir . uniqid() . '-' . basename($fileName);
    
    // Mover o arquivo
    if (move_uploaded_file($fileTmpPath, $dest_path)) {
        // Chamar o arquivo readFile.php para processar o DOCX
        include 'readFile.php';
        processDocx($dest_path, $conexao); // Passando a conexão como argumento
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao mover o arquivo para o diretório de uploads.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Arquivo inválido. Por favor, envie um arquivo DOCX.']);
}

// Fechar conexão se necessário
$conexao->close();
