// Arquivo: backend/get_dentista.php

<?php
header('Content-Type: application/json');

require_once "conexao.php"; // Presumindo que este é o caminho correto para a conexão

if (!isset($_GET['categoria_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Categoria ID é obrigatório.']);
    exit();
}

$categoria_id = filter_var($_GET['categoria_id'], FILTER_VALIDATE_INT);

try {
    // Busca o primeiro dentista (LIMIT 1) associado à categoria
    $sql = "
        SELECT 
            u.usuario_id, 
            u.nome,
            d.cro
        FROM 
            Usuario u
        JOIN 
            Dentista d ON u.usuario_id = d.usuario_id
        JOIN
            Categoria_Dentista cd ON d.usuario_id = cd.dentista_id
        WHERE 
            cd.categoria_id = :categoria_id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':categoria_id', $categoria_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $dentista = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($dentista) {
        echo json_encode($dentista);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Nenhum dentista encontrado para esta categoria.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor: ' . $e->getMessage()]);
}
?>