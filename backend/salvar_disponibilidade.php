<?php
session_start();
require 'conexao.php'; // Inclui a conexão PDO

// 1. Verificação de Acesso e Segurança
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'dentista') {
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Apenas aceita requisições POST do formulário
    header('Location: ../admin/dashboard-dentista.php');
    exit;
}

$dentista_id = $_SESSION['usuario_id'];
$horarios_selecionados = $_POST['horarios'] ?? []; // Pega o array de horários ou um array vazio

try {
    // Inicia uma transação para garantir que ambas as operações sejam concluídas com sucesso
    $pdo->beginTransaction();

    // 2. Deletar horários existentes
    // É mais fácil e seguro apagar todos e reinserir os novos
    $sql_delete = "DELETE FROM Disponibilidade_Dentista WHERE usuario_id = :dentista_id";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->execute([':dentista_id' => $dentista_id]);

    // 3. Inserir novos horários
    if (!empty($horarios_selecionados)) {
        
        $sql_insert = "INSERT INTO Disponibilidade_Dentista (usuario_id, dia_semana, horario) VALUES (:dentista_id, :dia, :hora)";
        $stmt_insert = $pdo->prepare($sql_insert);

        foreach ($horarios_selecionados as $dia_semana => $horas_do_dia) {
            
            // Certifica-se que $horas_do_dia é um array, mesmo que vazio
            if (!is_array($horas_do_dia)) continue;

            foreach ($horas_do_dia as $horario) {
                // Validação básica para evitar injeção SQL no loop
                if (strlen($dia_semana) > 10 || strlen($horario) > 5) continue; 
                
                $stmt_insert->execute([
                    ':dentista_id' => $dentista_id,
                    ':dia' => $dia_semana,
                    ':hora' => $horario // O banco deve aceitar a string 'HH:MI'
                ]);
            }
        }
    }

    // 4. Finalizar Transação e Redirecionar
    $pdo->commit();
    $_SESSION['mensagem_sucesso'] = "Sua disponibilidade foi atualizada com sucesso!";
    header('Location: ../admin/dashboard-dentista.php?sucesso=true');
    exit();

} catch (PDOException $e) {
    // 5. Tratar Erro
    $pdo->rollBack(); // Desfaz todas as alterações
    $_SESSION['mensagem_erro'] = "Erro ao salvar disponibilidade: " . $e->getMessage();
    // Em desenvolvimento, é útil ver a mensagem: die($e->getMessage());
    header('Location: ../admin/dashboard-dentista.php?erro=true');
    exit();
}
?>