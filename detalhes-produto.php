<?php
// detalhes_produto.php
require_once 'conexao.php';

$produto = null;
if (isset($_GET['id'])) {
    $id_produto = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM produto WHERE id_produto = ?");
    $stmt->execute([$id_produto]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Estilo Chic - Detalhes do Produto</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans">

  <header class="bg-white shadow-md p-6 text-center">
    <h1 class="text-4xl font-bold text-indigo-600">Estilo Chic</h1>
    <p class="text-gray-600 mt-2">Moda acessível e cheia de estilo</p>
  </header>

  <section class="p-8 max-w-7xl mx-auto bg-white shadow-md rounded-lg mt-16">
    <?php if ($produto): ?>
    <div class="grid md:grid-cols-2 gap-12">

      <div class="flex justify-center">
        <img
          src="<?= htmlspecialchars($produto['imagem'] ?? 'imagens/produto-default.jpg') ?>"
          alt="<?= htmlspecialchars($produto['nome']) ?>"
          class="w-full h-auto rounded-lg shadow-lg object-cover max-h-[400px]"
          loading="lazy"
        />
      </div>

      <div>
        <h2 class="text-3xl font-semibold mb-4"><?= htmlspecialchars($produto['nome']) ?></h2>
        <p class="text-gray-600 mb-6">
          <?= nl2br(htmlspecialchars($produto['descricao'])) ?>
        </p>

        <div class="mb-6">
          <span class="text-lg font-semibold text-indigo-600">Preço: R$ <?= number_format($produto['preco'], 2, ',', '.') ?></span>
        </div>

        <div class="mb-6">
          <span class="text-lg font-semibold">Quantidade em Estoque: <?= intval($produto['estoque']) ?> unidades</span>
        </div>

        <div class="text-center mt-8">
          <a href="index.php" class="bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
            Voltar ao Controle de Estoque
          </a>
        </div>
      </div>
    </div>
    <?php else: ?>
    <p class="text-center text-red-500 text-xl">Produto não encontrado.</p>
    <div class="text-center mt-8">
      <a href="index.php" class="bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
        Voltar ao Controle de Estoque
      </a>
    </div>
    <?php endif; ?>
  </section>

  <section class="p-8 max-w-6xl mx-auto bg-white shadow-md rounded-lg mt-12">
    <h2 class="text-2xl font-semibold mb-6 text-center">Outros Produtos</h2>
    <div class="grid md:grid-cols-3 sm:grid-cols-2 gap-6">
      <?php
      // Exemplo de como listar outros produtos (poderia ser por categoria, etc.)
      $stmt_outros = $pdo->query("SELECT * FROM produto ORDER BY nome ASC LIMIT 3");
      while ($outro_produto = $stmt_outros->fetch(PDO::FETCH_ASSOC)):
      ?>
      <article
        class="cursor-pointer bg-white rounded-lg shadow hover:shadow-lg transition"
        onclick="window.location.href='detalhes_produto.php?id=<?= $outro_produto['id_produto'] ?>'"
        role="button"
        tabindex="0"
        onkeydown="if(event.key==='Enter' || event.key===' ') window.location.href='detalhes_produto.php?id=<?= $outro_produto['id_produto'] ?>'"
        aria-label="Visualizar <?= htmlspecialchars($outro_produto['nome']) ?>"
      >
        <img src="<?= htmlspecialchars($outro_produto['imagem'] ?? 'imagens/produto-default.jpg') ?>" alt="<?= htmlspecialchars($outro_produto['nome']) ?>" class="rounded-t-lg w-full h-60 object-cover" />
        <div class="p-4">
          <h3 class="font-semibold text-lg"><?= htmlspecialchars($outro_produto['nome']) ?></h3>
        </div>
      </article>
      <?php endwhile; ?>
    </div>
  </section>

</body>
</html>