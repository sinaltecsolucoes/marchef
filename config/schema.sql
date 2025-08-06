--
-- Estrutura para tabela `tbl_configuracoes`
--

CREATE TABLE `tbl_configuracoes` (
  `config_id` INT(11) NOT NULL AUTO_INCREMENT,
  `config_nome_empresa` VARCHAR(255) NOT NULL,
  `config_nome_fantasia` VARCHAR(255) NOT NULL,
  `config_cnpj` varchar(14) DEFAULT NULL, 
  `config_logo_path` VARCHAR(255) NULL,
  PRIMARY KEY (`config_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Estrutura para tabela `tbl_api_tokens`
--

CREATE TABLE `tbl_api_tokens` (
  `token_id` int(11) NOT NULL,
  `token_usuario_id` int(11) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `token_expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Estrutura para tabela `tbl_auditoria_logs`
--

CREATE TABLE `tbl_auditoria_logs` (
  `log_id` int(11) NOT NULL,
  `log_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `log_usuario_id` int(11) DEFAULT NULL,
  `log_usuario_nome` varchar(150) DEFAULT NULL,
  `log_acao` varchar(50) NOT NULL COMMENT 'Ex: CREATE, UPDATE, DELETE, LOGIN_SUCCESS, LOGIN_FAIL',
  `log_tabela_afetada` varchar(100) DEFAULT NULL COMMENT 'Ex: tbl_usuarios, tbl_produtos',
  `log_registro_id` int(11) DEFAULT NULL COMMENT 'ID do registo que foi alterado (ex: usu_codigo)',
  `log_dados_antigos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Estado do registo ANTES da alteração' CHECK (json_valid(`log_dados_antigos`)),
  `log_dados_novos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Estado do registo DEPOIS da alteração' CHECK (json_valid(`log_dados_novos`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Estrutura para tabela `tbl_carregamentos`
--

CREATE TABLE `tbl_carregamentos` (
  `car_id` int(11) NOT NULL,
  `car_numero` varchar(50) NOT NULL,
  `car_data` date NOT NULL,
  `car_entidade_id_organizador` int(11) NOT NULL,
  `car_lacre` varchar(100) DEFAULT NULL,
  `car_placa_veiculo` varchar(20) DEFAULT NULL,
  `car_hora_inicio` time DEFAULT NULL,
  `car_ordem_expedicao` varchar(100) DEFAULT NULL,
  `car_usuario_id_responsavel` int(11) NOT NULL,
  `car_status` enum('EM ANDAMENTO','FINALIZADO') NOT NULL DEFAULT 'EM ANDAMENTO',
  `car_data_finalizacao` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Estrutura para tabela `tbl_carregamento_filas`
--

CREATE TABLE `tbl_carregamento_filas` (
  `fila_id` int(11) NOT NULL,
  `fila_carregamento_id` int(11) NOT NULL,
  `fila_entidade_id_cliente` int(11) NOT NULL,
  `fila_foto_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Estrutura para tabela `tbl_carregamento_leituras`
--

CREATE TABLE `tbl_carregamento_leituras` (
  `leitura_id` int(11) NOT NULL,
  `leitura_fila_id` int(11) NOT NULL,
  `leitura_qrcode_conteudo` text NOT NULL,
  `leitura_timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Estrutura para tabela `tbl_clientes`
--

CREATE TABLE `tbl_clientes` (
  `cli_codigo` int(11) NOT NULL,
  `cli_entidade_id` int(11) NOT NULL,
  `cli_status_cliente` enum('Ativo','Inativo','Potencial') NOT NULL DEFAULT 'Ativo',
  `cli_limite_credito` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cli_data_cadastro` datetime DEFAULT current_timestamp(),
  `cli_usuario_cadastro_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Estrutura para tabela `tbl_enderecos`
--

CREATE TABLE `tbl_enderecos` (
  `end_codigo` int(11) NOT NULL,
  `end_entidade_id` int(11) NOT NULL,
  `end_tipo_endereco` enum('Principal','Entrega','Cobranca','Residencial','Comercial','Outro') NOT NULL,
  `end_cep` varchar(8) DEFAULT NULL COMMENT 'CEP (apenas dígitos, ex: 12345678)',
  `end_logradouro` varchar(255) DEFAULT NULL,
  `end_numero` varchar(50) DEFAULT NULL,
  `end_bairro` varchar(100) DEFAULT NULL,
  `end_cidade` varchar(100) DEFAULT NULL,
  `end_uf` char(2) DEFAULT NULL,
  `end_complemento` varchar(255) DEFAULT NULL,
  `end_data_cadastro` datetime DEFAULT current_timestamp(),
  `end_usuario_cadastro_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Estrutura para tabela `tbl_entidades`
--

CREATE TABLE `tbl_entidades` (
  `ent_codigo` int(11) NOT NULL,
  `ent_razao_social` varchar(255) NOT NULL,
  `ent_nome_fantasia` varchar(255) DEFAULT NULL,
  `ent_tipo_pessoa` enum('F','J') NOT NULL COMMENT 'F: Pessoa Física, J: Pessoa Jurídica',
  `ent_cpf` varchar(11) DEFAULT NULL COMMENT 'CPF para Pessoa Física (apenas dígitos, ex: 12345678901)',
  `ent_cnpj` varchar(14) DEFAULT NULL COMMENT 'CNPJ para Pessoa Jurídica (apenas dígitos, ex: 12345678901234)',
  `ent_inscricao_estadual` varchar(50) DEFAULT NULL,
  `ent_tipo_entidade` enum('Cliente','Fornecedor','Cliente e Fornecedor') NOT NULL,
  `ent_situacao` enum('A','I') NOT NULL DEFAULT 'A' COMMENT 'A: Ativo, I: Inativo',
  `ent_data_cadastro` timestamp NOT NULL DEFAULT current_timestamp(),
  `ent_usuario_cadastro_id` int(11) DEFAULT NULL COMMENT 'ID do usuário que realizou o cadastro',
  `ent_codigo_interno` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Estrutura para tabela `tbl_estoque`
--

CREATE TABLE `tbl_estoque` (
  `estoque_id` int(11) NOT NULL,
  `estoque_produto_id` int(11) NOT NULL,
  `estoque_lote_item_id` int(11) DEFAULT NULL,
  `estoque_quantidade` decimal(10,3) NOT NULL,
  `estoque_data_movimento` timestamp NOT NULL DEFAULT current_timestamp(),
  `estoque_tipo_movimento` enum('ENTRADA','SAIDA','AJUSTE') NOT NULL,
  `estoque_observacao` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Estrutura para tabela `tbl_etiqueta_regras`
--

CREATE TABLE `tbl_etiqueta_regras` (
  `regra_id` int(11) NOT NULL,
  `regra_produto_id` int(11) DEFAULT NULL,
  `regra_cliente_id` int(11) DEFAULT NULL,
  `regra_template_id` int(11) NOT NULL,
  `regra_prioridade` int(11) NOT NULL DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Estrutura para tabela `tbl_etiqueta_templates`
--

CREATE TABLE `tbl_etiqueta_templates` (
  `template_id` int(11) NOT NULL,
  `template_nome` varchar(150) NOT NULL,
  `template_descricao` text DEFAULT NULL,
  `template_conteudo_zpl` longtext NOT NULL,
  `template_data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Estrutura para tabela `tbl_fornecedores`
--

CREATE TABLE `tbl_fornecedores` (
  `forn_codigo` int(11) NOT NULL,
  `forn_entidade_id` int(11) NOT NULL,
  `forn_categoria` varchar(100) DEFAULT NULL,
  `forn_condicoes_pagamento` varchar(255) DEFAULT NULL,
  `forn_data_cadastro` datetime DEFAULT current_timestamp(),
  `forn_usuario_cadastro_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Estrutura para tabela `tbl_lotes`
--

CREATE TABLE `tbl_lotes` (
  `lote_id` int(11) NOT NULL,
  `lote_numero` varchar(4) NOT NULL,
  `lote_data_fabricacao` date NOT NULL,
  `lote_fornecedor_id` int(11) DEFAULT NULL,
  `lote_cliente_id` int(11) DEFAULT NULL,
  `lote_ciclo` varchar(10) DEFAULT NULL,
  `lote_viveiro` varchar(10) DEFAULT NULL,
  `lote_completo_calculado` varchar(50) DEFAULT NULL,
  `lote_status` enum('EM ANDAMENTO','FINALIZADO','CANCELADO','PARCIALMENTE FINALIZADO') NOT NULL DEFAULT 'EM ANDAMENTO',
  `lote_usuario_id` int(11) NOT NULL,
  `lote_data_cadastro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Estrutura para tabela `tbl_lote_itens`
--

CREATE TABLE `tbl_lote_itens` (
  `item_id` int(11) NOT NULL,
  `item_lote_id` int(11) NOT NULL,
  `item_produto_id` int(11) NOT NULL,
  `item_quantidade` decimal(10,3) NOT NULL,
  `item_quantidade_finalizada` decimal(10,3) NOT NULL DEFAULT 0.000 COMMENT 'Quantidade deste item que já foi finalizada e enviada ao estoque',
  `item_status` varchar(20) NOT NULL DEFAULT 'EM PRODUCAO' COMMENT 'EM PRODUCAO, FINALIZADO',
  `item_data_validade` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Estrutura para tabela `tbl_permissoes`
--

CREATE TABLE `tbl_permissoes` (
  `permissao_codigo` int(11) NOT NULL,
  `permissao_pagina` varchar(100) NOT NULL,
  `permissao_perfil` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Estrutura para tabela `tbl_produtos`
--

CREATE TABLE `tbl_produtos` (
  `prod_codigo` int(11) NOT NULL,
  `prod_codigo_interno` varchar(50) DEFAULT NULL,
  `prod_descricao` varchar(255) NOT NULL,
  `prod_situacao` char(1) DEFAULT 'A',
  `prod_tipo` enum('CAMARAO','PEIXE','POLVO','LAGOSTA','OUTRO') NOT NULL,
  `prod_subtipo` varchar(100) DEFAULT NULL,
  `prod_classificacao` varchar(100) DEFAULT NULL,
  `prod_categoria` varchar(30) DEFAULT NULL,
  `prod_classe` varchar(255) DEFAULT NULL,
  `prod_especie` varchar(100) DEFAULT NULL,
  `prod_origem` enum('CULTIVO','PESCA EXTRATIVA') DEFAULT NULL,
  `prod_conservacao` enum('CRU','COZIDO','PARC. COZIDO','EMPANADO') DEFAULT NULL,
  `prod_congelamento` enum('BLOCO','IQF') DEFAULT NULL,
  `prod_fator_producao` decimal(5,2) DEFAULT NULL,
  `prod_tipo_embalagem` enum('PRIMARIA','SECUNDARIA') NOT NULL,
  `prod_peso_embalagem` decimal(10,3) NOT NULL,
  `prod_total_pecas` varchar(50) DEFAULT NULL,
  `prod_validade_meses` int(11) DEFAULT NULL,
  `prod_primario_id` int(11) DEFAULT NULL,
  `prod_ean13` varchar(13) DEFAULT NULL,
  `prod_dun14` varchar(14) DEFAULT NULL,
  `prod_data_cadastro` timestamp NOT NULL DEFAULT current_timestamp(),
  `prod_data_modificacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Estrutura para tabela `tbl_usuarios`
--

CREATE TABLE `tbl_usuarios` (
  `usu_codigo` int(11) NOT NULL,
  `usu_nome` varchar(50) NOT NULL,
  `usu_login` varchar(20) NOT NULL,
  `usu_senha` varchar(255) NOT NULL,
  `usu_session_token` varchar(64) DEFAULT NULL,
  `usu_tipo` varchar(20) NOT NULL,
  `usu_situacao` varchar(2) NOT NULL COMMENT 'A - Ativo / I - Inativo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tabela de Cadastro de Usuarios do Sistema';

--
-- Índices de tabela `tbl_api_tokens`
--
ALTER TABLE `tbl_api_tokens`
  ADD PRIMARY KEY (`token_id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`);

--
-- Índices de tabela `tbl_auditoria_logs`
--
ALTER TABLE `tbl_auditoria_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Índices de tabela `tbl_carregamentos`
--
ALTER TABLE `tbl_carregamentos`
  ADD PRIMARY KEY (`car_id`);

--
-- Índices de tabela `tbl_carregamento_filas`
--
ALTER TABLE `tbl_carregamento_filas`
  ADD PRIMARY KEY (`fila_id`);

--
-- Índices de tabela `tbl_carregamento_leituras`
--
ALTER TABLE `tbl_carregamento_leituras`
  ADD PRIMARY KEY (`leitura_id`);

--
-- Índices de tabela `tbl_clientes`
--
ALTER TABLE `tbl_clientes`
  ADD PRIMARY KEY (`cli_codigo`);

--
-- Índices de tabela `tbl_enderecos`
--
ALTER TABLE `tbl_enderecos`
  ADD PRIMARY KEY (`end_codigo`);

--
-- Índices de tabela `tbl_entidades`
--
ALTER TABLE `tbl_entidades`
  ADD PRIMARY KEY (`ent_codigo`);

--
-- Índices de tabela `tbl_estoque`
--
ALTER TABLE `tbl_estoque`
  ADD PRIMARY KEY (`estoque_id`);

--
-- Índices de tabela `tbl_etiqueta_regras`
--
ALTER TABLE `tbl_etiqueta_regras`
  ADD PRIMARY KEY (`regra_id`);

--
-- Índices de tabela `tbl_etiqueta_templates`
--
ALTER TABLE `tbl_etiqueta_templates`
  ADD PRIMARY KEY (`template_id`);

--
-- Índices de tabela `tbl_fornecedores`
--
ALTER TABLE `tbl_fornecedores`
  ADD PRIMARY KEY (`forn_codigo`);

--
-- Índices de tabela `tbl_lotes`
--
ALTER TABLE `tbl_lotes`
  ADD PRIMARY KEY (`lote_id`);

--
-- Índices de tabela `tbl_lote_itens`
--
ALTER TABLE `tbl_lote_itens`
  ADD PRIMARY KEY (`item_id`);

--
-- Índices de tabela `tbl_permissoes`
--
ALTER TABLE `tbl_permissoes`
  ADD PRIMARY KEY (`permissao_codigo`);

--
-- Índices de tabela `tbl_produtos`
--
ALTER TABLE `tbl_produtos`
  ADD PRIMARY KEY (`prod_codigo`);

--
-- Índices de tabela `tbl_usuarios`
--
ALTER TABLE `tbl_usuarios`
  ADD PRIMARY KEY (`usu_codigo`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `tbl_api_tokens`
--
ALTER TABLE `tbl_api_tokens`
  MODIFY `token_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tbl_auditoria_logs`
--
ALTER TABLE `tbl_auditoria_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `tbl_carregamentos`
--
ALTER TABLE `tbl_carregamentos`
  MODIFY `car_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tbl_carregamento_filas`
--
ALTER TABLE `tbl_carregamento_filas`
  MODIFY `fila_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tbl_carregamento_leituras`
--
ALTER TABLE `tbl_carregamento_leituras`
  MODIFY `leitura_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tbl_clientes`
--
ALTER TABLE `tbl_clientes`
  MODIFY `cli_codigo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de tabela `tbl_enderecos`
--
ALTER TABLE `tbl_enderecos`
  MODIFY `end_codigo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `tbl_entidades`
--
ALTER TABLE `tbl_entidades`
  MODIFY `ent_codigo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `tbl_estoque`
--
ALTER TABLE `tbl_estoque`
  MODIFY `estoque_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de tabela `tbl_etiqueta_regras`
--
ALTER TABLE `tbl_etiqueta_regras`
  MODIFY `regra_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tbl_etiqueta_templates`
--
ALTER TABLE `tbl_etiqueta_templates`
  MODIFY `template_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `tbl_fornecedores`
--
ALTER TABLE `tbl_fornecedores`
  MODIFY `forn_codigo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de tabela `tbl_lotes`
--
ALTER TABLE `tbl_lotes`
  MODIFY `lote_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `tbl_lote_itens`
--
ALTER TABLE `tbl_lote_itens`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de tabela `tbl_permissoes`
--
ALTER TABLE `tbl_permissoes`
  MODIFY `permissao_codigo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de tabela `tbl_produtos`
--
ALTER TABLE `tbl_produtos`
  MODIFY `prod_codigo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=696;

--
-- AUTO_INCREMENT de tabela `tbl_usuarios`
--
ALTER TABLE `tbl_usuarios`
  MODIFY `usu_codigo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;
