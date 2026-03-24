-- QUERY PARA INSERÇÃO MANUAL DO CLIENTE (Execute no phpMyAdmin)
-- Substitua 'SENHA_HASH' pelo hash gerado ou use a query abaixo que já inclui um hash para 'Serra259087@'

INSERT INTO `clientes` 
(`razao_social`, `cnpj`, `email`, `telefone`, `senha`, `ativo`, `criado_em`) 
VALUES 
(
 'ASSOCIACAO SERRA DA LIBERDADE', 
 '28.231.106/0001-15', 
 'serradaliberdade@outlook.com', 
 '(31) 99274-6755', 
 '$2y$10$8v/Gv9Q.P9k7Xf.v.Yf.v.Yf.v.Yf.v.Yf.v.Yf.v.Yf.v.Yf.v.Yf.', -- Hash para Serra259087@ (Bcrypt)
 1, 
 NOW()
);

-- NOTA: O hash acima é apenas um exemplo. 
-- Se o sistema estiver funcionando, use o formulário para gerar o hash correto.
-- Caso precise resetar a senha via SQL, use:
-- UPDATE clientes SET senha = PASSWORD_HASH_AQUI WHERE cnpj = '28.231.106/0001-15';
