import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const outputDir = path.join(root, 'storage', 'api-test-reports', 'inventory');
const sourceExtensions = ['.js', '.ts', '.mjs', '.vue', '.json'];
const ignoredReachableFiles = new Map([
  [path.join(root, 'src', 'views', 'TestIntegration.vue'), 'developer-only integration page'],
]);

const reachableFiles = new Set();
const importUsage = new Map();

function normalizeFile(file) {
  return path.normalize(file);
}

function addImportUsage(file, names) {
  const key = normalizeFile(file);
  const current = importUsage.get(key) || new Set();
  for (const name of names) current.add(name);
  importUsage.set(key, current);
}

function resolveImport(fromFile, specifier, appRoot) {
  if (!specifier.startsWith('.') && !specifier.startsWith('@/')) return null;

  const base = specifier.startsWith('@/')
    ? path.join(appRoot, specifier.slice(2))
    : path.resolve(path.dirname(fromFile), specifier);

  const candidates = [base];
  for (const extension of sourceExtensions) candidates.push(`${base}${extension}`);
  for (const extension of sourceExtensions) candidates.push(path.join(base, `index${extension}`));

  return candidates.find((candidate) => fs.existsSync(candidate) && fs.statSync(candidate).isFile()) || null;
}

function parseNamedImports(clause) {
  const match = clause.match(/\{([\s\S]*?)\}/);
  if (!match) return clause.includes('* as ') ? ['*'] : ['default'];

  return match[1]
    .split(',')
    .map((part) => part.trim().split(/\s+as\s+/)[0])
    .filter(Boolean);
}

function collectImports(content) {
  const imports = [];
  const staticImport = /import\s+([\s\S]*?)\s+from\s+['"]([^'"]+)['"]/g;
  const sideEffectImport = /import\s+['"]([^'"]+)['"]/g;
  const dynamicDestructure = /\{([\s\S]*?)\}\s*=\s*await\s+import\(\s*['"]([^'"]+)['"]\s*\)/g;
  const dynamicImport = /import\(\s*['"]([^'"]+)['"]\s*\)/g;
  let match;

  while ((match = staticImport.exec(content))) {
    imports.push({ specifier: match[2], names: parseNamedImports(match[1]) });
  }
  while ((match = sideEffectImport.exec(content))) {
    imports.push({ specifier: match[1], names: ['*'] });
  }
  while ((match = dynamicDestructure.exec(content))) {
    const names = match[1]
      .split(',')
      .map((part) => part.trim().split(/\s*:\s*/)[0])
      .filter(Boolean);
    imports.push({ specifier: match[2], names });
  }
  while ((match = dynamicImport.exec(content))) {
    if (!imports.some((entry) => entry.specifier === match[1])) {
      imports.push({ specifier: match[1], names: ['*'] });
    }
  }

  return imports;
}

function visit(file, appRoot) {
  const normalized = normalizeFile(file);
  if (reachableFiles.has(normalized) || ignoredReachableFiles.has(normalized)) return;
  reachableFiles.add(normalized);

  const content = fs.readFileSync(normalized, 'utf8');
  for (const entry of collectImports(content)) {
    const resolved = resolveImport(normalized, entry.specifier, appRoot);
    if (!resolved) continue;
    addImportUsage(resolved, entry.names);
    visit(resolved, appRoot);
  }
}

function findMatchingBrace(content, openIndex) {
  let depth = 0;
  let quote = null;
  let escaped = false;

  for (let index = openIndex; index < content.length; index += 1) {
    const char = content[index];
    if (escaped) {
      escaped = false;
      continue;
    }
    if (char === '\\') {
      escaped = true;
      continue;
    }
    if (quote) {
      if (char === quote) quote = null;
      continue;
    }
    if (char === '"' || char === "'" || char === '`') {
      quote = char;
      continue;
    }
    if (char === '{') depth += 1;
    if (char === '}') {
      depth -= 1;
      if (depth === 0) return index;
    }
  }

  return content.length - 1;
}

