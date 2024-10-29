<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'vendor/autoload.php'; // Carrega a biblioteca PhpWord
require_once 'db/connect.php'; // Inclui a conexão com o banco

use PhpOffice\PhpWord\IOFactory;

function extractTextFromCell($cell)
{
    $text = '';
    foreach ($cell->getElements() as $element) {
        if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
            foreach ($element->getElements() as $textElement) {
                if ($textElement instanceof \PhpOffice\PhpWord\Element\Text) {
                    $text .= $textElement->getText();
                }
            }
        }
    }
    return trim($text);
}

function cleanInput($input)
{
    return htmlspecialchars(strip_tags(trim($input)));
}

function processDocx($filePath, $conexao)
{
    $phpWord = IOFactory::load($filePath);

    $titles = [];
    $questions = [];
    $alternatives = [];

    foreach ($phpWord->getSections() as $section) {
        foreach ($section->getElements() as $element) {
            if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                foreach ($element->getElements() as $text) {
                    $cellText = cleanInput($text->getText());
                    if (!empty($cellText)) {
                        if (isTitle($cellText)) {
                            if (!in_array($cellText, $titles)) {
                                $titles[] = $cellText;
                            }
                        } else {
                            $questions[] = $cellText;
                        }
                    }
                }
            } elseif ($element instanceof \PhpOffice\PhpWord\Element\Table) {
                foreach ($element->getRows() as $row) {
                    foreach ($row->getCells() as $cell) {
                        $cellText = extractTextFromCell($cell);
                        // Limpeza e validação do texto
                        $cleanedText = preg_replace('/\]\=\>string\(\d+\)"[^"]*"\[\s*\d+\s*\]/', '', $cellText);
                        $cleanedText = preg_replace('/\b(Sim|Não|N\/A|Observação)\b/i', '', $cleanedText);
                        $cleanedText = preg_replace('/\?\s*$/', '', $cleanedText);
                        $cleanedText = trim($cleanedText);

                        // Verifica se o texto é válido e se não já está nos títulos
                        if (
                            !in_array($cleanedText, $titles) &&
                            !empty($cleanedText) &&
                            !preg_match('/^[\s]*[\d]+(\.\d*)?/', $cleanedText) &&
                            !preg_match('/^[\s]*\d+\-/', $cleanedText) &&
                            !preg_match("/(\d+\s*[-.]\s*[^?]*[\?]?)/", $cellText) &&
                            preg_match('/^[A-Z]/', $cleanedText) // Verifica se começa com letra maiúscula
                        ) {
                            $titles[] = $cleanedText;
                        }

                        // Armazena perguntas
                        if (!empty(trim($cellText)) && preg_match("/(\d+\s*[-.]\s*[^?]*[\?]?)/", $cellText)) {
                            $questions[] = cleanInput($cellText);
                        }

                        // Regex para identificar alternativas
                        if (
                            !empty(trim($cellText)) &&
                            !preg_match("/(\d+\s*[-.]\s*[^?]*[\?]?)/", $cellText)
                        ) {
                            $cleanedText = cleanInput($cellText);

                            // Regex para identificar alternativas
                            if (preg_match('/^(Sim|Não|N\/A|Observação)$/i', $cleanedText)) {
                                $alternatives[] = $cleanedText;
                            }
                        }
                    }
                }
            }
        }
    }

    // var_dump($alternatives);
    // exit();

    // Remover perguntas vazias
    $questions = array_filter($questions, fn($q) => !empty(trim($q)));

    if (empty($titles)) {
        echo json_encode(['status' => 'error', 'message' => 'O título não pode ser vazio']);
        exit;
    }

    try {

        $countTitles = 0;
        $countQuestions = 0;
        $countAlternatives = 0;

        foreach ($titles as $title) {
            $stmt = $conexao->prepare("INSERT INTO checklists (title) VALUES (?)");
            $stmt->bind_param("s", $title);
            $stmt->execute();

            $countTitles++;
            // Recupera o ID do checklist inserido
            $idChecklist = $conexao->insert_id;
        }
        // Insira as perguntas relacionadas a este checklist
        foreach ($questions as $question) {
            $stmt = $conexao->prepare("INSERT INTO questions (title, id_checklist) VALUES (?, ?)");
            $stmt->bind_param("si", $question, $idChecklist);
            $stmt->execute();

            $countQuestions++;
            // Recupera o ID da pergunta inserida
            $idQuestion = $conexao->insert_id;
        }
        // Insira as alternativas relacionadas a esta pergunta
        foreach ($alternatives as $alternative) {
            $stmt = $conexao->prepare("INSERT INTO alternatives (title, id_question) VALUES (?, ?)");
            $stmt->bind_param("si", $alternative, $idQuestion);
            $stmt->execute();

            $countAlternatives++;
        }

        echo json_encode([
            'status' => 'success',
            'titles' => $countTitles,
            'questions' => $countQuestions,
            'alternatives' => $countAlternatives,
        ], JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao inserir dados: ' . $e->getMessage()]);
        // Aqui você pode registrar o erro em um log, se necessário
    }
}

function isTitle($text)
{
    return (preg_match('/^[A-Z]/', $text) || str_word_count($text) > 3);
}

// Exemplo de chamada da função
// processDocx('caminho/para/seu/documento.docx', $conexao);
