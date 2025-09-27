<?php
session_start();
require 'conexao.php'; // Inclui sua conexão com o banco

// --- VALIDAÇÃO DE SEGURANÇA ---

// 1. Garante que um usuário (paciente ou dentista) esteja logado
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['admin_id'])) {
    die("Acesso negado. Você precisa estar logado.");
}

// 2. Pega o ID do arquivo da URL e valida
$id_laudo = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_laudo) {
    die("Arquivo não especificado ou inválido.");
}

try {
    // --- LÓGICA DE BANCO DE DADOS ---
    /*
     * Substitua 'laudos', 'id_laudo', 'id_paciente', 'nome_arquivo_servidor', e 'nome_arquivo_original'
     * pelos nomes corretos da sua tabela e colunas no banco de dados.
    */
    $stmt = $pdo->prepare(
        "SELECT id_paciente, nome_arquivo_servidor, nome_arquivo_original FROM laudos WHERE id_laudo = ?"
    );
    $stmt->execute([$id_laudo]);
    $arquivo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$arquivo) {
        die("Arquivo não encontrado.");
    }

    // 3. VERIFICAÇÃO DE PERMISSÃO
    $permitido = false;
    // Se for um paciente logado, verifica se o laudo pertence a ele
    if (isset($_SESSION['usuario_id'])) {
        $id_paciente_logado = $_SESSION['usuario_id']; // Assumindo que o usuario_id é o mesmo que o id do paciente
        if ($arquivo['id_paciente'] == $id_paciente_logado) {
            $permitido = true;
        }
    }
    // Se for um dentista logado (consideramos que ele pode ver laudos dos pacientes)
    if (isset($_SESSION['admin_id'])) {
        $permitido = true; // Simplificação. Numa versão futura, você poderia verificar se o paciente pertence ao dentista.
    }

    if (!$permitido) {
        die("Você não tem permissão para baixar este arquivo.");
    }

    // --- ENTREGA DO ARQUIVO PARA DOWNLOAD ---
    $pasta_uploads = 'uploads/';
    $caminho_completo = $pasta_uploads . $arquivo['nome_arquivo_servidor'];

    if (!file_exists($caminho_completo)) {
        die("Erro: O arquivo físico não foi encontrado no servidor.");
    }

    // Limpa qualquer saída de buffer antes de enviar o arquivo
    ob_clean();
    flush();

    // Define os cabeçalhos para forçar o download
    header('Content-Type: application/octet-stream'); // Tipo genérico para forçar download
    header('Content-Disposition: attachment; filename="' . basename($arquivo['nome_arquivo_original']) . '"');
    header('Content-Length: ' . filesize($caminho_completo));
    header('Pragma: public');

    // Lê o arquivo e o envia para o navegador
    readfile($caminho_completo);
    exit();

} catch (PDOException $e) {
    die("Erro no banco de dados. Por favor, tente novamente mais tarde.");
}
?>