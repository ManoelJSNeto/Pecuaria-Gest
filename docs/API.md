# PecuáriaGest — Especificação da API de Sincronização Mobile

Esta API permite que o aplicativo mobile sincronize registros de pesagens, eventos sanitários e novos animais cadastrados em campo offline.

---

## Autenticação

Todas as requisições para a API devem incluir o cabeçalho HTTP:

```http
X-API-KEY: sua_chave_configurada_no_env
```

> **Nota:** A chave padrão em desenvolvimento é `pecuaria-mobile-key`. Em produção, defina a variável `API_KEY` no arquivo `.env`.

---

## Endpoint: Sincronização em Lote

### `POST /api/sync`

Envia um lote de eventos coletados no aplicativo mobile para consolidação no servidor.

#### Headers:
```http
Content-Type: application/json
X-API-KEY: pecuaria-mobile-key
```

#### Corpo da Requisição (Payload JSON):

```json
{
  "animais": [
    {
      "brinco": "BR1050",
      "nome": "Estrela",
      "sexo": "F",
      "raca": "Nelore",
      "data_nascimento": "2024-05-10",
      "peso_inicial": 210.5,
      "status": "ativo",
      "pasto_id": 1,
      "origem": "Nascimento",
      "pai_brinco": "BR0010"
    }
  ],
  "pesagens": [
    {
      "animal_id": 1,
      "peso": 485.2,
      "data": "2026-08-25"
    }
  ],
  "saude": [
    {
      "animal_id": 1,
      "tipo": "Vacinação",
      "descricao": "Vacina Febre Aftosa",
      "medicamento": "Aftosa Tri-Vac",
      "data": "2026-08-25"
    }
  ]
}
```

#### Resposta de Sucesso (`200 OK`):

```json
{
  "status": "success",
  "processados": {
    "animais_novos": 1,
    "pesagens": 1,
    "saude": 1
  },
  "erros": []
}
```

#### Resposta de Erro de Autenticação (`401 Unauthorized`):

```json
{
  "error": "Unauthorized"
}
```
