// Arquivo: backend/get_dentista.php

<?php
header('Content-Type: application/json');
require_once "conexao.php"; // Verifique se este caminho está correto (ex: ./conexao.php ou ../conexao.php)

if (!isset($_GET['categoria_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Categoria ID é obrigatório.']);
    exit();
}

$categoria_id = filter_var($_GET['categoria_id'], FILTER_VALIDATE_INT);

try {
    // NOVA LÓGICA: Liga Categoria -> Servico -> Consulta_servico -> Consulta -> Dentista.
    // Isso encontra um dentista que já realizou um serviço dessa categoria.
    $sql = "
        SELECT DISTINCT
            u.usuario_id, 
            u.nome,
            d.cro
        FROM 
            Servico s
        JOIN
            Consulta_servico cs ON s.servico_id = cs.servico_id
        JOIN
            Consulta c ON cs.consulta_id = c.consulta_id
        JOIN
            Dentista d ON c.usuario_dentista = d.usuario_id
        JOIN
            Usuario u ON d.usuario_id = u.usuario_id
        WHERE 
            s.categoria_id = :categoria_id
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
        echo json_encode([
            'error' => 'Nenhum dentista encontrado.',
            'message' => 'Nenhum profissional está associado a esta categoria, ou não há agendamentos anteriores para esta categoria no banco de dados.'
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro interno do servidor (PDO).', 
        'detail' => 'Verifique se as tabelas Servico, Consulta_servico, Consulta, Dentista e Usuario estão preenchidas e se o arquivo de conexão está correto.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro inesperado: ' . $e->getMessage()]);
}
?>