<!-- [IN]: Normalized registration receipt data / 已标准化的报名凭证数据 -->
<!-- [OUT]: Fixed-width print-style registration receipt DOM / 固定宽度的打印风报名凭证 DOM -->
<!-- [POS]: Shared registration receipt renderer / 共享报名凭证渲染器 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { registrationStatusText } from '../types/domain'
import type { RegistrationReceiptData } from '../utils/registrationReceipt'
import { yuan } from '../utils/money'

defineProps<{ receipt: RegistrationReceiptData }>()
</script>

<template>
  <article class="registration-receipt">
    <header class="receipt-heading">
      <h1>{{ receipt.race_name }}</h1>
      <div class="receipt-total">
        <span>总金额</span>
        <strong>{{ yuan(receipt.total_amount_cent) }}</strong>
      </div>
    </header>

    <table class="receipt-table receipt-meta-table">
      <tbody>
        <tr>
          <th>棚号</th>
          <td>{{ receipt.loft_number }}</td>
          <th>参赛名</th>
          <td>{{ receipt.participant_name }}</td>
        </tr>
        <tr>
          <th>报名编号</th>
          <td>{{ receipt.registration_no }}</td>
          <th>确认状态</th>
          <td>{{ registrationStatusText(receipt.status) }}</td>
        </tr>
        <tr>
          <th>报名时间</th>
          <td colspan="3">{{ receipt.submitted_at }}</td>
        </tr>
      </tbody>
    </table>

    <section class="receipt-section">
      <h2>项目汇总</h2>
      <table class="receipt-table receipt-summary-table">
        <thead>
          <tr>
            <th>类别</th>
            <th>项目</th>
            <th>单价</th>
            <th>数量</th>
            <th>项目金额</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="summary in receipt.project_summaries" :key="summary.category + '-' + summary.project_name">
            <td>{{ summary.category }}</td>
            <td>{{ summary.project_name }}</td>
            <td class="receipt-number">{{ yuan(summary.unit_price_cent) }}</td>
            <td class="receipt-number">{{ summary.quantity }} {{ summary.quantity_unit }}</td>
            <td class="receipt-number receipt-strong">{{ yuan(summary.amount_cent) }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section v-if="receipt.single.rows.length > 0" class="receipt-section">
      <h2>单羽组明细</h2>
      <table class="receipt-table receipt-single-table">
        <thead>
          <tr>
            <th class="receipt-ring-column">足环</th>
            <th v-for="project in receipt.single.projects" :key="project.id">{{ project.name }}</th>
            <th class="receipt-count-column">项数</th>
            <th class="receipt-amount-column">行金额</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in receipt.single.rows" :key="row.ring_number">
            <td class="receipt-ring">{{ row.ring_number }}</td>
            <td v-for="project in receipt.single.projects" :key="project.id" class="receipt-check">
              {{ row.selected_project_ids[project.id] ? '✓' : '' }}
            </td>
            <td class="receipt-number">{{ row.count }}</td>
            <td class="receipt-number receipt-strong">{{ yuan(row.amount_cent) }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section v-if="receipt.multi.length > 0" class="receipt-section">
      <h2>多羽组明细</h2>
      <table class="receipt-table receipt-group-table">
        <thead>
          <tr>
            <th class="receipt-project-column">项目</th>
            <th class="receipt-group-column">组号</th>
            <th>足环组合</th>
            <th class="receipt-amount-column">组金额</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="project in receipt.multi" :key="project.project_id">
            <tr v-for="group in project.groups" :key="project.project_id + '-' + group.group_index">
              <td class="receipt-project-column">{{ project.project_name }}</td>
              <td class="receipt-number">第 {{ group.group_index }} 组</td>
              <td class="receipt-ring receipt-ring-list">{{ group.rings.join(' / ') }}</td>
              <td class="receipt-number receipt-strong">{{ yuan(project.price_cent) }}</td>
            </tr>
          </template>
        </tbody>
      </table>
    </section>

    <section v-if="receipt.progressive.length > 0" class="receipt-section">
      <h2>递进阶段明细</h2>
      <table class="receipt-table receipt-group-table">
        <thead>
          <tr>
            <th class="receipt-project-column">项目</th>
            <th class="receipt-group-column">组号</th>
            <th>足环组合</th>
            <th class="receipt-amount-column">组金额</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="project in receipt.progressive" :key="project.category_id + '-' + project.stage_project_id">
            <tr v-for="group in project.groups" :key="project.stage_project_id + '-' + group.group_index">
              <td class="receipt-project-column">{{ project.category_name }} · {{ project.stage_project_name }}</td>
              <td class="receipt-number">第 {{ group.group_index }} 组</td>
              <td class="receipt-ring receipt-ring-list">{{ group.rings.join(' / ') }}</td>
              <td class="receipt-number receipt-strong">{{ yuan(project.price_cent) }}</td>
            </tr>
          </template>
        </tbody>
      </table>
    </section>
  </article>
</template>

<style scoped>
.registration-receipt {
  width: 750px;
  padding: 28px 30px 32px;
  background: #fff;
  color: #1a211e;
  font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Segoe UI", sans-serif;
  font-size: 13px;
  line-height: 1.3;
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
