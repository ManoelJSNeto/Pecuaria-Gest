/**
 * MONITOR DE TELEMETRIA DE RECURSOS DOCKER (TCC)
 * PecuáriaGest - Coletor de CPU %, Memória RAM, I/O de Disco e Armazenamento
 * 
 * Executa em segundo plano durante os testes da Tríade e captura a cada 1s:
 * - CPU % de cada container (app, db, web)
 * - Memória RAM utilizada (MB) e %
 * - Leitura e Gravação em Disco (Block I/O)
 * - Volume de Rede (Net I/O)
 * - Tamanho físico do Banco de Dados PostgreSQL e Uploads
 */

const { execSync, spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const RESULTS_DIR = path.join(__dirname, 'resultados');
if (!fs.existsSync(RESULTS_DIR)) {
  fs.mkdirSync(RESULTS_DIR, { recursive: true });
}

class DockerMonitor {
  constructor() {
    this.intervalId = null;
    this.samples = [];
    this.currentContext = null;
    this.initialDbSizeMb = 0;
  }

  /**
   * Converte strings formatadas do Docker (ex: "45.2MiB", "1.2GiB", "500kB") em Megabytes (Float)
   */
  parseBytesToMb(str) {
    if (!str || typeof str !== 'string') return 0;
    const clean = str.trim().toLowerCase();
    const num = parseFloat(clean);
    if (isNaN(num)) return 0;
    if (clean.includes('gib') || clean.includes('gb')) return num * 1024;
    if (clean.includes('mib') || clean.includes('mb')) return num;
    if (clean.includes('kib') || clean.includes('kb')) return num / 1024;
    if (clean.includes('b')) return num / (1024 * 1024);
    return num;
  }

  /**
   * Converte strings de porcentagem (ex: "12.34%") em Float
   */
  parsePercent(str) {
    if (!str || typeof str !== 'string') return 0;
    const num = parseFloat(str.replace('%', '').trim());
    return isNaN(num) ? 0 : num;
  }

  /**
   * Obtém o tamanho em MB da pasta de dados do PostgreSQL
   */
  getPostgresDbSizeMb() {
    try {
      const output = execSync('docker exec pecuaria-gest-db du -sk /var/lib/postgresql/data', { stdio: ['pipe', 'pipe', 'ignore'], timeout: 2000 }).toString();
      const kb = parseInt(output.trim().split(/\s+/)[0], 10);
      return !isNaN(kb) ? parseFloat((kb / 1024).toFixed(2)) : 0;
    } catch (e) {
      return 0;
    }
  }

  /**
   * Inicia a coleta contínua a cada 1 segundo
   */
  start(context = {}) {
    this.samples = [];
    this.currentContext = {
      env: context.env || 'local',
      pilar: context.pilar || 'Pilar 1 (API)',
      concurrency: context.concurrency || 20,
      run: context.run || 1,
      startTime: new Date().toISOString()
    };
    this.initialDbSizeMb = this.getPostgresDbSizeMb();

    this.sample(); // Coleta amostra inicial imediata

    this.intervalId = setInterval(() => {
      this.sample();
    }, 1000);
  }

  /**
   * Coleta 1 snapshot dos containers
   */
  sample() {
    try {
      const format = '{{.Name}},{{.CPUPerc}},{{.MemUsage}},{{.MemPerc}},{{.NetIO}},{{.BlockIO}}';
      const output = execSync(`docker stats --no-stream --format "${format}"`, { stdio: ['pipe', 'pipe', 'ignore'], timeout: 3000 }).toString();
      const lines = output.trim().split('\n');
      const now = new Date().toISOString();
      const currentDbSize = this.getPostgresDbSizeMb();

      for (const line of lines) {
        if (!line.trim()) continue;
        const parts = line.split(',');
        if (parts.length < 6) continue;

        const name = parts[0].trim();
        // Filtrar apenas containers do projeto
        if (!name.includes('pecuaria-gest')) continue;

        const cpuPerc = this.parsePercent(parts[1]);
        
        // MemUsage: "54.2MiB / 7.67GiB"
        const memParts = parts[2].split('/');
        const memUsageMb = this.parseBytesToMb(memParts[0]);
        const memLimitMb = memParts[1] ? this.parseBytesToMb(memParts[1]) : 0;
        const memPerc = this.parsePercent(parts[3]);

        // NetIO: "1.2MB / 540kB"
        const netParts = parts[4].split('/');
        const netInMb = this.parseBytesToMb(netParts[0]);
        const netOutMb = netParts[1] ? this.parseBytesToMb(netParts[1]) : 0;

        // BlockIO: "12.4MB / 3.2MB"
        const blockParts = parts[5].split('/');
        const blockReadMb = this.parseBytesToMb(blockParts[0]);
        const blockWriteMb = blockParts[1] ? this.parseBytesToMb(blockParts[1]) : 0;

        this.samples.push({
          timestamp: now,
          container: name,
          cpuPerc,
          memUsageMb,
          memLimitMb,
          memPerc,
          netInMb,
          netOutMb,
          blockReadMb,
          blockWriteMb,
          dbSizeMb: currentDbSize
        });
      }
    } catch (e) {
      // Docker offline ou erro de execução ignorado suavemente
    }
  }

  /**
   * Para a coleta, grava o CSV bruto e calcula as métricas consolidadas
   */
  stop() {
    if (this.intervalId) {
      clearInterval(this.intervalId);
      this.intervalId = null;
    }

    if (!this.samples.length) {
      return {
        hasData: false,
        cpuPeak: 0,
        cpuAvg: 0,
        ramPeakMb: 0,
        ramAvgMb: 0,
        blockWriteTotalMb: 0,
        dbSizeMb: 0
      };
    }

    const { env, pilar, concurrency, run } = this.currentContext;
    const finalDbSize = this.getPostgresDbSizeMb();

    // 1. Gravar CSV detalhado da rodada
    const csvFileName = `telemetria_hardware_${env}_${concurrency}users_run${run}_${Date.now()}.csv`;
    const csvPath = path.join(RESULTS_DIR, csvFileName);

    const header = 'Timestamp,Env,Pilar,Concurrency,Run,Container,CPU_Perc,Mem_MB,Mem_Limit_MB,Mem_Perc,Block_Read_MB,Block_Write_MB,Net_In_MB,Net_Out_MB,DB_Size_MB\n';
    const rows = this.samples.map(s => 
      `${s.timestamp},${env},"${pilar}",${concurrency},${run},${s.container},${s.cpuPerc.toFixed(2)},${s.memUsageMb.toFixed(2)},${s.memLimitMb.toFixed(2)},${s.memPerc.toFixed(2)},${s.blockReadMb.toFixed(2)},${s.blockWriteMb.toFixed(2)},${s.netInMb.toFixed(2)},${s.netOutMb.toFixed(2)},${s.dbSizeMb.toFixed(2)}`
    ).join('\n');

    fs.writeFileSync(csvPath, header + rows, 'utf-8');

    // 2. Calcular estatísticas agregadas (agrupadas por segundo e por container)
    // Agrupamento de CPU: somar CPU de todos os containers em cada segundo para obter o consumo total do host
    const timeGroups = {};
    this.samples.forEach(s => {
      if (!timeGroups[s.timestamp]) timeGroups[s.timestamp] = { totalCpu: 0, totalRam: 0 };
      timeGroups[s.timestamp].totalCpu += s.cpuPerc;
      timeGroups[s.timestamp].totalRam += s.memUsageMb;
    });

    const cpuTotals = Object.values(timeGroups).map(g => g.totalCpu);
    const ramTotals = Object.values(timeGroups).map(g => g.totalRam);

    const cpuPeak = cpuTotals.length ? Math.max(...cpuTotals) : 0;
    const cpuAvg = cpuTotals.length ? (cpuTotals.reduce((a, b) => a + b, 0) / cpuTotals.length) : 0;
    const ramPeakMb = ramTotals.length ? Math.max(...ramTotals) : 0;
    const ramAvgMb = ramTotals.length ? (ramTotals.reduce((a, b) => a + b, 0) / ramTotals.length) : 0;

    // Métricas por container (especialmente DB e APP)
    const dbSamples = this.samples.filter(s => s.container.includes('-db'));
    const appSamples = this.samples.filter(s => s.container.includes('-app'));

    const dbCpuPeak = dbSamples.length ? Math.max(...dbSamples.map(s => s.cpuPerc)) : 0;
    const appCpuPeak = appSamples.length ? Math.max(...appSamples.map(s => s.cpuPerc)) : 0;
    const dbRamPeak = dbSamples.length ? Math.max(...dbSamples.map(s => s.memUsageMb)) : 0;
    const appRamPeak = appSamples.length ? Math.max(...appSamples.map(s => s.memUsageMb)) : 0;

    return {
      hasData: true,
      csvFile: csvFileName,
      samplesCount: this.samples.length,
      cpuPeak: parseFloat(cpuPeak.toFixed(2)),
      cpuAvg: parseFloat(cpuAvg.toFixed(2)),
      ramPeakMb: parseFloat(ramPeakMb.toFixed(2)),
      ramAvgMb: parseFloat(ramAvgMb.toFixed(2)),
      dbCpuPeak: parseFloat(dbCpuPeak.toFixed(2)),
      appCpuPeak: parseFloat(appCpuPeak.toFixed(2)),
      dbRamPeak: parseFloat(dbRamPeak.toFixed(2)),
      appRamPeak: parseFloat(appRamPeak.toFixed(2)),
      dbSizeMb: finalDbSize || this.initialDbSizeMb
    };
  }
}

// Instância singleton para uso simples nos scripts
const monitor = new DockerMonitor();

// Execução Standalone via CLI (útil para rodar na EC2 via SSH)
if (require.main === module) {
  const args = process.argv.slice(2);
  const env = args.includes('--env') ? args[args.indexOf('--env') + 1] : 'aws';
  const concurrency = args.includes('--concurrency') ? parseInt(args[args.indexOf('--concurrency') + 1], 10) : 100;
  const run = args.includes('--run') ? parseInt(args[args.indexOf('--run') + 1], 10) : 1;

  console.log('================================================================');
  console.log(`📡 MONITOR DE TELEMETRIA DOCKER STANDALONE [${env.toUpperCase()}]`);
  console.log('================================================================');
  console.log('Coletando CPU %, Memória RAM, I/O e Storage a cada 1 segundo...');
  console.log('⚡ Pressione Ctrl+C a qualquer momento para finalizar e salvar o CSV.\n');

  monitor.start({ env, pilar: 'Bateria Remota', concurrency, run });

  function finish() {
    console.log('\n🛑 Interrupção recebida. Gravando dados brutos de telemetria...');
    const res = monitor.stop();
    if (res.hasData) {
      console.log(`✅ Arquivo CSV gerado: ${res.csvFile}`);
      console.log(`📊 Total de Amostras: ${res.samplesCount}`);
      console.log(`🔥 Pico de CPU Geral: ${res.cpuPeak}% (Média: ${res.cpuAvg}%)`);
      console.log(`🧠 Pico de RAM Geral: ${res.ramPeakMb} MB (Média: ${res.ramAvgMb} MB)`);
      if (res.dbSizeMb > 0) console.log(`💾 Tamanho do Banco de Dados: ${res.dbSizeMb} MB`);
    } else {
      console.log('⚠️ Nenhuma amostra foi coletada.');
    }
    process.exit(0);
  }

  process.on('SIGINT', finish);
  process.on('SIGTERM', finish);
}

module.exports = {
  DockerMonitor,
  monitor
};
