<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Content-Type: application/json; charset=UTF-8");

require_once 'vendor/autoload.php'; // Certifique-se de que isso está correto
require_once 'db/connect.php'; // Incluindo a conexão com o banco

use PhpOffice\PhpWord\IOFactory;

function processDocx($filePath, $conexao)
{
    try {
        if (!file_exists($filePath)) {
            throw new Exception('Arquivo não encontrado: ' . $filePath);
        }

        $phpWord = IOFactory::load($filePath);
        $stmt = $conexao->prepare("INSERT INTO checklists (title) VALUES (?)");
        $stmt->bind_param("s", $title);
        $title = 'Checklist Importado';
        $stmt->execute();
        $checklistId = $conexao->insert_id;

        $questionCount = 0;

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                // Verifica se o elemento é uma tabela
                if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
                    foreach ($element->getRows() as $row) {
                        $questionTitle = '';
                        $alternatives = [];
                        $cellCount = 0;

                        foreach ($row->getCells() as $cell) {
                            $cellText = '';
                            foreach ($cell->getElements() as $cellElement) {
                                if (method_exists($cellElement, 'getText')) {
                                    $cellText .= $cellElement->getText() . ' ';
                                }
                            }

                            $cellText = trim($cellText);

                            if ($cellCount == 0) {
                                $questionTitle = $cellText;
                            } else {
                                if (!empty($cellText)) {
                                    $alternatives[] = $cellText;
                                }
                            }
                            $cellCount++;
                        }

                        if (!empty($questionTitle) && preg_match("/(\d+\s*[-.]\s*[^?]*[\?]?)/", $questionTitle)) {
                             $stmt = $conexao->prepare("INSERT INTO questions (title, id_checklist) VALUES (?, ?)");
                            $stmt->bind_param("si", $questionTitle, $checklistId);
                            $stmt->execute();
                            $questionId = $conexao->insert_id;
                            $questionCount++;

                            foreach ($alternatives as $alt) {
                                if (!empty($alt)) {
                                    $stmt = $conexao->prepare("INSERT INTO alternatives (title, id_question) VALUES (?, ?)");
                                    $stmt->bind_param("si", $alt, $questionId);
                                    $stmt->execute();
                                }
                            }
                        } else {
                            error_log("Título da pergunta inválido ou não segue o padrão: " . $questionTitle);
                        }
                    }
                } else {
                    error_log("Elemento encontrado não é uma tabela: " . get_class($element));
                }
            }
        }


        $response = [
            'status' => 'success',
            'message' => 'Processamento concluído com sucesso',
            'questions_inserted' => $questionCount
        ];

        echo json_encode($response);
    } catch (\PhpOffice\PhpWord\Exception\ExceptionInterface $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao processar o arquivo: ' . $e->getMessage()]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro: ' . $e->getMessage()]);
        exit;
    }
}

// Lógica para o upload de arquivo...
