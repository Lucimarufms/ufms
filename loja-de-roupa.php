<?php
require_once 'conexao.php'; // Inclui o arquivo de conexão com o banco de dados

$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $preco = floatval($_POST['preco'] ?? 0);
    $quantidade = intval($_POST['quantidade'] ?? 0);

    // Processar upload da imagem
    $imagemNome = 'imagens/produto-default.jpg'; // default caso não envie
    $extensao = '';
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['imagem']['tmp_name'];
        $extensao = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
        $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($extensao, $extensoesPermitidas)) {
            $mensagem = "Formato de imagem não permitido. Use jpg, jpeg, png ou gif.";
        }
    }

    if ($nome && $quantidade >= 0 && $mensagem === '') { // Preço e descrição podem ser opcionais dependendo da regra de negócio
        try {
            $pdo->beginTransaction();

            // Verifica se o produto já existe pelo nome
            $stmt_check = $pdo->prepare("SELECT id_produto, estoque FROM produto WHERE LOWER(nome) = LOWER(?)");
            $stmt_check->execute([$nome]);
            $produto_existente = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($produto_existente) {
                // Produto existe, apenas atualiza a quantidade
                $novo_estoque = $produto_existente['estoque'] + $quantidade;
                $stmt = $pdo->prepare("UPDATE produto SET estoque = ? WHERE id_produto = ?");
                $stmt->execute([$novo_estoque, $produto_existente['id_produto']]);
                $mensagem = "Estoque do produto '" . htmlspecialchars($nome) . "' atualizado para " . $novo_estoque . " unidades.";

                // Opcional: Registrar a movimentação de entrada na tabela 'estoque'
                $stmt_mov = $pdo->prepare("INSERT INTO estoque (id_produto, tipo_movimentacao, quantidade, data_movimentacao) VALUES (?, 'entrada', ?, CURRENT_DATE)");
                $stmt_mov->execute([$produto_existente['id_produto'], $quantidade]);

            } else {
                // Produto não existe, cadastra um novo
                $stmt = $pdo->prepare("INSERT INTO produto (nome, descricao, preco, estoque) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nome, $descricao, $preco, $quantidade]);
                $lastId = $pdo->lastInsertId('produto_id_produto_seq'); // Para PostgreSQL

                // Salva a imagem com o ID do produto
                if (isset($tmpName) && !empty($tmpName)) {
                    $novoCaminhoImagem = 'imagens/prod_' . $lastId . '.' . $extensao;
                    move_uploaded_file($tmpName, $novoCaminhoImagem);
                    $stmt_img = $pdo->prepare("UPDATE produto SET imagem = ? WHERE id_produto = ?");
                    $stmt_img->execute([$novoCaminhoImagem, $lastId]);
                } else {
                    // Se não foi enviada imagem, define a imagem padrão no banco
                    $stmt_img = $pdo->prepare("UPDATE produto SET imagem = ? WHERE id_produto = ?");
                    $stmt_img->execute(['imagens/produto-default.jpg', $lastId]);
                }
                $mensagem = "Produto '" . htmlspecialchars($nome) . "' cadastrado com sucesso com estoque inicial de " . $quantidade . " unidades.";
            }

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $mensagem = "Erro ao salvar produto: " . $e->getMessage();
        }
    } elseif ($mensagem === '') {
        $mensagem = "Por favor, preencha o nome e a quantidade do produto.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Estilo Chic - Controle de Estoque</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-900">
  <div class="container mx-auto px-4 py-8">

    <header class="mb-10 text-center">
      <h1 class="text-4xl font-bold text-pink-600">Estilo Chic</h1>
      <p class="text-lg mt-2 text-gray-600">Controle de Estoque da Loja de Roupas</p>
    </header>

    <?php if ($mensagem): ?>
      <div class="max-w-xl mx-auto mb-6 p-4 bg-<?= strpos($mensagem, 'sucesso') !== false ? 'green' : 'red' ?>-100 text-<?= strpos($mensagem, 'sucesso') !== false ? 'green' : 'red' ?>-700 rounded">
        <?= htmlspecialchars($mensagem) ?>
      </div>
    <?php endif; ?>

    <section class="max-w-xl mx-auto bg-white p-6 rounded-xl shadow-md mb-12">
      <h2 class="text-2xl font-semibold mb-4 text-center">Cadastrar ou Atualizar Produto</h2>
      <form action="loja_de_roupas.php" method="POST" enctype="multipart/form-data" class="space-y-4">
        <div>
          <label class="block mb-1">Nome do Produto</label>
          <input type="text" name="nome" required class="w-full border border-gray-300 p-2 rounded">
        </div>

        <div>
          <label class="block mb-1">Descrição</label>
          <textarea name="descricao" rows="2" class="w-full border border-gray-300 p-2 rounded"></textarea>
        </div>

        <div>
          <label class="block mb-1">Preço (R$)</label>
          <input type="number" step="0.01" name="preco" class="w-full border border-gray-300 p-2 rounded">
        </div>

        <div>
          <label class="block mb-1">Imagem (opcional)</label>
          <input type="file" name="imagem" accept="image/*" class="w-full">
        </div>

        <div>
          <label class="block mb-1">Quantidade (para adicionar ao estoque existente ou inicial)</label>
          <input type="number" name="quantidade" min="0" required class="w-full border border-gray-300 p-2 rounded">
        </div>

        <div class="text-center">
          <button type="submit" class="bg-pink-600 hover:bg-pink-700 text-white px-6 py-2 rounded">
            Salvar Produto
          </button>
        </div>
      </form>
    </section>

    <section class="bg-white p-6 rounded-xl shadow-md">
      <h2 class="text-2xl font-semibold mb-4 text-center">Produtos no Estoque</h2>
      <table class="w-full text-left border-collapse">
        <thead class="bg-pink-600 text-white">
          <tr>
            <th class="p-3">Imagem</th>
            <th class="p-3">Nome</th>
            <th class="p-3">Estoque</th>
            <th class="p-3">Preço</th>
            <th class="p-3">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php
          try {
              $stmt = $pdo->query("SELECT * FROM produto ORDER BY nome ASC");
              while ($produto = $stmt->fetch(PDO::FETCH_ASSOC)):
          ?>
          <tr class="border-b hover:bg-gray-100">
            <td class="p-3 text-center">
              <?php
              $imgPath = htmlspecialchars($produto['imagem'] ?? 'imagens/produto-default.jpg');
              // Verifica se o arquivo de imagem realmente existe no sistema de arquivos
              if (file_exists($imgPath) && !is_dir($imgPath)): ?>
                <img src="<?= $imgPath ?>" class="w-16 h-16 object-cover rounded" alt="<?= htmlspecialchars($produto['nome']) ?>">
              <?php else: ?>
                <img src="imagens/produto-default.jpg" class="w-16 h-16 object-cover rounded" alt="Imagem padrão">
              <?php endif; ?>
            </td>
            <td class="p-3"><?= htmlspecialchars($produto['nome']) ?></td>
            <td class="p-3"><?= $produto['estoque'] ?></td>
            <td class="p-3">R$ <?= number_format($produto['preco'], 2, ',', '.') ?></td>
            <td class="p-3">
                <a href="detalhes_produto.php?id=<?= $produto['id_produto'] ?>" class="text-blue-600 hover:underline">Ver Detalhes</a>
            </td>
          </tr>
          <?php
              endwhile;
          } catch (PDOException $e) {
              echo "<tr><td colspan='5' class='p-3 text-center text-red-500'>Erro ao carregar produtos: " . $e->getMessage() . "</td></tr>";
          }
          ?>
        </tbody>
      </table>
      <?php if (count($pdo->query("SELECT * FROM produto")->fetchAll()) == 0): ?>
          <p class="text-center text-gray-500 mt-4">Nenhum produto cadastrado ainda.</p>
      <?php endif; ?>
    </section>

    <div class="text-center mt-8">
      <a href="index.php" class="text-pink-600 hover:underline">← Voltar à página de controle de estoque</a>
    </div>

  </div>
</body>
</html>