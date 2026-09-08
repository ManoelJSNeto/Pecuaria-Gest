# 💾 Camada de Armazenamento Persistente — `storage/`

Este diretório gerencia o armazenamento de dados que devem sobreviver a reinicializações de contêineres e máquinas.

---

## 📁 Estrutura de Diretórios

```
storage/
├── data/                    # Dados de controle interno e arquivos de trava
│   ├── .db_ready            # Arquivo sentinela (indica banco inicializado com sucesso)
│   └── .gitkeep
└── uploads/                 # Arquivos enviados por usuários e app móvel
    ├── documentos/          # Arquivos XML de NF-e e cópias de GTAs
    ├── fotos/               # Fotos de identificação de animais e laudos veterinários
    └── .gitkeep
```

---

## 🔐 Regras de Acesso e Segurança

1. **`storage/data/` (Privado):**
   * **Bloqueado para a web:** O servidor Nginx **não expõe** este diretório sob nenhuma circunstância.
   * Contém arquivos de estado interno, como o `.db_ready`, que sinaliza ao PHP que as 11 tabelas e índices já foram criados no PostgreSQL, economizando tempo de CPU em cada clique.

2. **`storage/uploads/` (Público Controlado):**
   * Mapeado no Nginx através do alias `/uploads/`.
   * Possui diretiva de cache no Nginx com cabeçalho `Cache-Control: public, max-age=2592000` (30 dias) e resposta direta sem tocar no processador PHP.
   * Não executa scripts: Arquivos `.php` colocados aqui não são interpretados pelo servidor, prevenindo uploads maliciosos (RCE).

---

## ⚙️ Permissões de Escrita no Host / Docker
Em ambientes Linux/Docker, garanta que o usuário do PHP-FPM (`www-data` ou UID 33) possua permissão de escrita:
```bash
chmod -R 775 storage/
# Se necessário:
# chown -R 33:33 storage/
```
