{{-- [IN]: Serialized registration payload from RaceCacheService / 来自 RaceCacheService 的报名序列化数据 --}}
{{-- [OUT]: Self-rendering receipt page that auto-downloads the PNG via html2canvas / 自渲染并通过 html2canvas 自动下载 PNG 的凭证页面 --}}
{{-- [POS]: Admin registration receipt download page / 后台报名明细下载页面 --}}
{{-- Protocol: When updating me, sync this header + parent folder's .folder.md --}}
{{-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md --}}
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>报名明细 - {{ $payload['race_name'] ?? '下载' }}</title>
<style>
  body {
    margin: 0;
    padding: 18px 12px 40px;
    background: #e7e9e8;
    font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Segoe UI", sans-serif;
  }

  .receipt-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 10px 14px;
    margin: 0 auto 14px;
    max-width: 750px;
  }

  .receipt-toolbar span {
    color: #4d5a53;
    font-size: 13px;
    font-weight: 700;
  }

  .receipt-toolbar button {
    min-height: 38px;
    border: 0;
    border-radius: 8px;
    padding: 0 16px;
    background: #0b7a4b;
    color: #fff;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
  }

  .receipt-toolbar button:disabled {
    cursor: not-allowed;
    opacity: .55;
  }

  .receipt-stage {
    display: grid;
    justify-content: center;
    overflow-x: auto;
  }

  .registration-receipt {
    width: 750px;
    padding: 28px 30px 32px;
    background: #fff;
    color: #1a211e;
    font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Segoe UI", sans-serif;
    font-size: 13px;
    line-height: 1.3;
    box-sizing: border-box;
    box-shadow: 0 5px 16px rgba(0, 0, 0, .13);
  }

  .receipt-heading {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 210px;
    align-items: end;
    gap: 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid #cfd9d4;
  }

  .receipt-heading h1 {
    margin: 0;
    font-size: 25px;
    font-weight: 850;
    line-height: 1.2;
    overflow-wrap: anywhere;
  }

  .receipt-total {
    display: grid;
    align-content: center;
    justify-items: end;
    color: #765000;
  }

  .receipt-total span {
    font-size: 11px;
    font-weight: 750;
  }

  .receipt-total strong {
    font-size: 27px;
    font-weight: 900;
    line-height: 1.05;
    white-space: nowrap;
  }

  .receipt-section {
    margin-top: 15px;
    break-inside: avoid;
  }

  .receipt-section h2 {
    margin: 0;
    padding: 7px 0 6px;
    border-top: 1px solid #cfd9d4;
    color: #34423b;
    font-size: 14px;
    font-weight: 850;
  }

  .receipt-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
  }

  .receipt-table th,
  .receipt-table td {
    border: 1px solid #b8c1bd;
    padding: 5px 6px;
    vertical-align: middle;
    overflow-wrap: anywhere;
  }

  .receipt-table th {
    background: #f0f3f1;
    color: #334039;
    font-weight: 800;
    text-align: center;
  }

  .receipt-table tbody tr:nth-child(even) td {
    background: #f8faf9;
  }

  .receipt-meta-table {
    margin-top: 8px;
    border-top: 1px solid #e0e6e3;
  }

  .receipt-meta-table th,
  .receipt-meta-table td {
    border: 0;
    border-bottom: 1px solid #e0e6e3;
    padding: 5px 7px;
    background: transparent;
  }

  .receipt-meta-table th {
    width: 86px;
    color: #6b7771;
    font-size: 11px;
    font-weight: 700;
    text-align: left;
  }

  .receipt-meta-table td {
    font-size: 12px;
    font-weight: 750;
  }

  .receipt-meta-table th:nth-child(3) {
    border-left: 1px solid #e0e6e3;
  }

  .receipt-summary-table th:nth-child(1) { width: 86px; }
  .receipt-summary-table th:nth-child(3) { width: 94px; }
  .receipt-summary-table th:nth-child(4) { width: 76px; }
  .receipt-summary-table th:nth-child(5) { width: 104px; }

  .receipt-summary-table {
    font-size: 11px;
  }

  .receipt-summary-table th,
  .receipt-summary-table td {
    padding: 4px 6px;
    border-color: #d1d9d5;
  }

  .receipt-single-table,
  .receipt-group-table {
    font-size: 11px;
    line-height: 1.25;
  }

  .receipt-single-table th,
  .receipt-single-table td,
  .receipt-group-table th,
  .receipt-group-table td {
    padding: 4px 5px;
  }

  .receipt-ring-column { width: 170px; }
  .receipt-count-column { width: 50px; }
  .receipt-amount-column { width: 88px; }
  .receipt-group-column { width: 76px; }
  .receipt-project-column { width: 96px; }

  .receipt-ring {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Microsoft YaHei", monospace;
    font-weight: 800;
    word-break: break-all;
  }

  .receipt-ring-list {
    line-height: 1.35;
  }

  .receipt-check {
    font-size: 14px;
    font-weight: 900;
    text-align: center;
  }

  .receipt-number {
    text-align: center;
    white-space: nowrap;
  }

  .receipt-strong {
    font-weight: 900;
  }
