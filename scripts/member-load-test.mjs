// [IN]: Provisioned synthetic member JSON and production origin URL / 已准备的合成会员 JSON 与生产源站 URL
// [OUT]: Read-flow and concurrent-submission latency/error report / 访问流程与并发提交延迟错误报告
// [POS]: Controlled production capacity-test runner / 受控生产容量测试运行器
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

import { readFile } from 'node:fs/promises'
import { randomUUID } from 'node:crypto'
import { performance } from 'node:perf_hooks'

class HttpError extends Error {
  constructor(step, response) {
    super(`${step} returned HTTP ${response.status}: ${response.body.slice(0, 240)}`)
    this.step = step
    this.status = response.status
  }
}

class CookieJar {
  #cookies = new Map()

  add(response) {
    const setCookies = typeof response.headers.getSetCookie === 'function'
      ? response.headers.getSetCookie()
      : this.#fallbackSetCookies(response.headers.get('set-cookie'))

    for (const setCookie of setCookies) {
      const separator = setCookie.indexOf(';')
      const pair = separator >= 0 ? setCookie.slice(0, separator) : setCookie
      const equals = pair.indexOf('=')
      if (equals <= 0) continue
      this.#cookies.set(pair.slice(0, equals), pair.slice(equals + 1))
    }
  }

  header() {
    return [...this.#cookies.entries()].map(([name, value]) => `${name}=${value}`).join('; ')
  }

  value(name) {
    return this.#cookies.get(name)
  }

  #fallbackSetCookies(value) {
    if (!value) return []
    return value.split(/,(?=[^;,]+=)/)
  }
}

function parseArgs(argv) {
  const options = {
    baseUrl: process.env.LOAD_TEST_BASE_URL ?? 'https://feilesaige.cn',
    data: process.env.LOAD_TEST_DATA ?? '',
    readRampSeconds: 60,
    submissionRampSeconds: 5,
    submitterCount: null,
    timeoutMs: 15000,
  }

  for (let index = 0; index < argv.length; index += 1) {
    const argument = argv[index]
    if (!argument.startsWith('--')) throw new Error(`无法识别参数：${argument}`)

    const [rawName, inlineValue] = argument.slice(2).split('=', 2)
    const name = rawName.replaceAll('-', '')
    const value = inlineValue ?? argv[++index]

    if (value === undefined) throw new Error(`参数 --${rawName} 缺少值。`)

    if (name === 'baseurl') options.baseUrl = value
    else if (name === 'data') options.data = value
    else if (name === 'readrampseconds') options.readRampSeconds = numberOption(rawName, value, 1, 300)
    else if (name === 'submissionrampseconds') options.submissionRampSeconds = numberOption(rawName, value, 0, 60)
    else if (name === 'submittercount') options.submitterCount = numberOption(rawName, value, 1, 1000)
    else if (name === 'timeoutms') options.timeoutMs = numberOption(rawName, value, 1000, 120000)
    else throw new Error(`无法识别参数：--${rawName}`)
  }

  if (!options.data) throw new Error('必须提供 --data <provision.json>，或设置 LOAD_TEST_DATA。')

  return options
}

function numberOption(name, value, minimum, maximum) {
  const parsed = Number(value)
  if (!Number.isInteger(parsed) || parsed < minimum || parsed > maximum) {
    throw new Error(`--${name} 必须是 ${minimum} 到 ${maximum} 之间的整数。`)
  }
  return parsed
}

function wait(milliseconds) {
  return new Promise((resolve) => setTimeout(resolve, milliseconds))
}

function p95(values) {
  if (values.length === 0) return null
  const sorted = [...values].sort((left, right) => left - right)
  return Math.round(sorted[Math.min(sorted.length - 1, Math.ceil(sorted.length * 0.95) - 1)])
}

function round(value) {
  return Math.round(value)
}

function summarize(name, results, wallMs, rampMs) {
  const durations = results.map((result) => result.total_ms)
  const errors = results.filter((result) => !result.ok).length

  return {
    name,
    total: results.length,
    success: results.length - errors,
    errors,
    error_rate: results.length === 0 ? 0 : Number((errors / results.length).toFixed(4)),
    p95_ms: p95(durations),
    max_ms: durations.length === 0 ? null : Math.max(...durations.map(round)),
    wall_ms: round(wallMs),
    ramp_ms: round(rampMs),
    results,
  }
}

function assertProvisionData(data) {
  if (!data || typeof data !== 'object') throw new Error('压测 JSON 不是对象。')
  if (!data.password && !process.env.LOAD_TEST_PASSWORD) throw new Error('JSON 中缺少 password，且未设置 LOAD_TEST_PASSWORD。')
  if (!data.race?.id || !data.race?.config_version || !data.project?.id) throw new Error('压测 JSON 缺少 race/project 配置。')
  if (!Array.isArray(data.members) || data.members.length === 0) throw new Error('压测 JSON 缺少 members。')
}

async function request(baseUrl, jar, method, path, timeoutMs, body = undefined) {
  const url = new URL(path, baseUrl)
  const headers = {
    Accept: 'application/json',
    Origin: baseUrl.origin,
    Referer: new URL('/login', baseUrl).toString(),
    'User-Agent': 'pigeon-racing-controlled-load-test/1.0',
  }
  const cookie = jar.header()
  if (cookie) headers.Cookie = cookie

  if (method !== 'GET') {
    headers['Content-Type'] = 'application/json'
    const xsrf = jar.value('XSRF-TOKEN')
    if (xsrf) headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf)
  }

  const startedAt = performance.now()
  const response = await fetch(url, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
    signal: AbortSignal.timeout(timeoutMs),
  })
  const text = await response.text()
  jar.add(response)

  return {
    status: response.status,
    ok: response.status >= 200 && response.status < 300,
    elapsedMs: performance.now() - startedAt,
    body: text,
  }
}

