# 💬 Caderno de Discussões, Decisões de Negócio & Lições Aprendidas

> **Finalidade:** Espaço documental para registrar o racional humano, operacional e zootécnico por trás das decisões do projeto, servindo como base para os capítulos de Levantamento de Requisitos, Discussão e Considerações Finais do TCC.

---

## 1. O Conceito de Usabilidade "Saque Rápido" no Campo

### 1.1 A Realidade da Lida Rural e a Inclusão Digital
Muitos sistemas de software agropecuário falham na adoção porque são concebidos com a mentalidade de escritórios urbanos. No curral de manejo, a realidade é drástica:
* O operador lida com animais de 400kg a 700kg em movimento, poeira, lama e sol escaldante.
* Há operadores com baixa familiaridade com tecnologia touch ou com dificuldades de letramento técnico.
* Qualquer sistema que exija múltiplos passos, digitação de termos complexos ou configurações de rede é imediatamente abandonado pela equipe de campo.

### 1.2 A Eliminação Completa do IP ("Zero-Touch Network")
* **O Risco da Configuração Manual:** Expor um campo para o vaqueiro digitar endereços de IP (`192.168.x.x` ou portas) gera alto índice de erro e desconfiguração indevida ("dar zulo").
* **A Decisão do App de Saque Rápido:**  
  O aplicativo móvel deve nascer pré-configurado de fábrica apontando para o endpoint seguro do servidor. O operador insere apenas seu **login e senha uma única vez**.
* **Sessão Persistente de Trabalho (Manter Sempre Logado):**  
  Uma vez autenticado, o token de sincronização é mantido salvo no dispositivo com segurança. Quando o operador retorna ao alcance do sinal e aciona o descarregamento (ou via Auto-Sync), a transmissão ocorre de forma invisível em segundo plano, **sem travar a produção nem interromper o serviço exigindo e-mail e senha a cada pesagem**.

---

## 2. Metodologia de Levantamento de Requisitos: Escuta Ativa Direta

### 2.1 Da Abordagem Formal ao Diálogo Empírico
No início do projeto, a equipe planejou a aplicação de formulários estruturados (*Google Forms*) para mapear os processos da fazenda.
* **O Aprendizado Prático:** No contexto da agricultura e pecuária familiar, questionários acadêmicos e frios geram distanciamento e respostas rasas.
* **A Virada Metodológica:** A equipe adotou a técnica de **entrevistas semiestruturadas e escuta ativa verbal** com o produtor rural (o avô do autor). Acompanhando os relatos das frustrações diárias com anotações em papel, perdas de vacinação e falhas de comunicação com a cidade, os requisitos reais emergiram de forma muito mais fidedigna do que qualquer questionário padronizado.

---

## 3. Regras de Negócio e Fidelidade Zootécnica

### 3.1 Genealogia e Consistência Biológica
* **Restrição Estrita de Maternidade:** O sistema implementou validação para que apenas fêmeas ativas e vivas (`sexo = 'F'`) possam ser selecionadas como mães biológicas de novos bezerros, eliminando incongruências de cadastro.
* **Distinção de Origem:** Separação explícita entre animais nascidos na propriedade (que geram cálculo de idade e controle de maternidade) versus animais adquiridos/comprados de terceiros (com registro de peso de entrada e valor de aquisição).

### 3.2 O Ciclo de Saída (Faturamento vs Mortalidade)
* **Baixa por Venda:** Registro formal do comprador, valor por arroba/total, peso de embarque e data, alimentando o painel financeiro do proprietário na cidade.
* **Baixa por Óbito:** Exigência de motivo clínico e registro de necropsia, impactando diretamente os índices zootécnicos de taxa de mortalidade do rebanho no dashboard.

---

## 4. Segurança e Integridade Transacional no Campo

