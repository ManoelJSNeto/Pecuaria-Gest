<?php
function getDb(): PDO {
    static $db = null;
    if ($db === null) {
        if (DB_DRIVER === 'pgsql') {
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_DATABASE;
            $db = new PDO($dsn, DB_USERNAME, DB_PASSWORD);
        } else {
            $db = new PDO('sqlite:' . DB_PATH);
            $db->exec('PRAGMA foreign_keys = ON;');
            $db->exec('PRAGMA busy_timeout = 5000;');
            $db->exec('PRAGMA journal_mode = WAL;');
        }
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        initDb($db);
    }
    return $db;
}

function initDb(PDO $db): void {
    $isPg = (DB_DRIVER === 'pgsql');
    $pk   = $isPg ? 'SERIAL PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $now  = 'CURRENT_TIMESTAMP';

    $db->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            id $pk,
            nome TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            senha TEXT NOT NULL,
            tipo TEXT DEFAULT 'usuario',
            cargo TEXT DEFAULT 'Colaborador',
            permissoes TEXT DEFAULT '{}',
            ativo INTEGER DEFAULT 1,
            ultimo_acesso TEXT,
            created_at TIMESTAMP DEFAULT $now
        );

        CREATE TABLE IF NOT EXISTS pastagens (
            id $pk,
            nome TEXT NOT NULL,
            area_ha REAL,
            capacidade INTEGER,
            status TEXT DEFAULT 'ativa',
            observacao TEXT,
            created_at TIMESTAMP DEFAULT $now
        );

        CREATE TABLE IF NOT EXISTS animais (
            id $pk,
            brinco TEXT UNIQUE NOT NULL,
            nome TEXT,
            sexo TEXT NOT NULL DEFAULT 'M',
            raca TEXT,
            data_nascimento TEXT,
            peso_inicial REAL,
            status TEXT DEFAULT 'ativo',
            pasto_id INTEGER REFERENCES pastagens(id),
            origem TEXT,
            mae_id INTEGER REFERENCES animais(id),
            pai_brinco TEXT,
            observacao TEXT,
            foto_url TEXT,
            created_at TIMESTAMP DEFAULT $now,
            updated_at TIMESTAMP DEFAULT $now
        );

        CREATE TABLE IF NOT EXISTS pesagens (
            id $pk,
            animal_id INTEGER NOT NULL REFERENCES animais(id) ON DELETE CASCADE,
            peso REAL NOT NULL,
            data TEXT NOT NULL,
            observacao TEXT,
            origem TEXT DEFAULT 'web',
            created_at TIMESTAMP DEFAULT $now
        );

        CREATE TABLE IF NOT EXISTS saude (
            id $pk,
            animal_id INTEGER NOT NULL REFERENCES animais(id) ON DELETE CASCADE,
            tipo TEXT NOT NULL,
            descricao TEXT NOT NULL,
            data TEXT NOT NULL,
            veterinario TEXT,
            medicamento TEXT,
            dose TEXT,
            proxima_data TEXT,
            custo REAL,
            observacao TEXT,
            origem TEXT DEFAULT 'web',
            created_at TIMESTAMP DEFAULT $now
        );

        CREATE TABLE IF NOT EXISTS reproducao (
            id $pk,
            animal_id INTEGER NOT NULL REFERENCES animais(id) ON DELETE CASCADE,
            tipo TEXT NOT NULL,
            data TEXT NOT NULL,
            resultado TEXT,
            touro_brinco TEXT,
            observacao TEXT,
            created_at TIMESTAMP DEFAULT $now
        );

        CREATE TABLE IF NOT EXISTS alertas (
            id $pk,
            animal_id INTEGER REFERENCES animais(id) ON DELETE CASCADE,
            tipo TEXT NOT NULL,
            mensagem TEXT NOT NULL,
            lido INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT $now
        );

        CREATE TABLE IF NOT EXISTS sincronizacoes (
            id $pk,
            dispositivo TEXT,
            ip TEXT,
            dados_recebidos INTEGER DEFAULT 0,
            status TEXT DEFAULT 'ok',
            detalhes TEXT,
            created_at TIMESTAMP DEFAULT $now
        );

        CREATE TABLE IF NOT EXISTS fotos_animais (
            id $pk,
            animal_id INTEGER NOT NULL REFERENCES animais(id) ON DELETE CASCADE,
            foto_url TEXT NOT NULL,
            tipo_evento TEXT DEFAULT 'perfil',
            fase TEXT DEFAULT 'geral',
            is_sensivel INTEGER DEFAULT 0,
            data TEXT NOT NULL,
            observacao TEXT,
            created_at TIMESTAMP DEFAULT $now
        );

        CREATE TABLE IF NOT EXISTS configuracoes (
            chave TEXT PRIMARY KEY,
            valor TEXT,
            created_at TIMESTAMP DEFAULT $now
        );
    ");

    // Migrações dinâmicas para bases já existentes
    try { $db->exec("ALTER TABLE usuarios ADD COLUMN cargo TEXT DEFAULT 'Colaborador'"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE usuarios ADD COLUMN permissoes TEXT DEFAULT '{}'"); } catch (Exception $e) {}

    // Seed admin user if none exists
    $count = $db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    if ($count == 0) {
        $hash = password_hash(DEFAULT_ADMIN_PASS, PASSWORD_BCRYPT);
        $db->prepare("INSERT INTO usuarios (nome, email, senha, tipo, cargo, permissoes) VALUES (?, ?, ?, 'admin', 'Proprietário Geral', '{}')")
           ->execute(['Administrador', DEFAULT_ADMIN_EMAIL, $hash]);
    }

    // Seed sample pastagens
    $pCount = $db->query("SELECT COUNT(*) FROM pastagens")->fetchColumn();
    if ($pCount == 0) {
        $pastos = [
            ['Pasto A', 15.5, 30, 'ativa'],
            ['Pasto B', 22.0, 45, 'ativa'],
            ['Pasto C', 10.0, 20, 'ativa'],
            ['Pasto Maternidade', 5.0, 10, 'ativa'],
            ['Pasto Engorda', 18.0, 35, 'ativa'],
        ];
        $stmt = $db->prepare("INSERT INTO pastagens (nome, area_ha, capacidade, status) VALUES (?, ?, ?, ?)");
        foreach ($pastos as $p) $stmt->execute($p);
    }

    // Seed sample animals
    $aCount = $db->query("SELECT COUNT(*) FROM animais")->fetchColumn();
    if ($aCount == 0) {
        $racas  = ['Nelore', 'Angus', 'Brahman', 'Girolando', 'Gir', 'Senepol'];
        $status = ['ativo', 'ativo', 'ativo', 'ativo', 'prenha', 'doente'];
        $animais = [];
        for ($i = 1; $i <= 30; $i++) {
            $sexo  = ($i % 3 === 0) ? 'M' : 'F';
            $raca  = $racas[$i % count($racas)];
            $st    = $sexo === 'M' ? 'ativo' : $status[$i % count($status)];
            $nasci = date('Y-m-d', mktime(0, 0, 0, rand(1,12), rand(1,28), rand(2020,2024)));
            $peso  = round(rand(200, 550) + rand(0,99)/100, 2);
            $pasto = rand(1, 5);
            $animais[] = [sprintf('BR%04d', $i), ($sexo==='M'?'Boi ':'Vaca ').$i, $sexo, $raca, $nasci, $peso, $st, $pasto];
        }
        $stmt = $db->prepare("INSERT INTO animais (brinco, nome, sexo, raca, data_nascimento, peso_inicial, status, pasto_id) VALUES (?,?,?,?,?,?,?,?)");
        foreach ($animais as $a) $stmt->execute($a);

        // Add weight records
        $animals = $db->query("SELECT id FROM animais")->fetchAll();
        $wstmt   = $db->prepare("INSERT INTO pesagens (animal_id, peso, data) VALUES (?,?,?)");
        foreach ($animals as $an) {
            for ($w = 0; $w < rand(3, 8); $w++) {
                $d = date('Y-m-d', mktime(0,0,0, rand(1,12), rand(1,28), rand(2023,2026)));
                $p = round(rand(180, 600) + rand(0,99)/100, 2);
                $wstmt->execute([$an['id'], $p, $d]);
            }
        }

        // Add health records
        $tipos = ['Vacinação', 'Tratamento', 'Exame', 'Vermifugação', 'Curativo'];
        $meds  = ['Aftosa', 'Ivermectina', 'Antibiótico', 'Vitamina ADE', 'Clostridiose'];
        $hstmt = $db->prepare("INSERT INTO saude (animal_id, tipo, descricao, data, medicamento) VALUES (?,?,?,?,?)");
        foreach ($animals as $idx => $an) {
            if ($idx % 2 === 0) {
                $t = $tipos[rand(0, count($tipos)-1)];
                $m = $meds[rand(0, count($meds)-1)];
                $d = date('Y-m-d', mktime(0,0,0, rand(1,12), rand(1,28), 2025));
                $hstmt->execute([$an['id'], $t, $t . ' - ' . $m, $d, $m]);
            }
        }

        // Add reproduction records for females
        $females = $db->query("SELECT id FROM animais WHERE sexo='F'")->fetchAll();
        if (!empty($females)) {
            $rstmt = $db->prepare("INSERT INTO reproducao (animal_id, tipo, data, resultado, touro_brinco, observacao) VALUES (?,?,?,?,?,?)");
            $reproTipos = [
                ['Inseminação Artificial', 'Positivo - Prenha', 'TO0099', 'Sêmen convencional Nelore'],
                ['Diagnóstico de Gestação', 'Confirmada prenhez 60 dias', 'TO0088', 'Ultrassom realizado'],
                ['Cobertura Natural', 'Aguardando diagnóstico', 'BR0003', 'Manejo a campo'],
                ['Parto', 'Nascimento normal - bezerra saudável', 'TO0042', 'Fêmea 34kg'],
            ];
            foreach ($females as $idx => $f) {
                if ($idx % 2 === 0) {
                    $ev = $reproTipos[$idx % count($reproTipos)];
                    $rstmt->execute([$f['id'], $ev[0], date('Y-m-d', strtotime('-' . rand(10, 180) . ' days')), $ev[1], $ev[2], $ev[3]]);
                }
            }
        }

        // Add alerts
        $db->exec("
            INSERT INTO alertas (animal_id, tipo, mensagem) VALUES
            (1, 'saude',      'Animal com sinais de doença respiratória'),
            (3, 'pesagem',    'Sem pesagem há mais de 60 dias'),
            (5, 'vacina',     'Vacina da febre aftosa vencendo em 7 dias'),
            (7, 'reproducao', 'Prenhez confirmada — atenção ao parto'),
            (9, 'pesagem',    'Ganho de peso abaixo do esperado')
        ");
    }
}
