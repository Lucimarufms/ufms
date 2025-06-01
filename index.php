<?php
// index.php
require_once 'conexao.php'; // Inclui o arquivo de conexão com o banco de dados

$produtoSelecionado = null;
$mensagem = '';
$mostrarFormularioCadastro = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'buscar') {
        $nomeBusca = trim($_POST['nome_produto'] ?? '');
        if ($nomeBusca !== '') {
            $stmt = $pdo->prepare("SELECT * FROM produto WHERE LOWER(nome) = LOWER(?)");
            $stmt->execute([$nomeBusca]);
            $produtoSelecionado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($produtoSelecionado) {
                $mensagem = "Produto encontrado. Você pode abastecer o estoque.";
                $mostrarFormularioCadastro = false; // Garante que o form de cadastro esteja escondido
            } else {
                $mensagem = "Produto não encontrado. Preencha os dados abaixo para cadastrar um novo produto.";
                $mostrarFormularioCadastro = true; // Abre o formulário de cadastro
            }
        }
    } elseif ($acao === 'abastecer') {
        $idProduto = intval($_POST['id'] ?? 0);
        $qtdEntrada = intval($_POST['qtd_entrada'] ?? 0);

        if ($idProduto > 0 && $qtdEntrada > 0) {
            try {
                $pdo->beginTransaction();

                // Atualiza o estoque na tabela produto
                $stmt = $pdo->prepare("UPDATE produto SET estoque = estoque + ? WHERE id_produto = ?");
                $stmt->execute([$qtdEntrada, $idProduto]);

                // Opcional: Registrar a movimentação na tabela 'estoque' para histórico
                $stmt_mov = $pdo->prepare("INSERT INTO estoque (id_produto, tipo_movimentacao, quantidade, data_movimentacao) VALUES (?, 'entrada', ?, CURRENT_DATE)");
                $stmt_mov->execute([$idProduto, $qtdEntrada]);

                $pdo->commit();

                // Recarrega o produto para mostrar o estoque atualizado
                $stmt = $pdo->prepare("SELECT * FROM produto WHERE id_produto = ?");
                $stmt->execute([$idProduto]);
                $produtoSelecionado = $stmt->fetch(PDO::FETCH_ASSOC);

                $mensagem = "Estoque atualizado com sucesso!";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $mensagem = "Erro ao abastecer estoque: " . $e->getMessage();
            }
        } else {
            $mensagem = "Quantidade a adicionar inválida ou produto não selecionado.";
        }
    } elseif ($acao === 'cadastrar') {
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $preco = floatval($_POST['preco'] ?? 0);
        $estoqueInicial = intval($_POST['estoque'] ?? 0);

        // Processar upload da imagem
        $imagemNome = 'imagens/produto-default.jpg'; // default caso não envie
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['imagem']['tmp_name'];
            $extensao = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
            $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($extensao, $extensoesPermitidas)) {
                if (!is_dir('imagens')) {
                    mkdir('imagens', 0755, true); // Cria recursivamente
                }
                $imagemNome = 'imagens/' . uniqid('prod_') . '.' . $extensao;
                move_uploaded_file($tmpName, $imagemNome);
            } else {
                $mensagem = "Formato de imagem não permitido. Use jpg, jpeg, png ou gif.";
            }
        }

        if ($nome && $descricao && $preco > 0 && $estoqueInicial > 0 && $mensagem === '') {
            try {
                // Verifica se já existe um produto com o mesmo nome
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM produto WHERE LOWER(nome) = LOWER(?)");
                $stmt_check->execute([$nome]);
                if ($stmt_check->fetchColumn() > 0) {
                    $mensagem = "Produto com este nome já existe. Se deseja atualizar, use a busca.";
                    $mostrarFormularioCadastro = true; // Mantém o formulário de cadastro aberto
                } else {
                    $stmt = $pdo->prepare("INSERT INTO produto (nome, descricao, preco, estoque) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$nome, $descricao, $preco, $estoqueInicial]);

                    // Recupera o ID do produto recém-inserido para associar à imagem
                    $lastId = $pdo->lastInsertId('produto_id_produto_seq'); // Para PostgreSQL
                    // Se fosse MySQL: $lastId = $pdo->lastInsertId();

                    // Atualiza o caminho da imagem no banco de dados se uma imagem foi enviada
                    if ($imagemNome !== 'imagens/produto-default.jpg') {
                        $novoCaminhoImagem = 'imagens/prod_' . $lastId . '.' . $extensao;
                        rename($imagemNome, $novoCaminhoImagem); // Renomeia o arquivo
                        $stmt_img = $pdo->prepare("UPDATE produto SET imagem = ? WHERE id_produto = ?");
                        $stmt_img->execute([$novoCaminhoImagem, $lastId]);
                    } else {
                        // Se não foi enviada imagem, define a imagem padrão no banco
                        $stmt_img = $pdo->prepare("UPDATE produto SET imagem = ? WHERE id_produto = ?");
                        $stmt_img->execute(['imagens/produto-default.jpg', $lastId]);
                    }

                    // Recarrega o produto para mostrar o recém-cadastrado
                    $stmt = $pdo->prepare("SELECT * FROM produto WHERE id_produto = ?");
                    $stmt->execute([$lastId]);
                    $produtoSelecionado = $stmt->fetch(PDO::FETCH_ASSOC);

                    $mensagem = "Produto cadastrado com sucesso!";
                    $mostrarFormularioCadastro = false; // Esconde o formulário após o cadastro
                }
            } catch (PDOException $e) {
                $mensagem = "Erro ao cadastrar produto: " . $e->getMessage();
                $mostrarFormularioCadastro = true; // Mantém o formulário de cadastro aberto em caso de erro
            }
        } elseif ($mensagem === '') {
            $mensagem = "Por favor, preencha todos os campos obrigatórios corretamente.";
            $mostrarFormularioCadastro = true; // Mantém o formulário de cadastro aberto se os dados estiverem incompletos
        }
    }
}

