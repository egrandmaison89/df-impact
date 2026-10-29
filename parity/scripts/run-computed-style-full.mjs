#!/usr/bin/env node
/**
 * All template samples × desktop/tablet/mobile, serial jobs, retry WP failures.
 *   unset PARITY_PATH
 *   DRUPAL_BASE_URL=http://127.0.0.1:8888 node scripts/run-computed-style-full.mjs
 */
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const runId = process.env.REPORT_RUN_ID || `full-cs-${Date.now()}`;
const outDir = path.join(parityRoot, 'reports', 'computed-style-diff', runId);
const maxRounds = Number(process.env.PARITY_FULL_RETRY_ROUNDS || 3);
const drupalBase = process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888';

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const samples = JSON.parse(
  fs.readFileSync(path.join(parityRoot, 'config', 'template-samples.json'), 'utf8'),
);
const bps = ['desktop', 'tablet', 'mobile'];
const jobs = [];
for (const [template, entry] of Object.entries(samples)) {
  for (const p of entry.paths) {
    for (const bp of bps) {
      jobs.push({ template, path: p, bp });
    }
  }
}

fs.mkdirSync(outDir, { recursive: true });
const envBase = {
  ...process.env,
  PARITY_SERIAL: '1',
  REPORT_RUN_ID: runId,
  DRUPAL_BASE_URL: drupalBase,
  PARITY_WP_SOURCE: process.env.PARITY_WP_SOURCE || 'refpack',
};
delete envBase.PARITY_TEMPLATE;
delete envBase.PARITY_BP;
delete envBase.PARITY_PATH;

function runJob(job) {
  const env = { ...envBase, PARITY_TEMPLATE: job.template, PARITY_PATH: job.path, PARITY_BP: job.bp };
  const jobTimeoutMs = Number(process.env.PARITY_JOB_TIMEOUT_MS || 600000);
  const r = spawnSync('node', ['scripts/computed-style-diff.mjs'], {
    cwd: parityRoot,
    env,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
    timeout: jobTimeoutMs,
  });
  return { code: r.status ?? 1, stderr: r.stderr };
}

function loadResult(job) {
  const slug = job.path === '/' ? 'root' : job.path.replace(/^\/|\/$/g, '').replace(/\//g, '_');
  const file = path.join(outDir, `${job.template}_${slug}_${job.bp}.json`);
  if (!fs.existsSync(file)) return null;
  return JSON.parse(fs.readFileSync(file, 'utf8'));
}

function needsRetry(report) {
  if (!report || report.error) return true;
  if (report.drStatus !== 200) return true;
  // WP ref-pack missing this path: Drupal-only result is final, not a transient failure.
  if (report.wpStatus === 404 && report.drStatus === 200) return false;
  if (!report.wpComparable && report.wpStatus !== 200) return true;
  return false;
}

async function main() {
  let pending = [...jobs];
  for (let round = 1; round <= maxRounds && pending.length; round++) {
    process.stderr.write(`\n=== round ${round}/${maxRounds} (${pending.length} jobs) ===\n`);
    const next = [];
    for (const job of pending) {
      process.stderr.write(`job ${job.template} ${job.path} ${job.bp}\n`);
      const { code, stderr } = runJob(job);
      if (code !== 0) process.stderr.write(stderr.slice(-400));
      const report = loadResult(job);
      if (needsRetry(report)) {
        next.push(job);
        process.stderr.write(
          `  retry: wp=${report?.wpStatus} comparable=${report?.wpComparable} dr=${report?.drStatus}\n`,
        );
      } else {
        process.stderr.write(`  ok mismatches=${report?.mismatchCount}\n`);
      }
      await sleep(Number(process.env.PARITY_INTER_JOB_MS || 1500));
    }
    pending = next;
    if (pending.length && round < maxRounds) await sleep(4000);
  }

  const results = jobs.map((job) => loadResult(job)).filter(Boolean);

  function sumByTemplateBp(bpFilter) {
    const sums = {};
    for (const r of results) {
      if (bpFilter && r.bp !== bpFilter) continue;
      if (!sums[r.template]) sums[r.template] = { sum: 0, paths: 0, comparableSum: 0 };
      sums[r.template].sum += r.mismatchCount ?? 0;
      sums[r.template].paths += 1;
      if (r.wpComparable) sums[r.template].comparableSum += r.mismatchCount ?? 0;
    }
    return sums;
  }

  const aggregate = {
    generatedAt: new Date().toISOString(),
    runId,
    drupalBase,
    jobCount: jobs.length,
    completed: results.length,
    stillPending: pending.map((j) => `${j.template}:${j.path}:${j.bp}`),
    desktopMismatchSumByTemplate: sumByTemplateBp('desktop'),
    allBpMismatchSumByTemplate: sumByTemplateBp(null),
    results: results.map((r) => ({
      template: r.template,
      path: r.path,
      bp: r.bp,
      mismatchCount: r.mismatchCount,
      wpStatus: r.wpStatus,
      drStatus: r.drStatus,
      wpComparable: r.wpComparable,
      error: r.error,
    })),
  };

  fs.writeFileSync(path.join(outDir, 'aggregate.json'), JSON.stringify(aggregate, null, 2));
  console.log(JSON.stringify(aggregate, null, 2));
  process.exit(pending.length ? 1 : 0);
}

main();