function exportedFunctionRanges(content) {
  const ranges = new Map();
  const patterns = [
    /export\s+(?:async\s+)?function\s+(\w+)\s*\([^)]*\)\s*\{/g,
    /export\s+const\s+(\w+)\s*=\s*(?:async\s*)?(?:\([^)]*\)|\w+)\s*=>\s*\{/g,
  ];

  for (const pattern of patterns) {
    let match;
    while ((match = pattern.exec(content))) {
      const openIndex = content.indexOf('{', match.index + match[0].lastIndexOf('{'));
      const closeIndex = findMatchingBrace(content, openIndex);
      ranges.set(match[1], { start: match.index, end: closeIndex + 1 });
    }
  }

  return ranges;
}

function selectedChunks(file, content) {
  const normalized = normalizeFile(file);
  const isApiModule = normalized.includes(`${path.sep}api${path.sep}`);
  if (!isApiModule) return [{ content, offset: 0 }];

  const usage = importUsage.get(normalized);
  if (!usage || usage.has('*') || usage.has('default')) return [{ content, offset: 0 }];

  const ranges = exportedFunctionRanges(content);
  const chunks = [];
  for (const name of usage) {
    const range = ranges.get(name);
    if (range) chunks.push({ content: content.slice(range.start, range.end), offset: range.start });
  }
  return chunks;
}

function lineNumber(content, index) {
  return content.slice(0, index).split(/\r?\n/).length;
}