function assertResponse(step, response) {
  if (!response.ok) throw new HttpError(step, response)
}

async function readFlow(baseUrl, member, password, raceId, timeoutMs) {
  const startedAt = performance.now()
  const jar = new CookieJar()
  const steps = []

  try {
    const execute = async (step, method, path, body = undefined) => {
      const response = await request(baseUrl, jar, method, path, timeoutMs, body)
      steps.push({ step, status: response.status, elapsed_ms: round(response.elapsedMs) })
      assertResponse(step, response)
      return response
    }

    await execute('entry', 'GET', '/login')
    await execute('branding', 'GET', '/api/member/branding')
    await execute('csrf', 'GET', '/sanctum/csrf-cookie')
    await execute('login', 'POST', '/api/member/login', { phone: member.phone, password })
    await execute('races', 'GET', '/api/member/races')
    await execute('bootstrap', 'GET', `/api/member/races/${raceId}/bootstrap`)

    return {
      index: member.index,
      ok: true,
      total_ms: round(performance.now() - startedAt),
      steps,
    }
  } catch (error) {
    return {
      index: member.index,
      ok: false,
      total_ms: round(performance.now() - startedAt),
      error: error instanceof Error ? error.message : String(error),
      steps,
    }
  }
}

async function submitFlow(baseUrl, member, password, race, projectId, timeoutMs) {
  const startedAt = performance.now()
  const jar = new CookieJar()
  const steps = []

  try {
    const execute = async (step, method, path, body = undefined) => {
      const response = await request(baseUrl, jar, method, path, timeoutMs, body)
      steps.push({ step, status: response.status, elapsed_ms: round(response.elapsedMs) })
      assertResponse(step, response)
      return response
    }

    await execute('csrf', 'GET', '/sanctum/csrf-cookie')
    await execute('login', 'POST', '/api/member/login', { phone: member.phone, password })
    await execute('submit', 'POST', `/api/member/races/${race.id}/registrations`, {
      config_version: race.config_version,
      idempotency_key: randomUUID(),
      entries: [{ project_id: projectId, pigeon_ids: [member.pigeon_id] }],
      progressive_entries: [],
    })

    return {
      index: member.index,
      ok: true,
      total_ms: round(performance.now() - startedAt),
      steps,
    }
  } catch (error) {
    return {
      index: member.index,
      ok: false,
      total_ms: round(performance.now() - startedAt),
      error: error instanceof Error ? error.message : String(error),
      steps,
    }
  }
}

async function runPhase(name, items, rampSeconds, worker) {
  const rampMs = rampSeconds * 1000
  const intervalMs = items.length <= 1 ? 0 : rampMs / (items.length - 1)
  const phaseStartedAt = performance.now()
  const results = await Promise.all(items.map(async (item, index) => {
    const delayMs = intervalMs * index
    if (delayMs > 0) await wait(delayMs)
    const result = await worker(item)
    return { ...result, scheduled_delay_ms: round(delayMs) }
  }))

  return summarize(name, results, performance.now() - phaseStartedAt, rampMs)
}

async function main() {
  const options = parseArgs(process.argv.slice(2))
  const baseUrl = new URL(options.baseUrl.endsWith('/') ? options.baseUrl : `${options.baseUrl}/`)
  const data = JSON.parse(await readFile(options.data, 'utf8'))
  assertProvisionData(data)

  const password = data.password ?? process.env.LOAD_TEST_PASSWORD
  const members = data.members.slice(0, data.read_count ?? data.members.length)
  const submitterIndexes = (data.submitter_indexes ?? []).slice(0, options.submitterCount ?? data.submitter_indexes?.length ?? 0)
  const submitters = submitterIndexes.map((index) => data.members[index]).filter(Boolean)

  if (members.length === 0) throw new Error('没有可执行的读取会员。')
  if (submitters.some((member) => !member.pigeon_id)) throw new Error('至少一个提交会员缺少 pigeon_id。')

  const read = await runPhase(
    'member_read_flow',
    members,
    options.readRampSeconds,
    (member) => readFlow(baseUrl, member, password, data.race.id, options.timeoutMs),
  )
  const submissions = await runPhase(
    'concurrent_submissions',
    submitters,
    options.submissionRampSeconds,
    (member) => submitFlow(baseUrl, member, password, data.race, data.project.id, options.timeoutMs),
  )

  const report = {
    generated_at: new Date().toISOString(),
    base_url: baseUrl.origin,
    run_id: data.run_id,
    acceptance: {
      read_error_rate_lt_1_percent: read.error_rate < 0.01,
      read_p95_lt_2_seconds: read.p95_ms !== null && read.p95_ms < 2000,
      read_each_flow_lt_60_seconds: read.results.every((result) => result.total_ms < 60000),
      submission_error_rate_lt_1_percent: submissions.error_rate < 0.01,
      submission_p95_lt_3_seconds: submissions.p95_ms !== null && submissions.p95_ms < 3000,
      all_submission_results_successful: submissions.errors === 0,
    },
    read,
    submissions,
  }

  process.stdout.write(`${JSON.stringify(report, null, 2)}\n`)

  const passed = Object.values(report.acceptance).every(Boolean)
  process.exitCode = passed ? 0 : 1
}

main().catch((error) => {
  process.stderr.write(`${error instanceof Error ? error.message : String(error)}\n`)
  process.exitCode = 2
})
