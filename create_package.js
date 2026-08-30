const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const rootDir = __dirname;
const targetDir = path.join(rootDir, 'pacote_tcc_analise_ia');

if (fs.existsSync(targetDir)) {
  fs.rmSync(targetDir, { recursive: true, force: true });
}
fs.mkdirSync(targetDir, { recursive: true });
fs.mkdirSync(path.join(targetDir, 'benchmark'), { recursive: true });
fs.mkdirSync(path.join(targetDir, 'documentacao_tecnica'), { recursive: true });

function copyFileSafe(src, dest) {
  if (fs.existsSync(src)) {
    fs.copyFileSync(src, dest);
    console.log(`✓ Copiado: ${path.basename(src)}`);
  }
}

function copyDirRecursive(srcDir, destDir) {
  if (!fs.existsSync(srcDir)) return;
  if (!fs.existsSync(destDir)) fs.mkdirSync(destDir, { recursive: true });
  const entries = fs.readdirSync(srcDir, { withFileTypes: true });
  for (const entry of entries) {
    const srcPath = path.join(srcDir, entry.name);
    const destPath = path.join(destDir, entry.name);
    if (entry.isDirectory()) {
      copyDirRecursive(srcPath, destPath);
    } else {
      fs.copyFileSync(srcPath, destPath);
      console.log(`✓ Copiado: ${entry.name}`);
    }
  }
}

console.log('\n📦 Montando o Pacote Completo do TCC...');

// 1. Dossiê e Status
copyFileSafe(path.join(rootDir, 'DOSSIE_COMPLETO_TCC_PECUARIAGEST.md'), path.join(targetDir, 'DOSSIE_COMPLETO_TCC_PECUARIAGEST.md'));
copyFileSafe(path.join(rootDir, 'STATUS_PROJETO_E_TCC.md'), path.join(targetDir, 'STATUS_PROJETO_E_TCC.md'));

// 2. Documentação Técnica
copyDirRecursive(path.join(rootDir, 'docs'), path.join(targetDir, 'documentacao_tecnica'));

// 3. Suíte de Benchmark e Resultados
copyFileSafe(path.join(rootDir, 'tests/benchmark/RESULTADOS_CONSOLIDADOS_TCC.md'), path.join(targetDir, 'benchmark/RESULTADOS_CONSOLIDADOS_TCC.md'));
copyFileSafe(path.join(rootDir, 'tests/benchmark/plano_teste_jmeter.jmx'), path.join(targetDir, 'benchmark/plano_teste_jmeter.jmx'));
copyFileSafe(path.join(rootDir, 'tests/benchmark/run_benchmark.js'), path.join(targetDir, 'benchmark/run_benchmark.js'));
copyFileSafe(path.join(rootDir, 'tests/benchmark/executar_todos_cenarios.ps1'), path.join(targetDir, 'benchmark/executar_todos_cenarios.ps1'));
copyFileSafe(path.join(rootDir, 'tests/benchmark/README.md'), path.join(targetDir, 'benchmark/README.md'));
copyDirRecursive(path.join(rootDir, 'tests/benchmark/resultados'), path.join(targetDir, 'benchmark/resultados'));

console.log('\n🗜️ Compactando em PACOTE_COMPLETO_TCC_PECUARIAGEST.zip...');
const zipFile = path.join(rootDir, 'PACOTE_COMPLETO_TCC_PECUARIAGEST.zip');
if (fs.existsSync(zipFile)) {
  fs.unlinkSync(zipFile);
}

try {
  execSync(`powershell -Command "Compress-Archive -Path '${targetDir}/*' -DestinationPath '${zipFile}' -Force"`, { stdio: 'inherit' });
  console.log(`\n🎉 SUCESSO! Pacote ZIP criado com sucesso em: ${zipFile}\n`);
} catch (e) {
  console.error('Erro ao compactar:', e.message);
}