// Lista todos os produtos cadastrados para exibir na parte inferior
$produtosCadastrados = [];
try {
    $stmt = $pdo->query("SELECT * FROM produto ORDER BY nome ASC");
    $produtosCadastrados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensagem = "Erro ao carregar produtos: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Controle de Estoque - Estilo Chic</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Estilo para garantir que o formulário de cadastro se ajuste corretamente ao ser exibido/ocultado */
    #form-cadastro-novo {
      display: <?= $mostrarFormularioCadastro ? 'block' : 'none'; ?>;
    }
  </style>
</head>
<body class="bg-gray-50 font-sans text-gray-900 p-8">

  <h1 class="text-4xl font-bold text-indigo-600 mb-8 text-center">Controle de Estoque - Estilo Chic</h1>

  <?php if ($mensagem): ?>
    <div class="max-w-3xl mx-auto mb-6 p-4 bg-<?= strpos($mensagem, 'sucesso') !== false ? 'green' : 'red' ?>-100 text-<?= strpos($mensagem, 'sucesso') !== false ? 'green' : 'red' ?>-700 rounded">
      <?= htmlspecialchars($mensagem) ?>
    </div>
  <?php endif; ?>

  <form method="POST" class="max-w-3xl mx-auto mb-8 flex gap-4 items-center" enctype="multipart/form-data">
    <input
      type="text" name="nome_produto" placeholder="Digite o nome do produto"
      class="flex-grow border border-gray-300 p-3 rounded focus:outline-none focus:ring-2 focus:ring-indigo-400"
      required
      value="<?= isset($_POST['nome_produto']) ? htmlspecialchars($_POST['nome_produto']) : '' ?>"
      aria-label="Nome do produto para busca"
    />
    <input type="hidden" name="acao" value="buscar" />
    <button type="submit" class="bg-indigo-600 text-white px-6 py-3 rounded hover:bg-indigo-700 transition">
      Buscar Produto
    </button>
  </form>

  <?php if ($produtoSelecionado): ?>
    <section class="max-w-3xl mx-auto bg-white p-6 rounded-lg shadow-md mb-8">
      <h2 class="text-2xl font-semibold mb-4 text-indigo-600">Produto Encontrado:</h2>
      <div class="mb-4 flex flex-col md:flex-row gap-6 items-center">
        <img src="<?= htmlspecialchars($produtoSelecionado['imagem'] ?? 'imagens/produto-default.jpg') ?>" alt="Imagem do produto" class="w-32 h-32 object-cover rounded border" />
        <div>
          <p><strong>Nome:</strong> <?= htmlspecialchars($produtoSelecionado['nome']) ?></p>
          <p><strong>Descrição:</strong> <?= nl2br(htmlspecialchars($produtoSelecionado['descricao'])) ?></p>
          <p><strong>Preço:</strong> R$ <?= number_format($produtoSelecionado['preco'], 2, ',', '.') ?></p>
          <p><strong>Estoque Atual:</strong> <?= intval($produtoSelecionado['estoque']) ?></p>
        </div>
      </div>
      <form method="POST" class="flex flex-col sm:flex-row gap-4 items-center">
        <input type="number" name="qtd_entrada" min="1" placeholder="Quantidade a adicionar" required
          class="border border-gray-300 p-2 rounded w-full sm:w-40 focus:outline-none focus:ring-2 focus:ring-green-400" />
        <input type="hidden" name="id" value="<?= htmlspecialchars($produtoSelecionado['id_produto']) ?>" />
        <input type="hidden" name="acao" value="abastecer" />
        <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition w-full sm:w-auto">
          Abastecer Estoque
        </button>
      </form>
    </section>
  <?php endif; ?>

  <section id="form-cadastro-novo" class="max-w-3xl mx-auto bg-white p-6 rounded-lg shadow-md mb-8">
    <h2 class="text-2xl font-semibold mb-4 text-indigo-600 text-center">Cadastrar Novo Produto</h2>
    <form method="POST" class="space-y-6" enctype="multipart/form-data" novalidate>
      <div>
        <label for="nome" class="block font-medium mb-1">Nome do Produto</label>
        <input
          type="text" id="nome" name="nome" required
          class="w-full border border-gray-300 p-3 rounded focus:outline-none focus:ring-2 focus:ring-indigo-400"
          value="<?= ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['acao'] === 'buscar' && !$produtoSelecionado) ? htmlspecialchars($_POST['nome_produto']) : '' ?>"
        />
      </div>
      <div>
        <label for="descricao" class="block font-medium mb-1">Descrição</label>
        <textarea
          id="descricao" name="descricao" rows="4" required
          class="w-full border border-gray-300 p-3 rounded focus:outline-none focus:ring-2 focus:ring-indigo-400"
          placeholder="Descreva o produto"
        ></textarea>
      </div>
      <div>
        <label for="preco" class="block font-medium mb-1">Preço (R$)</label>
        <input
          type="number" id="preco" name="preco" step="0.01" min="0" required
          class="w-full border border-gray-300 p-3 rounded focus:outline-none focus:ring-2 focus:ring-indigo-400"
        />
      </div>
      <div>
        <label for="estoque" class="block font-medium mb-1">Estoque Inicial</label>
        <input
          type="number" id="estoque" name="estoque" min="1" required
          class="w-full border border-gray-300 p-3 rounded focus:outline-none focus:ring-2 focus:ring-indigo-400"
          value="1"
        />
      </div>
      <div>
        <label for="imagem" class="block font-medium mb-1">Imagem do Produto</label>
        <input type="file" id="imagem" name="imagem" accept="image/*"
          class="w-full border border-gray-300 p-2 rounded focus:outline-none focus:ring-2 focus:ring-indigo-400"
        />
      </div>
      <input type="hidden" name="acao" value="cadastrar" />
      <div class="text-center">
        <button type="submit" class="bg-indigo-600 text-white px-8 py-3 rounded hover:bg-indigo-700 transition">
          Cadastrar Produto
        </button>
      </div>
    </form>
  </section>

  <section class="max-w-5xl mx-auto mt-12">
    <h2 class="text-2xl font-semibold mb-4 text-indigo-600 text-center">Todos os Produtos Cadastrados</h2>
    <?php if (count($produtosCadastrados) > 0): ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        <?php foreach ($produtosCadastrados as $p): ?>
          <div class="bg-white p-4 rounded shadow hover:shadow-lg transition border border-gray-200">
            <img src="<?= htmlspecialchars($p['imagem'] ?? 'imagens/produto-default.jpg') ?>" alt="Imagem do produto" class="w-full h-40 object-cover rounded mb-2 border" />
            <h3 class="font-bold text-lg text-indigo-700 mb-1"><?= htmlspecialchars($p['nome']) ?></h3>
            <p class="text-gray-700 text-sm mb-2 h-16 overflow-hidden"><?= nl2br(htmlspecialchars($p['descricao'])) ?></p>
            <p class="text-green-700 font-semibold mb-1">R$ <?= number_format($p['preco'], 2, ',', '.') ?></p>
            <p class="text-gray-600 text-sm">Estoque: <?= intval($p['estoque']) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="text-center text-gray-500">Nenhum produto cadastrado ainda.</p>
    <?php endif; ?>
  </section>

</body>
</html>