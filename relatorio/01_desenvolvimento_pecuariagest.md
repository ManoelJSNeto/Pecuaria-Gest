# 📖 Caderno de Desenvolvimento: Sistema PecuáriaGest

> **Finalidade:** Servir como base documental e memorial descritivo para os capítulos de Introdução, Motivação, Metodologia e Engenharia de Software do TCC.

---

## 1. Origem e Motivação: O "Porquê" Real do PecuáriaGest

### 1.1 O Desafio da Gestão Remota (Cidade vs Campo)
O projeto nasceu de uma dor familiar concreta: o avô do autor é pecuarista e proprietário rural, mas reside predominantemente na cidade. 
* **O Problema da Comunicação:** O distanciamento físico gerava uma defasagem crítica nas informações. Quando um bezerro nascia, um animal adoecia ou ocorria uma pesagem, o proprietário só ficava sabendo dias depois — ou quando visitava a fazenda.
* **A Fragilidade da Caderneta de Papel:** Toda a anotação do rebanho era feita manualmente em cadernos no curral, sujeita a rasuras, umidade, perda de folhas e erros de transcrição, tornando a tomada de decisão lenta e baseada em estimativas imprecisas.
* **A Proposta de Valor:** Desenvolver um ecossistema com dois pilares integrados:
  1. **Aplicativo Mobile (Curral/Pasto):** Coletar dados com rapidez na mão do vaqueiro, funcionando 100% offline e enviando lotes assim que houver conexão.
  2. **Painel Web Centralizado (Cidade/Escritório):** Permitir que o proprietário, mesmo a centenas de quilômetros de distância, visualize os dados organizados, monitore ganho de peso, aprove coberturas e gere relatórios gerenciais consolidados.

### 1.2 O Ponto de Inflexão (Validação Externa)
Inicialmente, o PecuáriaGest foi concebido como uma aplicação teste para gerar carga de dados para a pesquisa de Computação em Nuvem. No entanto, dois eventos reais mudaram o rumo do projeto:
1. **Aprovação Imediata do Usuário-Chave:** O avô do autor testou o sistema, aprovou o fluxo e solicitou prontamente um plano para migrar todo o histórico acumulado em papel para a plataforma digital.
2. **Interesse de Mercado (Validação Agrícola):** Durante um encontro casual, um ex-aluno formado na mesma ETEC Agrícola (Colégio Agrícola) tomou conhecimento da proposta do sistema para o terceiro ano e demonstrou interesse imediato, solicitando contato para implantar a solução em sua propriedade no estado de Minas Gerais.

Esses fatos comprovaram que o software havia superado o escopo de um "protótipo acadêmico descartável", tornando-se uma solução viável com alto potencial de adoção no agronegócio familiar e de médio porte.

---

## 2. A Jornada da Arquitetura e Decisões Técnicas

### 2.1 A Descontinuação do PWA e a Adoção do Capacitor 8
* **A Tentativa com Progressive Web App (PWA):**  
  A primeira versão buscou utilizar PWA para evitar lojas de aplicativos e facilitar o acesso via navegador.
* **A Falha Prática:**  
  O PWA falhou na premissa fundamental do projeto: **confiabilidade offline absoluta**. O ciclo de atualização do Service Worker/WebAPK no Android Chrome retinha código antigo em cache por até 24 horas, e o cold-start (abertura do app sem sinal) frequentemente travava a thread do navegador, gerando sensação de tela congelada e perda de foco nos inputs.
* **A Solução com Ionic Capacitor 8:**  
  O Capacitor permitiu reaproveitar 100% da base web (HTML5, CSS e JS) empacotando-a como um aplicativo Android nativo (`.apk`). Os ativos residem diretamente no armazenamento do dispositivo (`assets/public/`), iniciando em 0ms sem depender de conexão ou Service Worker, com acesso direto a recursos de hardware (câmera traseira nativa e vibração física).

### 2.2 Da Arquitetura Monolítica aos Microsserviços Docker
Para viabilizar o estudo comparativo de nuvem e garantir isolamento de processos, a stack foi desacoplada em 3 contêineres independentes:
* **`web` (Nginx 1.25 Alpine):** Proxy reverso, entrega de arquivos estáticos, terminação HTTP/HTTPS e bloqueio de acessos a pastas internas.
* **`app` (PHP 8.3-FPM Alpine):** Processamento das regras de negócio, autenticação e geração de views.
* **`db` (PostgreSQL 16 Alpine):** Instância relacional pura com volume de dados persistente (`./storage/data`).

### 2.3 Padronização Estrita no PostgreSQL (Fim do SQLite)
O sistema inicialmente possuía suporte dual (SQLite local e PostgreSQL em produção). No entanto, as diferenças de dialeto (`strftime` vs `TO_CHAR`), tipagem fraca e limitações de concorrência em gravações simultâneas do SQLite criavam ruído metodológico nos testes de estresse. A decisão técnica foi **padronizar 100% no PostgreSQL nativo**, garantindo paridade exata entre o ambiente de desenvolvimento local e o banco gerenciado da nuvem (AWS RDS).

---

## 3. Ergonomia de Campo & Acessibilidade Visual (O Foco no Vaqueiro)

### 3.1 Alfabetização Visual e Ícones Intuitivos
Em propriedades rurais, é comum que colaboradores do campo possuam diferentes graus de escolaridade ou dificuldades com leitura técnica rápida.
* **Priorização de Símbolos:** O sistema substituiu rótulos puramente textuais por figuras e ícones vetoriais universais (balança para peso, seringa/coração para saúde, bezerro para nascimento).
* **Fluxo Semântico:** Uso de cores intuitivas (verde para saudável/ativo, vermelho para atenção/doente, preto para óbito).

### 3.2 Usabilidade sob Sol Forte e Curral
* **Design de Alto Contraste:** Cores calibradas (paleta verde terroso `#26441F` e contrastes escuros) para garantir legibilidade sob incidência solar direta.
* **Toque Amplo (Glove-Friendly):** Botões com altura mínima de **68px** e campos com 50px de altura para permitir acionamento preciso mesmo com luvas de manejo ou mãos calejadas.
* **Confirmação Tátil Nativa:** Ao salvar uma pesagem ou manejo, o hardware do celular vibra com pulso firme (`@capacitor/haptics`), dispensando a necessidade de o vaqueiro parar a lida para conferir visualmente a tela.
