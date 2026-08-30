/**
 * ============================================================
 * Monitor de Métricas de CPU / CloudWatch para o Benchmark TCC
 * ============================================================
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const METRICS_FILE = path.join(__dirname, 'resultados', 'cpu_credits_metrics.json');

function recordCpuSnapshot(stage, env, users, run) {
  const snapshot = {
    timestamp: new Date().toISOString(),
    stage, // 'before' ou 'after'
    env,
    users,
    run,
    metrics: {}
  };

  // 1. Tenta coletar métricas do CloudWatch via AWS CLI se configurado
  if (env === 'aws') {
    try {
      const now = new Date();
      const tenMinAgo = new Date(now.getTime() - 10 * 60 * 1000);
      const startTime = tenMinAgo.toISOString();
      const endTime = now.toISOString();

      // Coleta CPUCreditBalance da EC2
      const cmdEc2 = `aws cloudwatch get-metric-statistics --namespace AWS/EC2 --metric-name CPUCreditBalance --dimensions Name=InstanceType,Value=t3.micro --start-time ${startTime} --end-time ${endTime} --period 300 --statistics Average --output json`;
      const outEc2 = execSync(cmdEc2, { stdio: ['pipe', 'pipe', 'ignore'], timeout: 3000 }).toString();
      snapshot.metrics.ec2_cpu_credit_balance = JSON.parse(outEc2);
    } catch (e) {
      snapshot.metrics.ec2_cpu_credit_balance_note = 'AWS CLI credentials not bound in local shell; snapshot timestamped for console correlation.';
    }

    try {
      // Coleta CPUUtilization da EC2
      const cmdUtil = `aws cloudwatch get-metric-statistics --namespace AWS/EC2 --metric-name CPUUtilization --dimensions Name=InstanceType,Value=t3.micro --start-time ${startTime} --end-time ${endTime} --period 300 --statistics Maximum,Average --output json`;
      const outUtil = execSync(cmdUtil, { stdio: ['pipe', 'pipe', 'ignore'], timeout: 3000 }).toString();
      snapshot.metrics.ec2_cpu_utilization = JSON.parse(outUtil);
    } catch (e) {}
  }

  // 2. Registra no arquivo JSON persistente
  let allSnapshots = [];
  if (fs.existsSync(METRICS_FILE)) {
    try {
      allSnapshots = JSON.parse(fs.readFileSync(METRICS_FILE, 'utf-8'));
    } catch (e) {}
  }

  allSnapshots.push(snapshot);
  fs.writeFileSync(METRICS_FILE, JSON.stringify(allSnapshots, null, 2), 'utf-8');
  console.log(`📡 [CPU Snapshot Registrado]: ${stage.toUpperCase()} ${env.toUpperCase()} ${users} users (Run #${run}) @ ${snapshot.timestamp}`);
}

module.exports = { recordCpuSnapshot };
