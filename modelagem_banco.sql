CREATE TABLE produto (
    id_produto SERIAL PRIMARY KEY,
    nome VARCHAR(100) UNIQUE NOT NULL, -- Nome único para evitar duplicidade fácil
    descricao TEXT,
    preco NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    estoque INTEGER NOT NULL DEFAULT 0, -- estoque físico disponível
    imagem VARCHAR(255) DEFAULT 'imagens/produto-default.jpg' -- Caminho da imagem
);

CREATE TABLE pedidos (
    id_pedido SERIAL PRIMARY KEY,
    id_produto INTEGER NOT NULL REFERENCES produto(id_produto),
    quantidade INTEGER NOT NULL CHECK (quantidade > 0),
    data_pedido DATE NOT NULL DEFAULT CURRENT_DATE
);

CREATE TABLE estoque (
    id_estoque SERIAL PRIMARY KEY,
    id_produto INTEGER NOT NULL REFERENCES produto(id_produto),
    tipo_movimentacao VARCHAR(10) NOT NULL CHECK (tipo_movimentacao IN ('entrada', 'saida')),
    quantidade INTEGER NOT NULL CHECK (quantidade > 0),
    data_movimentacao DATE NOT NULL DEFAULT CURRENT_DATE,
    observacao TEXT
);

CREATE TABLE condicional (
    id_condicional SERIAL PRIMARY KEY,
    id_produto INTEGER NOT NULL REFERENCES produto(id_produto),
    nome_cliente VARCHAR(100) NOT NULL,
    quantidade INTEGER NOT NULL CHECK (quantidade > 0),
    data_envio DATE NOT NULL DEFAULT CURRENT_DATE,
    status VARCHAR(20) NOT NULL CHECK (status IN ('pendente', 'devolvido', 'vendido'))
);

-- Funções e Triggers para automatização do estoque

-- Função para atualizar estoque na tabela produto após movimentações de 'estoque'
CREATE OR REPLACE FUNCTION atualizar_estoque_produto_estoque()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        IF NEW.tipo_movimentacao = 'entrada' THEN
            UPDATE produto SET estoque = estoque + NEW.quantidade WHERE id_produto = NEW.id_produto;
        ELSIF NEW.tipo_movimentacao = 'saida' THEN
            UPDATE produto SET estoque = estoque - NEW.quantidade WHERE id_produto = NEW.id_produto;
        END IF;
    ELSIF TG_OP = 'UPDATE' THEN
        IF OLD.tipo_movimentacao = 'entrada' AND NEW.tipo_movimentacao = 'saida' THEN
            -- Se o tipo de movimentação mudou de entrada para saída, reverter entrada e aplicar saída
            UPDATE produto SET estoque = estoque - OLD.quantidade - NEW.quantidade WHERE id_produto = NEW.id_produto;
        ELSIF OLD.tipo_movimentacao = 'saida' AND NEW.tipo_movimentacao = 'entrada' THEN
            -- Se o tipo de movimentação mudou de saída para entrada, reverter saída e aplicar entrada
            UPDATE produto SET estoque = estoque + OLD.quantidade + NEW.quantidade WHERE id_produto = NEW.id_produto;
        ELSE -- O tipo de movimentação não mudou
            IF NEW.tipo_movimentacao = 'entrada' THEN
                UPDATE produto SET estoque = estoque - OLD.quantidade + NEW.quantidade WHERE id_produto = NEW.id_produto;
            ELSIF NEW.tipo_movimentacao = 'saida' THEN
                UPDATE produto SET estoque = estoque + OLD.quantidade - NEW.quantidade WHERE id_produto = NEW.id_produto;
            END IF;
        END IF;
    ELSIF TG_OP = 'DELETE' THEN
        IF OLD.tipo_movimentacao = 'entrada' THEN
            UPDATE produto SET estoque = estoque - OLD.quantidade WHERE id_produto = OLD.id_produto;
        ELSIF OLD.tipo_movimentacao = 'saida' THEN
            UPDATE produto SET estoque = estoque + OLD.quantidade WHERE id_produto = OLD.id_produto;
        END IF;
    END IF;
    RETURN NEW; -- Ou OLD para DELETE
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_atualizar_estoque_produto_estoque
AFTER INSERT OR UPDATE OR DELETE ON estoque
FOR EACH ROW
EXECUTE FUNCTION atualizar_estoque_produto_estoque();


