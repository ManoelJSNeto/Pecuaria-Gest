# 📱 Aplicativo Mobile Nativo: PecuGest-Campo — `mobile-app/`

Este diretório contém o código-fonte do aplicativo Android nativo dedicado **PecuGest-Campo** (`com.pecuariagest.app`), desenvolvido com **Ionic Capacitor 8**.

---

## 🎯 Por que Aplicativo Nativo (Capacitor) e NÃO PWA?

Conforme registrado nos memoriais do TCC:
* **A falha da PWA no campo:** O ciclo de atualização do Service Worker no navegador Android frequentemente retinha versões antigas em cache, travava a abertura sem sinal de rede (*cold-start*) e perdia o estado dos formulários quando a memória do celular era reciclada.
* **A solução com Capacitor:** O código web (`www/`) reside compactado diretamente dentro do pacote APK no armazenamento do aparelho. Ele inicia instantaneamente em **0ms** mesmo no modo avião, tem acesso nativo a recursos de hardware e não depende de navegador externo.

---

## 🐂 Recursos Específicos para a Lida no Curral

1. **Modo 100% Offline com IndexedDB:**  
   Todas as pesagens, registros clínicos e novos bezerros são salvos no banco local do dispositivo. O vaqueiro pode trabalhar semanas sem sinal de internet.

2. **Feedback Tátil Háptico (`@capacitor/haptics`):**  
   Ao pressionar para registrar uma pesagem, o telefone vibra com pulso firme. O vaqueiro sabe que o registro foi gravado sem precisar parar a lida para conferir a tela.

3. **Design Ergonômico para Uso com Luvas:**  
   Botões com altura mínima de **68px** e contraste elevado para visualização sob luz solar direta no curral.

4. **Zero-Touch Network (Sem digitação de IP):**  
   O vaqueiro não precisa configurar portas ou IPs. O app mantém a sessão salva e descarrega os dados automaticamente assim que entra no raio do Wi-Fi da sede.

---

## 🔄 Comunicação com o Servidor Central

* **`GET /api/animais`**: Baixa a lista de animais ativos com seus últimos pesos para alimentar a busca local. Exige cabeçalho `X-API-KEY: pecuaria-mobile-key`.
* **`POST /api/sync`**: Envia os lotes acumulados (pesagens, manejos e fotos em Base64). O servidor processa os dados em uma transação atômica com rollback em caso de falha.

---

## 🛠️ Comandos de Compilação e Empacotamento

Para gerar o APK ou depurar no dispositivo físico:

```bash
# 1. Instalar dependências (na primeira vez)
npm install

# 2. Sincronizar alterações da pasta www com o projeto nativo Android
npx cap sync

# 3. Abrir o projeto no Android Studio para compilar o APK
npx cap open android
```
