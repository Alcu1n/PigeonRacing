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
            <th>项目</th>
            <th class="receipt-group-column">组号</th>
            <th>足环组合</th>
            <th class="receipt-amount-column">组金额</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="project in receipt.multi" :key="project.project_id">
            <tr v-for="group in project.groups" :key="project.project_id + '-' + group.group_index">
              <td>{{ project.project_name }}</td>
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
            <th>项目</th>
            <th class="receipt-group-column">组号</th>
            <th>足环组合</th>
            <th class="receipt-amount-column">组金额</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="project in receipt.progressive" :key="project.category_id + '-' + project.stage_project_id">
            <tr v-for="group in project.groups" :key="project.stage_project_id + '-' + group.group_index">
              <td>{{ project.category_name }} · {{ project.stage_project_name }}</td>
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
  padding: 32px;
  background: #fff;
  color: #151515;
  font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Segoe UI", sans-serif;
  font-size: 14px;
  line-height: 1.35;
}

.receipt-heading {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 210px;
  align-items: stretch;
  gap: 18px;
  margin-bottom: 16px;
}

.receipt-heading h1 {
  display: flex;
  align-items: center;
  margin: 0;
  padding: 16px 18px;
  border: 2px solid #202020;
  font-size: 30px;
  font-weight: 900;
  line-height: 1.25;
  overflow-wrap: anywhere;
}

.receipt-total {
  display: grid;
  align-content: center;
  justify-items: end;
  padding: 14px 16px;
  border: 2px solid #bd7b05;
  background: #fff2cf;
  color: #875300;
}

.receipt-total span {
  font-size: 13px;
  font-weight: 800;
}

.receipt-total strong {
  font-size: 32px;
  font-weight: 950;
  line-height: 1.15;
  white-space: nowrap;
}

.receipt-section {
  margin-top: 20px;
  break-inside: avoid;
}

.receipt-section h2 {
  margin: 0;
  padding: 8px 10px;
  border: 1px solid #333;
  border-bottom: 0;
  background: #ececec;
  font-size: 17px;
  font-weight: 900;
}

.receipt-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
}

.receipt-table th,
.receipt-table td {
  border: 1px solid #565656;
  padding: 7px 8px;
  vertical-align: middle;
  overflow-wrap: anywhere;
}

.receipt-table th {
  background: #e7e7e7;
  font-weight: 900;
  text-align: center;
}

.receipt-table tbody tr:nth-child(even) td {
  background: #f7f7f7;
}

.receipt-meta-table th {
  width: 86px;
}

.receipt-meta-table td {
  font-weight: 800;
}

.receipt-summary-table th:nth-child(1) { width: 86px; }
.receipt-summary-table th:nth-child(3) { width: 94px; }
.receipt-summary-table th:nth-child(4) { width: 76px; }
.receipt-summary-table th:nth-child(5) { width: 104px; }

.receipt-single-table {
  font-size: 12px;
}

.receipt-ring-column { width: 170px; }
.receipt-count-column { width: 50px; }
.receipt-amount-column { width: 88px; }
.receipt-group-column { width: 76px; }

.receipt-ring {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Microsoft YaHei", monospace;
  font-weight: 800;
  word-break: break-all;
}

.receipt-ring-list {
  line-height: 1.55;
}

.receipt-check {
  font-size: 17px;
  font-weight: 950;
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