-- Função para registrar movimentação de 'saída' e atualizar estoque após 'pedidos' (venda)
CREATE OR REPLACE FUNCTION registrar_saida_pedido()
RETURNS TRIGGER AS $$
BEGIN
    INSERT INTO estoque (id_produto, tipo_movimentacao, quantidade, data_movimentacao, observacao)
    VALUES (NEW.id_produto, 'saida', NEW.quantidade, NEW.data_pedido, 'Venda registrada no pedido ' || NEW.id_pedido);
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_registrar_saida_pedido
AFTER INSERT ON pedidos
FOR EACH ROW
EXECUTE FUNCTION registrar_saida_pedido();


-- Função para registrar movimentação de 'saída' no estoque ao enviar para 'condicional' (status pendente)
CREATE OR REPLACE FUNCTION registrar_saida_condicional_envio()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.status = 'pendente' THEN
        INSERT INTO estoque (id_produto, tipo_movimentacao, quantidade, data_movimentacao, observacao)
        VALUES (NEW.id_produto, 'saida', NEW.quantidade, NEW.data_envio, 'Consignação para cliente ' || NEW.nome_cliente || ' (status pendente)');
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_registrar_saida_condicional_envio
AFTER INSERT ON condicional
FOR EACH ROW
EXECUTE FUNCTION registrar_saida_condicional_envio();


-- Função para ajustar estoque e movimentação de estoque ao mudar status da 'condicional'
CREATE OR REPLACE FUNCTION ajustar_estoque_condicional_status()
RETURNS TRIGGER AS $$
BEGIN
    IF OLD.status = 'pendente' AND NEW.status = 'devolvido' THEN
        -- Se foi devolvido, a quantidade volta para o estoque (movimentação de entrada)
        INSERT INTO estoque (id_produto, tipo_movimentacao, quantidade, data_movimentacao, observacao)
        VALUES (NEW.id_produto, 'entrada', NEW.quantidade, CURRENT_DATE, 'Devolução de consignação do cliente ' || NEW.nome_cliente);
    ELSIF OLD.status = 'pendente' AND NEW.status = 'vendido' THEN
        -- Se foi vendido, a saída já foi registrada no envio. Não precisa fazer nada aqui.
        -- Poderíamos registrar uma 'saída' específica de venda condicional se quiséssemos um histórico diferente,
        -- mas a movimentação original 'saída' já descontou do estoque.
        -- Para evitar dupla contagem, não fazemos nada no estoque aqui.
        -- Apenas para clareza, pode-se registrar um evento para auditoria se necessário.
        NULL;
    ELSIF OLD.status = 'devolvido' AND NEW.status = 'vendido' THEN
        -- Produto foi devolvido e agora vendido (ex: cliente comprou depois de devolver)
        -- Precisa registrar uma nova saída
        INSERT INTO estoque (id_produto, tipo_movimentacao, quantidade, data_movimentacao, observacao)
        VALUES (NEW.id_produto, 'saida', NEW.quantidade, CURRENT_DATE, 'Venda de produto originalmente devolvido de consignação ' || NEW.nome_cliente);
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_ajustar_estoque_condicional_status
AFTER UPDATE ON condicional
FOR EACH ROW
WHEN (OLD.status IS DISTINCT FROM NEW.status)
EXECUTE FUNCTION ajustar_estoque_condicional_status();


-- Inserção de dados de exemplo (opcional, pode ser removido para um banco vazio)
INSERT INTO produto (nome, descricao, preco, estoque) VALUES
('Vestido Floral', 'Vestido de verão com estampa floral.', 129.90, 10),
('Camisa Social Masculina', 'Camisa manga longa branca.', 89.90, 25),
('Calça Jeans Feminina', 'Calça jeans cintura alta.', 149.00, 15);

-- Exemplo de uso (os inserts abaixo acionarão as triggers e atualizarão o estoque)
-- INSERT INTO estoque (id_produto, tipo_movimentacao, quantidade, observacao) VALUES (1, 'entrada', 5, 'Nova remessa');
-- INSERT INTO pedidos (id_produto, quantidade) VALUES (2, 3);
-- INSERT INTO condicional (id_produto, nome_cliente, quantidade, status) VALUES (3, 'Maria Silva', 2, 'pendente');
-- UPDATE condicional SET status = 'vendido' WHERE id_condicional = 1; -- Exemplo de atualização de status
-- UPDATE condicional SET status = 'devolvido' WHERE id_condicional = 2; -- Exemplo de devolução
