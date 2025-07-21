<?php
// process/entidades/inativar_entidade.php

require_once('../../conexao.php');
require_once('../../includes/error_handler.php');
session_start();
header('Content-Type: application/json');

// Validações
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit(); }
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) { http_response_code(403); exit(); }

$response = ['success' => false, 'message' => 'ID não fornecido.'];

if (isset($_POST['ent_codigo'])) {
    $ent_codigo = $_POST['ent_codigo'];

    try {
        // Em vez de DELETAR, nós ATUALIZAMOS a situação para 'I' (Inativo)
        $stmt = $pdo->prepare("UPDATE tbl_entidades SET ent_situacao = 'I' WHERE ent_codigo = :ent_codigo");
        $stmt->execute([':ent_codigo' => $ent_codigo]);

        if ($stmt->rowCount() > 0) {
            $response['success'] = true;
            $response['message'] = 'Registro inativado com sucesso!';
        } else {
            $response['message'] = 'Nenhum registro encontrado com o ID fornecido.';
        }
    } catch (PDOException $e) {
        http_response_code(500);
        $response['message'] = 'Erro no servidor ao inativar o registro.';
        error_log("Erro ao inativar entidade: " . $e->getMessage());
    }
}

echo json_encode($response);