function normalizeClientPath(rawPath, isMini) {
  let value = rawPath.trim();
  value = value.replace(/\\\//g, '/');
  value = value.replace(/^https?:\/\/[^/]+/i, '');
  value = value.replace(/^\$\{(?:BASE_URL|baseURL)\}/, '');
  value = value.split('?')[0].split('#')[0];
  value = value.replace(/^\/api\//, '/');
  value = value.replace(/^\//, '');
  value = value.replace(/\$\{[^}]+\}/g, '{param}');
  value = value.replace(/\/+/g, '/').replace(/\/$/, '');
  if (isMini && value && !value.startsWith('mini/')) value = `mini/${value}`;
  return value;
}

function expandRawPaths(rawPath) {
  let values = [''];
  let cursor = 0;
  const interpolation = /\$\{([^{}]+)\}/g;
  let match;

  while ((match = interpolation.exec(rawPath))) {
    const literal = rawPath.slice(cursor, match.index);
    const ternary = match[1].match(/\?\s*['"]([^'"]+)['"]\s*:\s*['"]([^'"]+)['"]/);
    const replacements = ternary ? [ternary[1], ternary[2]] : ['{param}'];
    values = values.flatMap((value) => replacements.map((replacement) => `${value}${literal}${replacement}`));
    cursor = match.index + match[0].length;
  }

  const suffix = rawPath.slice(cursor);
  return values.map((value) => `${value}${suffix}`);
}

function extractCalls(file) {
  const fullContent = fs.readFileSync(file, 'utf8');
  const isMini = file.startsWith(path.join(root, 'ht_qshu'));
  const calls = [];

  function addCall(method, rawPath, absoluteIndex) {
    if (!rawPath || rawPath.includes('storage/')) return;
    for (const expandedPath of expandRawPaths(rawPath)) {
      const normalizedPath = normalizeClientPath(expandedPath, isMini);
      if (!normalizedPath || normalizedPath.includes('://')) continue;
      calls.push({
        method: method.toUpperCase(),
        clientPath: normalizedPath,
        rawPath,
        source: path.relative(root, file).replace(/\\/g, '/'),
        line: lineNumber(fullContent, absoluteIndex),
      });
    }
  }

  for (const chunk of selectedChunks(file, fullContent)) {
    const directCall = /\b(?:request|axios|api)\.(get|post|put|patch|delete)\s*\(\s*([`'"])([\s\S]*?)\2/g;
    const fetchCall = /\bfetch\s*\(\s*([`'"])([\s\S]*?)\1\s*(?:,\s*\{([\s\S]{0,800}?)\})?/g;
    let match;

    while ((match = directCall.exec(chunk.content))) {
      addCall(match[1], match[3], chunk.offset + match.index);
    }
    let requestIndex = chunk.content.indexOf('request(');
    while (requestIndex !== -1) {
      const openIndex = chunk.content.indexOf('{', requestIndex + 'request('.length);
      if (openIndex === -1) break;
      const closeIndex = findMatchingBrace(chunk.content, openIndex);
      const objectBody = chunk.content.slice(openIndex + 1, closeIndex);
      const urlMatch = objectBody.match(/\burl\s*:\s*([`'"])([\s\S]*?)\1/);
      const methodMatch = objectBody.match(/\bmethod\s*:\s*['"](get|post|put|patch|delete)['"]/i);
      if (urlMatch && methodMatch) addCall(methodMatch[1], urlMatch[2], chunk.offset + requestIndex);
      requestIndex = chunk.content.indexOf('request(', closeIndex + 1);
    }
    while ((match = fetchCall.exec(chunk.content))) {
      const method = match[3]?.match(/\bmethod\s*:\s*['"](get|post|put|patch|delete)['"]/i)?.[1] || 'GET';
      addCall(method, match[2], chunk.offset + match.index);
    }
  }

  return calls;
}

function routeMatches(routePath, clientPath) {
  const routeSegments = routePath.split('/').filter(Boolean);
  const clientSegments = clientPath.split('/').filter(Boolean);
  const requiredRouteSegments = routeSegments.filter((segment) => !segment.endsWith('?}'));
  if (clientSegments.length < requiredRouteSegments.length || clientSegments.length > routeSegments.length) return false;

  for (let index = 0; index < clientSegments.length; index += 1) {
    const routeSegment = routeSegments[index];
    const clientSegment = clientSegments[index];
    const routeDynamic = /^\{[^}]+\??\}$/.test(routeSegment);
    const clientDynamic = /^\{[^}]+\}$/.test(clientSegment);
    if (!routeDynamic && !clientDynamic && routeSegment !== clientSegment) return false;
  }
  return true;
}

function routeScore(routePath, clientPath) {
  const routeSegments = routePath.split('/');
  const clientSegments = clientPath.split('/');
  return routeSegments.reduce((score, segment, index) => {
    if (!segment.startsWith('{') && segment === clientSegments[index]) return score + 2;
    return score + 1;
  }, 0);
}

visit(path.join(root, 'src', 'main.js'), path.join(root, 'src'));

const miniPages = JSON.parse(fs.readFileSync(path.join(root, 'ht_qshu', 'pages.json'), 'utf8'));
visit(path.join(root, 'ht_qshu', 'main.js'), path.join(root, 'ht_qshu'));
visit(path.join(root, 'ht_qshu', 'App.vue'), path.join(root, 'ht_qshu'));
for (const page of miniPages.pages || []) {
  visit(path.join(root, 'ht_qshu', `${page.path}.vue`), path.join(root, 'ht_qshu'));
}

const routeOutput = execFileSync('php', ['artisan', 'route:list', '--json'], {
  cwd: root,
  encoding: 'utf8',
  maxBuffer: 20 * 1024 * 1024,
});
const allRoutes = JSON.parse(routeOutput);
const apiRoutes = allRoutes
  .filter((route) => route.uri.startsWith('api/'))
  .flatMap((route) => route.method.split('|').filter((method) => method !== 'HEAD').map((method) => ({
    method,
    uri: route.uri.replace(/^api\//, ''),
    action: route.action,
    middleware: route.middleware,
  })));

const clientCalls = [...reachableFiles].flatMap(extractCalls);
const dedupedCalls = [...new Map(clientCalls.map((call) => [`${call.method} ${call.clientPath} ${call.source}:${call.line}`, call])).values()];
const usedRouteMap = new Map();
const unmatchedClientCalls = [];

for (const call of dedupedCalls) {
  const candidates = apiRoutes
    .filter((route) => route.method === call.method && routeMatches(route.uri, call.clientPath))
    .sort((left, right) => routeScore(right.uri, call.clientPath) - routeScore(left.uri, call.clientPath));
  const route = candidates[0];
  if (!route) {
    unmatchedClientCalls.push(call);
    continue;
  }

  const key = `${route.method} ${route.uri}`;
  const existing = usedRouteMap.get(key) || { ...route, evidence: [] };
  existing.evidence.push({ source: call.source, line: call.line, clientPath: call.clientPath });
  usedRouteMap.set(key, existing);
}

const usedRoutes = [...usedRouteMap.values()].sort((left, right) => `${left.uri} ${left.method}`.localeCompare(`${right.uri} ${right.method}`));
const usedKeys = new Set(usedRoutes.map((route) => `${route.method} ${route.uri}`));
const excludedRoutes = apiRoutes
  .filter((route) => !usedKeys.has(`${route.method} ${route.uri}`))
  .sort((left, right) => `${left.uri} ${left.method}`.localeCompare(`${right.uri} ${right.method}`));

const report = {
  generatedAt: new Date().toISOString(),
  assumptions: [
    'Only modules reachable from the active PC router and mini-program pages are considered in use.',
    'src/views/TestIntegration.vue is excluded as a developer-only page.',
    'Routes with no static client-call evidence are excluded from the test denominator and retained for review.',
  ],
  counts: {
    laravelApiMethodRoutes: apiRoutes.length,
    reachableSourceFiles: reachableFiles.size,
    clientCalls: dedupedCalls.length,
    usedRoutes: usedRoutes.length,
    unmatchedClientCalls: unmatchedClientCalls.length,
    excludedRoutes: excludedRoutes.length,
  },
  ignoredReachableFiles: [...ignoredReachableFiles.entries()].map(([file, reason]) => ({
    file: path.relative(root, file).replace(/\\/g, '/'),
    reason,
  })),
  usedRoutes,
  unmatchedClientCalls,
  excludedRoutes,
};

fs.mkdirSync(outputDir, { recursive: true });
fs.writeFileSync(path.join(outputDir, 'used-api-inventory.json'), `${JSON.stringify(report, null, 2)}\n`);

const domainCounts = new Map();
for (const route of usedRoutes) {
  const segments = route.uri.split('/');
  const domain = segments[0] === 'mini' ? `mini/${segments[1] || ''}` : segments[0];
  domainCounts.set(domain, (domainCounts.get(domain) || 0) + 1);
}

const markdown = [
  '# Used API Inventory',
  '',
  `Generated: ${report.generatedAt}`,
  '',
  `- Laravel API method routes: ${report.counts.laravelApiMethodRoutes}`,
  `- Reachable source files: ${report.counts.reachableSourceFiles}`,
  `- Static client calls: ${report.counts.clientCalls}`,
  `- Matched routes in use: ${report.counts.usedRoutes}`,
  `- Unmatched client calls: ${report.counts.unmatchedClientCalls}`,
  `- Routes excluded for no usage evidence: ${report.counts.excludedRoutes}`,
  '',
  '## Used routes by domain',
  '',
  '| Domain | Routes |',
  '| --- | ---: |',
  ...[...domainCounts.entries()].sort((left, right) => right[1] - left[1]).map(([domain, count]) => `| ${domain} | ${count} |`),
  '',
  '## Unmatched client calls',
  '',
  ...(unmatchedClientCalls.length
    ? unmatchedClientCalls.map((call) => `- ${call.method} ${call.clientPath} (${call.source}:${call.line})`)
    : ['None.']),
  '',
  'The JSON report contains the complete used-route evidence and excluded-route review list.',
  '',
].join('\n');

fs.writeFileSync(path.join(outputDir, 'used-api-inventory.md'), markdown);
console.log(JSON.stringify(report.counts));