### 4.1 Transações Atômicas no Descarregamento (`/api/sync`)
Como a sincronização de campo transmite pacotes contendo dezenas de pesagens e manejos acumulados durante o dia, a API foi construída com controle estrito de transação de banco de dados (`BEGIN / COMMIT / ROLLBACK`):
* Se um lote de 40 pesagens sofrer uma falha de conexão na 39ª, a operação inteira sofre reversão segura (*rollback*). O aplicativo mantém o lote intacto no celular para reenvio, impedindo que registros fiquem duplicados ou gravados pela metade no banco de dados central.

### 4.2 Privacidade e Apresentação Visual Respeitosa
* **Desfoque de Imagens Clínicas/Óbito:** O sistema aplica automaticamente desfoque visual forte (`blur`) em fotos associadas a ferimentos, tratamentos cirúrgicos ou animais mortos. Isso evita choques visuais desnecessários no painel gerencial, mantendo o acesso ao laudo fotográfico disponível mediante um clique de confirmação.

---

## 5. Blindagem de Autenticação e Proteção contra Força Bruta

### 5.1 O Risco de Ataques de Dicionário em Ambientes Web
Como o painel centralizado fica exposto para acesso remoto do proprietário na cidade, tornou-se imperativo blindar a rota de login contra tentativas automatizadas de adivinhação de senhas:
* **Rate Limiting e Bloqueio Temporário (Lockout):** O sistema monitora as tentativas falhas consecutivas. Ao atingir a **5ª tentativa errada**, a aplicação congela novos acessos por **3 minutos**, desestimulando ataques automatizados de força bruta.
* **Proteção de Credenciais em Produção:** As senhas padrão de primeiro acesso só são aceitas caso o ambiente esteja expressamente declarado como `development`, forçando a troca de senhas na implantação real.
* **Proteção contra Timing Attacks na API:** As rotas consumidas pelo aplicativo móvel (`/api/sync` e `/api/animais`) passaram a comparar as chaves de API utilizando `hash_equals()`, eliminando qualquer vazamento de tempo em nanossegundos que pudesse permitir a dedução da chave secreta.

---

## 6. Arquitetura de Software: Monólito Pragmático vs SPA e a Transição para Controladores

### 6.1 Por que não separar o Front-End Web em uma SPA (React/Vue)?
No meio acadêmico e no mercado corporativo, é comum adotar como "padrão" a separação total entre uma Single Page Application (SPA) em JavaScript e uma API REST no back-end. Para o PecuáriaGest, no entanto, a decisão consciente foi manter o **Painel Web em Server-Side Rendering (SSR) com PHP 8.3 e Nginx**:
* **Desempenho Instantâneo:** O servidor entrega o HTML sem exigir que o dispositivo do usuário faça download de pesados bundles de JavaScript de centenas de megabytes.
* **Simplicidade de Manutenção:** Evita a duplicação de regras de validação (uma no front e outra no back), elimina problemas de CORS e complexidade de tokens JWT com expiração e refresh.
* **Separação Onde Importa:** A separação de responsabilidades existe com clareza entre o **Aplicativo Android Nativo** (que coleta no campo) e o **Painel Central** (que consolida e exibe no escritório).

### 6.2 O Próximo Passo Evolutivo: Modularização em Controladores Dedicados
Com o amadurecimento dos módulos de Animais, Pesagens, Manejo Sanitário, Reprodução, Pastagens, Alertas e o novo Cockpit Comercial (Compras e Vendas), o arquivo `public/index.php` atingiu seu limite saudável como Front Controller + Roteador. 
* **A Estratégia de Transição:** Para manter o código limpo e sustentável para futuras equipes, foi planejado o desacoplamento do roteamento em **Controladores Dedicados** (`src/controllers/`), permitindo que cada recurso de negócio tenha sua própria classe especializada (`AnimaisController`, `ComercialController`, `AuthController`, etc.), mantendo o `index.php` apenas como despachante de requisições de poucas dezenas de linhas.
