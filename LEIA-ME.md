# ERP Condomínio – Sistema de Chamados

Sistema de suporte técnico desenvolvido em PHP + MySQL para rodar no HostGator.

---

## Estrutura de Arquivos

```
chamados_erp/
├── index.php              ← Login do cliente (CNPJ + senha)
├── painel.php             ← Painel do cliente (chamados, chat, perfil)
├── logout.php             ← Logout do cliente
├── setup.php              ← Instalação (apagar após usar!)
├── .htaccess              ← Segurança e configurações Apache
├── install.sql            ← Script SQL alternativo (phpMyAdmin)
├── LEIA-ME.md             ← Este arquivo
│
├── includes/
│   └── config.php         ← Configurações do banco e funções globais
│
├── assets/
│   ├── style.css          ← CSS global do sistema
│   └── logo.png           ← Logo ERP Condomínio
│
├── actions/
│   ├── abrir_chamado.php  ← Action: abrir novo chamado
│   └── enviar_mensagem.php← Action: enviar mensagem no chat
│
├── admin/
│   ├── login.php          ← Login do administrador
│   ├── index.php          ← Dashboard admin
│   ├── chamados.php       ← Listagem de chamados
│   ├── chamado_detalhe.php← Detalhe + gestão de chamado
│   ├── clientes.php       ← CRUD de clientes
│   ├── logout.php         ← Logout admin
│   └── partials/
│       ├── sidebar.php    ← Menu lateral admin
│       └── topbar.php     ← Barra superior admin
│
└── uploads/               ← Anexos dos chamados (criado automaticamente)
    └── .htaccess          ← Proteção do diretório
```

---

## Instalação no HostGator

### Passo 1 – Criar banco de dados

1. Acesse o **cPanel** do HostGator
2. Vá em **MySQL Databases**
3. Crie um banco de dados: `inlaud99_erpcondo`
4. Crie um usuário: `inlaud99_erpcondo` com a senha desejada
5. Associe o usuário ao banco com **todas as permissões**

### Passo 2 – Configurar credenciais

Edite o arquivo `includes/config.php` e atualize:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'inlaud99_erpcondo');
define('DB_PASS', 'SUA_SENHA_AQUI');
define('DB_NAME', 'inlaud99_erpcondo');
```

### Passo 3 – Fazer upload dos arquivos

1. Acesse o **File Manager** do cPanel ou use FTP
2. Faça upload de todos os arquivos para o diretório desejado
   - Ex: `public_html/chamados/` para acessar em `seusite.com/chamados/`
   - Ou `public_html/` para acessar na raiz

### Passo 3.5 – Permissões de Arquivos (Erro 403)
Se você vir um erro **403 Acesso Negado**, verifique as permissões no cPanel:
- Todas as **pastas** devem ter permissão **755**.
- Todos os **arquivos** devem ter permissão **644**.
- O arquivo `.htaccess` deve ter permissão **644**.

### Passo 4 – Instalar o banco de dados

Acesse no navegador:
```
https://seusite.com/chamados/setup.php?key=erpcondo2024setup
```

Isso criará todas as tabelas automaticamente.

**IMPORTANTE:** Apague o arquivo `setup.php` após a instalação!

### Passo 5 – Acessar o sistema

- **Portal do Cliente:** `https://seusite.com/chamados/`
- **Painel Admin:** `https://seusite.com/chamados/admin/login.php`

---

## Credenciais

### Administrador
| Campo   | Valor           |
|---------|-----------------|
| Usuário | `inlaud99_admin` |
| Senha   | `Admin259087`   |

### Clientes de Demonstração
| Empresa                              | CNPJ                  | Senha    |
|--------------------------------------|-----------------------|----------|
| Condomínio Residencial Primavera     | 12.345.678/0001-90    | 123456   |
| Condomínio Edifício Central          | 98.765.432/0001-10    | 123456   |
| Associação Moradores Vila Nova       | 11.222.333/0001-44    | 123456   |

---

## Funcionalidades

### Portal do Cliente
- Login com CNPJ e senha
- Dashboard com estatísticas dos chamados
- Abertura de chamados (assunto, descrição, prioridade, anexo)
- Número automático de chamado (ex: CHM20240001)
- Acompanhamento de status em tempo real
- Barra de SLA com tempo restante
- Chat por chamado
- Chat geral (seleciona chamado e conversa)
- Perfil: visualizar dados e alterar senha

### Painel Administrativo
- Dashboard com métricas gerais
- Listagem de chamados com filtros (status, prioridade, cliente, busca)
- Detalhe do chamado com todas as informações
- Atualização de status com observação (notifica o cliente automaticamente)
- Botões rápidos: Fechar / Cancelar chamado
- Alteração de prioridade
- Chat com o cliente por chamado
- Histórico completo de atualizações
- CRUD completo de clientes (criar, editar, inativar, excluir)

### SLA por Prioridade
| Prioridade | SLA     |
|------------|---------|
| Crítica    | 2 horas |
| Alta       | 8 horas |
| Média      | 24 horas|
| Baixa      | 72 horas|

---

## Requisitos do Servidor

- PHP 7.4+ (recomendado PHP 8.x)
- MySQL 5.7+ ou MariaDB 10.3+
- Extensão PDO e PDO_MySQL habilitadas
- mod_rewrite habilitado (Apache)
- Permissão de escrita no diretório `uploads/` (Recomendado 755 para pastas e 644 para arquivos no HostGator)

---

## Segurança

- Senhas armazenadas com `password_hash()` (bcrypt)
- Proteção contra SQL Injection via PDO + prepared statements
- Sanitização de output com `htmlspecialchars()`
- Proteção de diretórios via `.htaccess`
- Execução de PHP bloqueada no diretório de uploads
- Sessões PHP para autenticação

---

*ERP Condomínio – Software de gestão de condomínios e associações*