</style>
</head>
<body>
<div class="receipt-toolbar">
  <span id="statusText">正在生成报名明细图片…</span>
  <button id="downloadButton" type="button" disabled>下载报名明细图片</button>
</div>
<div class="receipt-stage">
  <article id="receipt" class="registration-receipt"></article>
</div>
<script src="/js/html2canvas.min.js"></script>
<script>
(function () {
  'use strict';

  // 以下整形逻辑移植自会员端 registrationHistory.ts / registrationReceipt.ts，保持一致 / Ported from the member app receipt shaping helpers.
  var payload = @json($payload);

  function yuan(cent) {
    return '¥' + (cent / 100).toLocaleString('zh-CN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }

  function statusText(status) {
    return status === 'confirmed' ? '已确认' : '未确认';
  }

  function buildSingleProjects(entries) {
    var projects = new Map();
    entries.forEach(function (entry) {
      if (!projects.has(entry.project_id)) {
        projects.set(entry.project_id, { id: entry.project_id, name: entry.project_name, price_cent: entry.price_cent });
      }
    });

    return Array.from(projects.values()).sort(function (a, b) { return a.id - b.id; });
  }

  function buildSingleRows(entries, projects) {
    var rows = new Map();
    var prices = new Map(projects.map(function (project) { return [project.id, project.price_cent]; }));

    entries.forEach(function (entry) {
      var ringNumber = entry.pigeons[0] && entry.pigeons[0].ring_number;
      if (!ringNumber) return;

      var row = rows.get(ringNumber) || { ring_number: ringNumber, selected_project_ids: {}, count: 0, amount_cent: 0 };
      if (!row.selected_project_ids[entry.project_id]) {
        row.selected_project_ids[entry.project_id] = true;
        row.count += 1;
        row.amount_cent += prices.get(entry.project_id) || entry.price_cent;
      }
      rows.set(ringNumber, row);
    });

    return Array.from(rows.values()).sort(function (a, b) { return a.ring_number.localeCompare(b.ring_number); });
  }

  function buildMultiProjects(entries) {
    var projects = new Map();

    entries.forEach(function (entry) {
      var project = projects.get(entry.project_id) || {
        project_id: entry.project_id,
        project_name: entry.project_name,
        group_size: entry.group_size,
        price_cent: entry.price_cent,
        groups: [],
        group_count: 0,
        amount_cent: 0,
      };

      project.groups.push({
        group_index: entry.group_index,
        rings: entry.pigeons.slice()
          .sort(function (a, b) { return a.sort_order - b.sort_order; })
          .map(function (pigeon) { return pigeon.ring_number; }),
      });
      project.group_count = project.groups.length;
      project.amount_cent = project.group_count * project.price_cent;
      projects.set(entry.project_id, project);
    });

    return Array.from(projects.values())
      .map(function (project) {
        project.groups.sort(function (a, b) { return a.group_index - b.group_index; });
        return project;
      })
      .sort(function (a, b) { return a.project_id - b.project_id; });
  }

  function buildProgressiveGroups(entries) {
    var groups = new Map();

    entries.forEach(function (entry) {
      var key = entry.category_id + ':' + entry.stage_project_id;
      var group = groups.get(key) || {
        category_id: entry.category_id,
        category_name: entry.category_name || '递进报名',
        stage_project_id: entry.stage_project_id,
        stage_project_name: entry.stage_project_name,
        price_cent: entry.price_cent,
        groups: [],
        count: 0,
        amount_cent: 0,
      };

      var groupKey = entry.group_key || String(entry.pigeon_id);
      var stageGroup = null;
      for (var i = 0; i < group.groups.length; i++) {
        if (group.groups[i].group_key === groupKey) { stageGroup = group.groups[i]; break; }
      }
      if (!stageGroup) {
        stageGroup = { group_key: groupKey, group_index: entry.group_index || group.groups.length + 1, rings: [], status: entry.status };
        group.groups.push(stageGroup);
      }
      stageGroup.rings.push(entry.ring_number);
      stageGroup.status = stageGroup.status === entry.status ? stageGroup.status : 'pending_confirmation';
      group.count = group.groups.length;
      group.amount_cent = group.count * group.price_cent;
      groups.set(key, group);
    });

    return Array.from(groups.values())
      .map(function (group) {
        group.groups.sort(function (a, b) { return a.group_index - b.group_index; });
        return group;
      })
      .sort(function (a, b) { return a.stage_project_id - b.stage_project_id; });
  }

  function buildMatrix(registration) {
    var singleEntries = registration.entries.filter(function (entry) { return entry.group_size === 1; });
    var singleProjects = buildSingleProjects(singleEntries);
    var singleRows = buildSingleRows(singleEntries, singleProjects);

    return {
      single: {
        projects: singleProjects,
        rows: singleRows,
        total_count: singleRows.reduce(function (sum, row) { return sum + row.count; }, 0),
        total_amount_cent: singleRows.reduce(function (sum, row) { return sum + row.amount_cent; }, 0),
      },
      multi: buildMultiProjects(registration.entries.filter(function (entry) { return entry.group_size > 1; })),
      progressive: buildProgressiveGroups(registration.progressive_entries || []),
    };
  }

  function buildReceipt(registration) {
    var matrix = buildMatrix(registration);
    var summaries = [];

    matrix.single.projects.forEach(function (project) {
      var quantity = matrix.single.rows.filter(function (row) { return row.selected_project_ids[project.id]; }).length;
      if (quantity > 0) {
        summaries.push({ category: '单羽组', project_name: project.name, unit_price_cent: project.price_cent, quantity: quantity, quantity_unit: '羽', amount_cent: quantity * project.price_cent });
      }
    });
    matrix.multi.forEach(function (project) {
      summaries.push({ category: '多羽组', project_name: project.project_name, unit_price_cent: project.price_cent, quantity: project.group_count, quantity_unit: '组', amount_cent: project.amount_cent });
    });
    matrix.progressive.forEach(function (project) {
      summaries.push({ category: '递进阶段', project_name: project.category_name + ' · ' + project.stage_project_name, unit_price_cent: project.price_cent, quantity: project.count, quantity_unit: '组', amount_cent: project.amount_cent });
    });

    return {
      race_name: registration.race_name,
      loft_number: registration.loft_number,
      participant_name: registration.participant_name,
      registration_no: registration.registration_no,
      submitted_at: registration.submitted_at,
      status: registration.status,
      total_amount_cent: registration.total_amount_cent,
      project_summaries: summaries,
      single: matrix.single,
      multi: matrix.multi,
      progressive: matrix.progressive,
    };
  }

  function receiptFileName(receipt) {
    var safeRaceName = (receipt.race_name || '').replace(/[\\/:*?"<>|\u0000-\u001F]/g, '-').replace(/\s+/g, ' ').trim() || '赛事';
    var safeRegistrationNo = (receipt.registration_no || '').replace(/[\\/:*?"<>|\u0000-\u001F]/g, '-').trim() || '报名';

    return safeRaceName + '-报名明细-' + safeRegistrationNo + '.png';
  }

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function renderReceipt(receipt) {
    var html = '';
    html += '<header class="receipt-heading"><h1>' + esc(receipt.race_name) + '</h1>';
    html += '<div class="receipt-total"><span>总金额</span><strong>' + esc(yuan(receipt.total_amount_cent)) + '</strong></div></header>';

    html += '<table class="receipt-table receipt-meta-table"><tbody>';
    html += '<tr><th>棚号</th><td>' + esc(receipt.loft_number) + '</td><th>参赛名</th><td>' + esc(receipt.participant_name) + '</td></tr>';
    html += '<tr><th>报名编号</th><td>' + esc(receipt.registration_no) + '</td><th>确认状态</th><td>' + esc(statusText(receipt.status)) + '</td></tr>';
    html += '<tr><th>报名时间</th><td colspan="3">' + esc(receipt.submitted_at) + '</td></tr>';
    html += '</tbody></table>';

    html += '<section class="receipt-section"><h2>项目汇总</h2>';
    html += '<table class="receipt-table receipt-summary-table"><thead><tr><th>类别</th><th>项目</th><th>单价</th><th>数量</th><th>项目金额</th></tr></thead><tbody>';
    receipt.project_summaries.forEach(function (summary) {
      html += '<tr><td>' + esc(summary.category) + '</td><td>' + esc(summary.project_name) + '</td>';
      html += '<td class="receipt-number">' + esc(yuan(summary.unit_price_cent)) + '</td>';
      html += '<td class="receipt-number">' + esc(summary.quantity + ' ' + summary.quantity_unit) + '</td>';
      html += '<td class="receipt-number receipt-strong">' + esc(yuan(summary.amount_cent)) + '</td></tr>';
    });
    html += '</tbody></table></section>';

    if (receipt.single.rows.length > 0) {
      html += '<section class="receipt-section"><h2>单羽组明细</h2>';
      html += '<table class="receipt-table receipt-single-table"><thead><tr><th class="receipt-ring-column">足环</th>';
      receipt.single.projects.forEach(function (project) { html += '<th>' + esc(project.name) + '</th>'; });
      html += '<th class="receipt-count-column">项数</th><th class="receipt-amount-column">行金额</th></tr></thead><tbody>';
      receipt.single.rows.forEach(function (row) {
        html += '<tr><td class="receipt-ring">' + esc(row.ring_number) + '</td>';
        receipt.single.projects.forEach(function (project) {
          html += '<td class="receipt-check">' + (row.selected_project_ids[project.id] ? '✓' : '') + '</td>';
        });
        html += '<td class="receipt-number">' + esc(row.count) + '</td>';
        html += '<td class="receipt-number receipt-strong">' + esc(yuan(row.amount_cent)) + '</td></tr>';
      });
      html += '</tbody></table></section>';
    }

    if (receipt.multi.length > 0) {
      html += '<section class="receipt-section"><h2>多羽组明细</h2>';
      html += '<table class="receipt-table receipt-group-table"><thead><tr><th class="receipt-project-column">项目</th><th class="receipt-group-column">组号</th><th>足环组合</th><th class="receipt-amount-column">组金额</th></tr></thead><tbody>';
      receipt.multi.forEach(function (project) {
        project.groups.forEach(function (group) {
          html += '<tr><td class="receipt-project-column">' + esc(project.project_name) + '</td>';
          html += '<td class="receipt-number">第 ' + esc(group.group_index) + ' 组</td>';
          html += '<td class="receipt-ring receipt-ring-list">' + esc(group.rings.join(' / ')) + '</td>';
          html += '<td class="receipt-number receipt-strong">' + esc(yuan(project.price_cent)) + '</td></tr>';
        });
      });
      html += '</tbody></table></section>';
    }

    if (receipt.progressive.length > 0) {
      html += '<section class="receipt-section"><h2>递进阶段明细</h2>';
      html += '<table class="receipt-table receipt-group-table"><thead><tr><th class="receipt-project-column">项目</th><th class="receipt-group-column">组号</th><th>足环组合</th><th class="receipt-amount-column">组金额</th></tr></thead><tbody>';
      receipt.progressive.forEach(function (project) {
        project.groups.forEach(function (group) {
          html += '<tr><td class="receipt-project-column">' + esc(project.category_name + ' · ' + project.stage_project_name) + '</td>';
          html += '<td class="receipt-number">第 ' + esc(group.group_index) + ' 组</td>';
          html += '<td class="receipt-ring receipt-ring-list">' + esc(group.rings.join(' / ')) + '</td>';
          html += '<td class="receipt-number receipt-strong">' + esc(yuan(project.price_cent)) + '</td></tr>';
        });
      });
      html += '</tbody></table></section>';
    }

    document.getElementById('receipt').innerHTML = html;
  }

  // 画布缩放逻辑移植自会员端 registrationReceiptExport.ts / Ported adaptive canvas scale from the member app.
  var TARGET_SCALE = 2;
  var MAX_CANVAS_EDGE = 16000;
  var MAX_CANVAS_PIXELS = 16000000;

  function receiptCanvasScale(width, height) {
    if (width <= 0 || height <= 0) return 1;

    return Math.max(Number.EPSILON, Math.min(
      TARGET_SCALE,
      MAX_CANVAS_EDGE / width,
      MAX_CANVAS_EDGE / height,
      Math.sqrt(MAX_CANVAS_PIXELS / (width * height))
    ));
  }

  var generating = false;

  function download() {
    if (generating) return;
    generating = true;

    var statusText = document.getElementById('statusText');
    var button = document.getElementById('downloadButton');
    var node = document.getElementById('receipt');
    statusText.textContent = '正在生成报名明细图片…';
    button.disabled = true;

    var width = Math.ceil(node.scrollWidth || node.getBoundingClientRect().width);
    var height = Math.ceil(node.scrollHeight || node.getBoundingClientRect().height);
    var preferredScale = receiptCanvasScale(width, height);
    var retryScale = preferredScale * 0.72;
    var scales = preferredScale === retryScale ? [preferredScale] : [preferredScale, retryScale];
    var fileName = receiptFileName(receiptData);

    var attempt = function (index) {
      if (index >= scales.length) {
        statusText.textContent = '报名明细图片生成失败，请重试';
        button.disabled = false;
        generating = false;
        return;
      }

      html2canvas(node, {
        backgroundColor: '#ffffff',
        height: height,
        logging: false,
        scale: scales[index],
        useCORS: true,
        width: width,
        windowWidth: Math.max(document.documentElement.clientWidth, width + 80),
      }).then(function (canvas) {
        canvas.toBlob(function (blob) {
          if (!blob) { attempt(index + 1); return; }

          var url = URL.createObjectURL(blob);
          var link = document.createElement('a');
          link.href = url;
          link.download = fileName;
          document.body.appendChild(link);
          link.click();
          link.remove();
          setTimeout(function () { URL.revokeObjectURL(url); }, 10000);

          statusText.textContent = '报名明细图片已下载，如未开始请再次点击按钮';
          button.disabled = false;
          generating = false;
        }, 'image/png');
      }).catch(function () {
        attempt(index + 1);
      });
    };

    attempt(0);
  }

  var receiptData = buildReceipt(payload);
  renderReceipt(receiptData);

  document.getElementById('downloadButton').addEventListener('click', download);

  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(function () { download(); });
  } else {
    download();
  }
})();
</script>
</body>
</html>
