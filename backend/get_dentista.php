// Arquivo: backend/get_dentista.php (FINAL E CORRIGIDO)

<?php
header('Content-Type: application/json');

// IMPORTANTE: Use o caminho de conexão que funciona no seu servidor!
require_once "conexao.php"; 

if (!isset($_GET['categoria_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Categoria ID é obrigatório.']);
    exit();
}

$categoria_id = filter_var($_GET['categoria_id'], FILTER_VALIDATE_INT);

try {
    // LÓGICA CORRETA: Junta Categoria diretamente com Dentista e Usuario.
    // Presume-se que a coluna na tabela Categoria é 'dentista_id'.
    $sql = "
        SELECT 
            u.usuario_id, 
            u.nome,
            d.cro
        FROM 
            Categoria c
        JOIN
            Dentista d ON c.dentista_id = d.usuario_id -- JUNÇÃO DIRETA CORRIGIDA
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
        // Sucesso: retorna o dentista para o JS
        echo json_encode($dentista);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Nenhum dentista encontrado para esta categoria. Verifique se a coluna dentista_id está preenchida na tabela Categoria.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    // Se este erro ainda aparecer, o problema é no require_once ou nas credenciais.
    echo json_encode(['error' => 'Erro fatal de Conexão/SQL. Detalhe: ' . $e->getMessage()]);
}
?>