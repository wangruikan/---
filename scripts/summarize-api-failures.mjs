import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const scope = process.argv[2] || 'baseline';
const reportDir = path.join(root, 'storage', 'api-test-reports', scope);

function collectFailures(suite, parents, file, failures) {
  const titles = suite.title ? [...parents, suite.title] : parents;

  for (const spec of suite.specs || []) {
    for (const test of spec.tests || []) {
      for (const result of test.results || []) {
        if (!['failed', 'timedOut', 'interrupted'].includes(result.status)) continue;
        const firstError = result.errors?.[0] || result.error || {};
        failures.push({
          file,
          title: [...titles, spec.title].filter(Boolean).join(' > '),
          status: result.status,
          message: firstError.message || String(firstError),
        });
      }
    }
  }

  for (const child of suite.suites || []) {
    collectFailures(child, titles, file, failures);
  }
}

const failures = [];
for (const file of fs.readdirSync(reportDir).filter((name) => name.endsWith('.json') && name !== 'summary.json')) {
  const report = JSON.parse(fs.readFileSync(path.join(reportDir, file), 'utf8'));
  for (const suite of report.suites || []) {
    collectFailures(suite, [], file.replace(/\.json$/, '.spec.ts'), failures);
  }
}

fs.writeFileSync(path.join(reportDir, 'failures.json'), `${JSON.stringify(failures, null, 2)}\n`);
const markdown = [
  `# API Test Failures: ${scope}`,
  '',
  `Failures: ${failures.length}`,
  '',
  ...failures.flatMap((failure, index) => [
    `## ${index + 1}. ${failure.title}`,
    '',
    `File: ${failure.file}`,
    '',
    '```text',
    failure.message,
    '```',
    '',
  ]),
].join('\n');
fs.writeFileSync(path.join(reportDir, 'failures.md'), markdown);
console.log(JSON.stringify({ scope, failures: failures.length }));
