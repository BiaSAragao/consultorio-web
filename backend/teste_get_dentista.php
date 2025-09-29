// Arquivo: backend/teste_conexao_dentista.php

<?php
// ATIVE O RELATÓRIO DE ERROS PARA VER O ERRO FATAL
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// --- PONTO CRÍTICO: USE O require_once CORRETO ---
// 1. Se conexao.php estiver no mesmo diretório /backend:
require_once __DIR__ . "/conexao.php"; 
// 2. Se conexao.php estiver no diretório RAIZ:
// require_once dirname(__DIR__) . "/conexao.php"; 


// Dados de teste (use um ID de categoria que você sabe que existe)
$categoria_id = 1; 

try {
    // Mesma lógica de busca do get_dentista.php
    $sql = "
        SELECT 
            u.usuario_id, 
            u.nome,
            d.cro
        FROM 
            Categoria c
        JOIN
            Dentista d ON c.dentista_id = d.usuario_id
        JOIN
            Usuario u ON d.usuario_id = u.usuario_id
        WHERE 
            c.categoria_id = :categoria_id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':categoria_id', $categoria_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $dentista = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($dentista) {
        // Sucesso: Retorna o JSON
        echo json_encode(['status' => 'sucesso', 'dentista' => $dentista]);
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'erro', 'message' => 'Nenhum dentista encontrado na busca SQL.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'erro PDO', 'message' => 'Erro SQL/Conexão: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'erro PHP', 'message' => 'Erro geral: ' . $e->getMessage()]);
}
?>