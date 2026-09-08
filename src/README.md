# 🧠 Camada de Lógica de Negócio e Domínio — `src/`

Este diretório concentra o núcleo lógico, as regras de negócio, a infraestrutura de banco de dados e os mecanismos de segurança da aplicação **PecuáriaGest**.

---

## 📁 Arquitetura dos Módulos

| Arquivo | Responsabilidade Principal |
|---|---|
| **`config.php`** | Configurações centrais, definição de caminhos (`STORAGE_PATH`, `DATA_PATH`, `UPLOADS_PATH`), chaves de segurança (`SESSION_SECRET`, `API_KEY`) e conexão com o PostgreSQL. |
| **`auth.php`** | Motor de autenticação com senhas em **Bcrypt**, gestão do ciclo de vida da sessão e controle de acesso baseado em papéis (**RBAC**) via `can($permissao)` e `requirePermission($permissao)`. |
| **`db.php`** | Provedor de conexão PDO com PostgreSQL via padrão Singleton (`getDb()`), execução do esquema relacional e criação de índices B-Tree de alta velocidade. Possui controle de arquivo sentinela (`.db_ready`) para garantir tempo de resposta < 2ms. |
| **`helpers.php`** | Conjunto de funções utilitárias: sanitização de entradas (`cleanInput`), formatação agropecuária (cálculo de arroba `@`, rendimento de carcaça e moedas), tokens Anti-CSRF (`csrf_field`, `csrf_verify`), mensagens flash e envio de notificações SMTP. |
| **`views/`** | Subdiretório contendo os templates e componentes visuais do Painel Web (Server-Side Rendering). |
| **`controllers/`** | *(Em transição)* Pasta reservada para os controladores especializados que assumirão o processamento das rotas hoje concentradas no Front Controller. |

---

## 🛡️ Diretrizes de Segurança para Edição de Código

1. **Sempre use Prepared Statements:**  
   Nunca interpole variáveis diretamente em consultas SQL. Exemplo obrigatório:
   ```php
   // CORRETO
   $stmt = $db->prepare("SELECT * FROM animais WHERE brinco = ? AND status = ?");
   $stmt->execute([$brinco, 'ativo']);
   ```

2. **Sempre valide permissões em ações sensíveis:**  
   Para rotas de exclusão, edição de usuários ou relatórios restritos:
   ```php
   requirePermission('gerenciar_usuarios');
   ```

3. **Validação CSRF em requisições POST:**  
   Todo formulário que altera dados deve incluir `<?= csrf_field() ?>` no HTML e validar `if (!csrf_verify())` no processamento.

4. **Regras Zootécnicas Imutáveis:**  
   * Fêmeas: Apenas fêmeas ativas e vivas (`sexo = 'F'`) podem ser vinculadas como mãe biológica.
   * Óbito: Todo animal marcado com óbito deve ter seu status alterado para `'morto'` e seu histórico arquivado.
