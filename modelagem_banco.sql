CREATE TABLE produto (
    id_produto SERIAL PRIMARY KEY,
    nome VARCHAR(100),
    descricao TEXT,
    preco NUMERIC(10,2),
    estoque INTEGER
);
CREATE TABLE pedidos (
    id_pedido SERIAL PRIMARY KEY,
    id_produto INTEGER REFERENCES produto(id_produto),
    quantidade INTEGER,
    data_pedido DATE
);
CREATE TABLE estoque (
    id_estoque SERIAL PRIMARY KEY,
    id_produto INTEGER REFERENCES produto(id_produto),
    tipo_movimentacao VARCHAR(10), -- 'entrada' ou 'saida'
    quantidade INTEGER,
    data_movimentacao DATE
);
CREATE TABLE condicional (
    id_condicional SERIAL PRIMARY KEY,
    id_produto INTEGER REFERENCES produto(id_produto),
    nome_cliente VARCHAR(100),
    quantidade INTEGER,
    data_envio DATE,
    status VARCHAR(20) -- 'pendente', 'devolvido', 'vendido'
);

INSERT INTO produto (nome, descricao, preco, estoque) VALUES
('Vestido Floral', 'Vestido de verão com estampa floral.', 129.90, 10),
('Camisa Social Masculina', 'Camisa manga longa branca.', 89.90, 25),
('Calça Jeans Feminina', 'Calça jeans cintura alta.', 149.00, 15);
INSERT INTO pedidos (id_produto, quantidade, data_pedido) VALUES
(1, 2, '2025-05-01'),
(2, 1, '2025-05-02'),
(3, 3, '2025-05-03');
INSERT INTO estoque (id_produto, tipo_movimentacao, quantidade, data_movimentacao) VALUES
(1, 'entrada', 10, '2025-04-28'),
(2, 'saida', 5, '2025-05-01'),
(3, 'entrada', 15, '2025-05-02');
INSERT INTO condicional (id_produto, nome_cliente, quantidade, data_envio, status) VALUES
(1, 'Maria Souza', 2, '2025-05-03', 'pendente'),
(2, 'Loja XYZ', 5, '2025-05-04', 'vendido'),
(3, 'João Oliveira', 1, '2025-05-05', 'devolvido');
