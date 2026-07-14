import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const testsDir = path.join(root, 'tests', 'api');
const reportScope = process.env.API_TEST_REPORT_SCOPE || 'baseline';
const reportDir = path.join(root, 'storage', 'api-test-reports', reportScope);
const playwrightCli = path.join(root, 'node_modules', '@playwright', 'test', 'cli.js');
const timeoutMs = Number(process.env.API_TEST_FILE_TIMEOUT_MS || 20 * 60 * 1000);

fs.mkdirSync(reportDir, { recursive: true });

const requestedFiles = process.argv.slice(2);
const files = (requestedFiles.length
  ? requestedFiles
  : fs.readdirSync(testsDir).filter((file) => file.endsWith('.spec.ts')).map((file) => path.join('tests', 'api', file)))
  .map((file) => file.replace(/\\/g, '/'))
  .sort();

function runFile(file) {
  return new Promise((resolve) => {
    const baseName = path.basename(file, '.spec.ts');
    const jsonPath = path.join(reportDir, `${baseName}.json`);
    const logPath = path.join(reportDir, `${baseName}.log`);
    const log = fs.createWriteStream(logPath, { flags: 'w' });
    const startedAt = new Date();

    console.log(`\n[baseline] START ${file}`);
    const child = spawn(process.execPath, [
      playwrightCli,
      'test',
      file,
      '--workers=1',
      '--reporter=line,json',
    ], {
      cwd: root,
      env: {
        ...process.env,
        PLAYWRIGHT_JSON_OUTPUT_NAME: jsonPath,
      },
      windowsHide: true,
    });

    let timedOut = false;
    const timer = setTimeout(() => {
      timedOut = true;
      if (process.platform === 'win32') {
        spawnSync('taskkill', ['/pid', String(child.pid), '/T', '/F'], { windowsHide: true });
      } else {
        child.kill('SIGTERM');
      }
    }, timeoutMs);

    child.stdout.on('data', (chunk) => {
      process.stdout.write(chunk);
      log.write(chunk);
    });
    child.stderr.on('data', (chunk) => {
      process.stderr.write(chunk);
      log.write(chunk);
    });

    child.on('close', (code) => {
      clearTimeout(timer);
      log.end();
      const finishedAt = new Date();
      let stats = null;
      let errors = [];

      if (fs.existsSync(jsonPath)) {
        try {
          const report = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));
          stats = report.stats || null;
          errors = report.errors || [];
        } catch (error) {
          errors = [{ message: `Unable to parse JSON report: ${error.message}` }];
        }
      }

      const result = {
        file: file.replace(/\\/g, '/'),
        exitCode: code,
        timedOut,
        startedAt: startedAt.toISOString(),
        finishedAt: finishedAt.toISOString(),
        durationMs: finishedAt - startedAt,
        stats,
        errors,
        jsonReport: path.relative(root, jsonPath).replace(/\\/g, '/'),
        log: path.relative(root, logPath).replace(/\\/g, '/'),
      };
      console.log(`[baseline] END ${file} exit=${code} timeout=${timedOut} duration=${result.durationMs}ms`);
      resolve(result);
    });
  });
}

const results = [];
for (const file of files) {
  results.push(await runFile(file));
}

const totals = results.reduce((summary, result) => {
  summary.files += 1;
  if (result.timedOut) summary.timedOutFiles += 1;
  if (result.exitCode === 0) summary.passedFiles += 1;
  else summary.failedFiles += 1;
  if (result.stats) {
    summary.expected += result.stats.expected || 0;
    summary.unexpected += result.stats.unexpected || 0;
    summary.flaky += result.stats.flaky || 0;
    summary.skipped += result.stats.skipped || 0;
  }
  summary.durationMs += result.durationMs;
  return summary;
}, {
  files: 0,
  passedFiles: 0,
  failedFiles: 0,
  timedOutFiles: 0,
  expected: 0,
  unexpected: 0,
  flaky: 0,
  skipped: 0,
  durationMs: 0,
});

const summary = {
  generatedAt: new Date().toISOString(),
  timeoutMs,
  totals,
  results,
};

fs.writeFileSync(path.join(reportDir, 'summary.json'), `${JSON.stringify(summary, null, 2)}\n`);

const markdown = [
  '# Existing API Test Baseline',
  '',
  `Generated: ${summary.generatedAt}`,
  '',
  `- Files: ${totals.files}`,
  `- Passed files: ${totals.passedFiles}`,
  `- Failed files: ${totals.failedFiles}`,
  `- Timed out files: ${totals.timedOutFiles}`,
  `- Passed tests: ${totals.expected}`,
  `- Failed tests: ${totals.unexpected}`,
  `- Flaky tests: ${totals.flaky}`,
  `- Skipped tests: ${totals.skipped}`,
  '',
  '| File | Result | Passed | Failed | Skipped | Duration |',
  '| --- | --- | ---: | ---: | ---: | ---: |',
  ...results.map((result) => {
    const status = result.timedOut ? 'TIMEOUT' : result.exitCode === 0 ? 'PASS' : 'FAIL';
    const stats = result.stats || {};
    return `| ${result.file} | ${status} | ${stats.expected || 0} | ${stats.unexpected || 0} | ${stats.skipped || 0} | ${Math.round(result.durationMs / 1000)}s |`;
  }),
  '',
].join('\n');

fs.writeFileSync(path.join(reportDir, 'summary.md'), markdown);
console.log(`\n[baseline] SUMMARY ${JSON.stringify(totals)}`);
