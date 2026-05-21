<template>
  <div class="selectable-reimbursement-forms">
    <el-divider content-position="left">表单附件（内联填写）</el-divider>

    <el-alert
      v-if="hasUploadedAttachments"
      title="已上传附件，情况说明单已自动隐藏"
      type="success"
      :closable="false"
      style="margin-bottom: 12px"
    />
    <el-alert
      v-else
      title="未上传附件时，提交前必须填写【情况说明单】"
      type="warning"
      :closable="false"
      style="margin-bottom: 12px"
    />

    <el-form-item label="可选表单">
      <el-checkbox-group v-model="selectedOptionalForms">
        <el-checkbox value="payment">付款申请单</el-checkbox>
        <el-checkbox value="reimbursement">报销单</el-checkbox>
        <el-checkbox value="travelApplication">差旅申请单</el-checkbox>
        <el-checkbox value="travel">差旅费报销单</el-checkbox>
      </el-checkbox-group>
    </el-form-item>

    <el-card
      v-if="showSituationForm"
      class="inline-form-card"
      shadow="never"
      body-style="padding: 12px 12px 4px 12px"
    >
      <template #header>
        <div class="card-title">情况说明单</div>
      </template>
      <el-form ref="situationFormRef" :model="situationForm" :rules="situationRules" label-width="110px">
        <el-form-item label="公司名称" prop="companyName">
          <el-input v-model="situationForm.companyName" />
        </el-form-item>
        <el-form-item label="日期" prop="date">
          <el-date-picker
            v-model="situationForm.date"
            type="date"
            value-format="YYYY-MM-DD"
            format="YYYY-MM-DD"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="项目" prop="project">
          <el-input v-model="situationForm.project" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item label="事项" prop="matter">
          <el-input v-model="situationForm.matter" type="textarea" :rows="4" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="situationForm.remarks" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
    </el-card>

    <el-card
      v-if="selectedOptionalForms.includes('payment')"
      class="inline-form-card"
      shadow="never"
      body-style="padding: 12px 12px 4px 12px"
    >
      <template #header>
        <div class="card-title">付款申请单</div>
      </template>
      <el-form ref="paymentFormRef" :model="paymentForm" :rules="paymentRules" label-width="110px">
        <el-row :gutter="12">
          <el-col :span="12">
            <el-form-item label="部门" prop="department">
              <el-input v-model="paymentForm.department" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="申请日期" prop="applyDate">
              <el-date-picker
                v-model="paymentForm.applyDate"
                type="month"
                value-format="YYYY-MM"
                format="YYYY-MM"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="支付对象" prop="payee">
              <el-input v-model="paymentForm.payee" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="小写金额" prop="amountSmall">
              <el-input v-model="paymentForm.amountSmall" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="大写金额">
              <el-input v-model="paymentForm.amountLarge" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="付款方式" prop="paymentMethod">
              <el-select v-model="paymentForm.paymentMethod" style="width: 100%">
                <el-option label="转账" value="转账" />
                <el-option label="现金" value="现金" />
                <el-option label="电汇" value="电汇" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="开户行">
              <el-input v-model="paymentForm.bank" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="账号">
              <el-input v-model="paymentForm.bankAccount" />
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="用途" prop="purpose">
              <el-input v-model="paymentForm.purpose" type="textarea" :rows="2" />
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="备注">
              <el-input v-model="paymentForm.remarks" type="textarea" :rows="2" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
    </el-card>

    <el-card
      v-if="selectedOptionalForms.includes('reimbursement')"
      class="inline-form-card"
      shadow="never"
      body-style="padding: 12px 12px 4px 12px"
    >
      <template #header>
        <div class="card-title">报销单</div>
      </template>
      <el-form ref="reimbursementFormRef" :model="reimbursementForm" :rules="reimbursementRules" label-width="110px">
        <el-row :gutter="12">
          <el-col :span="12">
            <el-form-item label="公司名称" prop="companyName">
              <el-input v-model="reimbursementForm.companyName" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="日期" prop="date">
              <el-date-picker
                v-model="reimbursementForm.date"
                type="date"
                value-format="YYYY-MM-DD"
                format="YYYY-MM-DD"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="报销人" prop="applicant">
              <el-input v-model="reimbursementForm.applicant" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="开户行">
              <el-input v-model="reimbursementForm.bankName" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="卡号">
              <el-input v-model="reimbursementForm.cardNumber" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="支付日期">
              <el-date-picker
                v-model="reimbursementForm.paymentDate"
                type="date"
                value-format="YYYY-MM-DD"
                format="YYYY-MM-DD"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <div class="items-header">
              <span>报销明细</span>
              <el-button type="primary" link @click="addReimbursementItem">新增明细</el-button>
            </div>
            <div v-for="(item, idx) in reimbursementForm.items" :key="idx" class="line-item">
              <el-row :gutter="8">
                <el-col :span="13">
                  <el-input v-model="item.projectName" placeholder="项目名称" />
                </el-col>
                <el-col :span="9">
                  <el-input-number v-model="item.amount" :min="0" :precision="2" :controls="false" style="width: 100%" />
                </el-col>
                <el-col :span="2">
                  <el-button type="danger" link @click="removeReimbursementItem(idx)">删</el-button>
                </el-col>
              </el-row>
            </div>
          </el-col>
          <el-col :span="24">
            <el-form-item label="备注">
              <el-input v-model="reimbursementForm.remarks" type="textarea" :rows="2" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
    </el-card>

    <el-card
      v-if="selectedOptionalForms.includes('travelApplication')"
      class="inline-form-card"
      shadow="never"
      body-style="padding: 12px 12px 4px 12px"
    >
      <template #header>
        <div class="card-title">差旅申请单</div>
      </template>
      <el-form ref="travelApplicationFormRef" :model="travelApplicationForm" :rules="travelApplicationRules" label-width="110px">
        <el-row :gutter="12">
          <el-col :span="12">
            <el-form-item label="部门" prop="department">
              <el-input v-model="travelApplicationForm.department" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="申请日期" prop="applyDate">
              <el-date-picker v-model="travelApplicationForm.applyDate" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="姓名" prop="name">
              <el-input v-model="travelApplicationForm.name" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="出差地点" prop="destination">
              <el-input v-model="travelApplicationForm.destination" />
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="出差事由" prop="reason">
              <el-input v-model="travelApplicationForm.reason" type="textarea" :rows="2" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="起始时间" prop="startTime">
              <el-date-picker v-model="travelApplicationForm.startTime" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="结束时间" prop="endTime">
              <el-date-picker v-model="travelApplicationForm.endTime" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="天数">
              <el-input-number v-model="travelApplicationForm.days" :min="0" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="预支金额">
              <el-input-number v-model="travelApplicationForm.advanceAmountSmall" :min="0" :precision="2" :controls="false" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="备注">
              <el-input v-model="travelApplicationForm.remarks" type="textarea" :rows="2" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
    </el-card>

    <el-card
      v-if="selectedOptionalForms.includes('travel')"
      class="inline-form-card"
      shadow="never"
      body-style="padding: 12px 12px 4px 12px"
    >
      <template #header>
        <div class="card-title">差旅费报销单</div>
      </template>
      <el-form ref="travelFormRef" :model="travelForm" :rules="travelRules" label-width="110px">
        <el-row :gutter="12">
          <el-col :span="12">
            <el-form-item label="姓名" prop="name">
              <el-input v-model="travelForm.name" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="日期" prop="date">
              <el-date-picker v-model="travelForm.date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <div class="items-header">
              <span>差旅明细</span>
              <el-button type="primary" link @click="addTravelItem">新增明细</el-button>
            </div>
            <div v-for="(item, idx) in travelForm.items" :key="idx" class="line-item">
              <el-row :gutter="8">
                <el-col :span="6"><el-input v-model="item.date" placeholder="日期" /></el-col>
                <el-col :span="8"><el-input v-model="item.route" placeholder="线路" /></el-col>
                <el-col :span="6"><el-input v-model="item.transport" placeholder="交通方式" /></el-col>
                <el-col :span="3"><el-input-number v-model="item.amount" :min="0" :precision="2" :controls="false" style="width: 100%" /></el-col>
                <el-col :span="1"><el-button type="danger" link @click="removeTravelItem(idx)">删</el-button></el-col>
              </el-row>
            </div>
          </el-col>
          <el-col :span="8">
            <el-form-item label="预支金额">
              <el-input-number v-model="travelForm.advanceAmount" :min="0" :precision="2" :controls="false" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="报销金额">
              <el-input-number :model-value="travelReimbursementTotal" disabled :precision="2" :controls="false" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="应退金额">
              <el-input-number :model-value="travelRefundAmount" disabled :precision="2" :controls="false" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="报销说明">
              <el-input v-model="travelForm.reason" type="textarea" :rows="2" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
    </el-card>

    <div class="print-root">
      <div ref="situationPrintRef" class="print-sheet">
        <h2>情况说明单</h2>
        <table class="print-table">
          <tr><td>公司名称</td><td>{{ situationForm.companyName }}</td></tr>
          <tr><td>日期</td><td>{{ situationForm.date }}</td></tr>
          <tr><td>项目</td><td style="white-space: pre-wrap">{{ situationForm.project }}</td></tr>
          <tr><td>事项</td><td style="white-space: pre-wrap">{{ situationForm.matter }}</td></tr>
          <tr><td>备注</td><td style="white-space: pre-wrap">{{ situationForm.remarks }}</td></tr>
        </table>
      </div>

      <div ref="paymentPrintRef" class="print-sheet">
        <h2>付款申请单</h2>
        <table class="print-table">
          <tr><td>部门</td><td>{{ paymentForm.department }}</td><td>申请日期</td><td>{{ paymentForm.applyDate }}</td></tr>
          <tr><td>支付对象</td><td>{{ paymentForm.payee }}</td><td>付款方式</td><td>{{ paymentForm.paymentMethod }}</td></tr>
          <tr><td>小写金额</td><td>{{ paymentForm.amountSmall }}</td><td>大写金额</td><td>{{ paymentForm.amountLarge }}</td></tr>
          <tr><td>开户行</td><td>{{ paymentForm.bank }}</td><td>账号</td><td>{{ paymentForm.bankAccount }}</td></tr>
          <tr><td>用途</td><td colspan="3" style="white-space: pre-wrap">{{ paymentForm.purpose }}</td></tr>
          <tr><td>备注</td><td colspan="3" style="white-space: pre-wrap">{{ paymentForm.remarks }}</td></tr>
        </table>
      </div>

      <div ref="reimbursementPrintRef" class="print-sheet">
        <h2>报销单</h2>
        <table class="print-table">
          <tr><td>公司名称</td><td>{{ reimbursementForm.companyName }}</td><td>日期</td><td>{{ reimbursementForm.date }}</td></tr>
          <tr><td>报销人</td><td>{{ reimbursementForm.applicant }}</td><td>开户行</td><td>{{ reimbursementForm.bankName }}</td></tr>
          <tr><td>卡号</td><td>{{ reimbursementForm.cardNumber }}</td><td>支付日期</td><td>{{ reimbursementForm.paymentDate }}</td></tr>
          <tr><td>报销明细</td><td colspan="3">
            <div v-for="(item, idx) in reimbursementForm.items" :key="'p'+idx">{{ item.projectName }}：{{ formatAmount(item.amount) }}</div>
          </td></tr>
          <tr><td>备注</td><td colspan="3" style="white-space: pre-wrap">{{ reimbursementForm.remarks }}</td></tr>
        </table>
      </div>

      <div ref="travelApplicationPrintRef" class="print-sheet">
        <h2>差旅申请单</h2>
        <table class="print-table">
          <tr><td>部门</td><td>{{ travelApplicationForm.department }}</td><td>申请日期</td><td>{{ travelApplicationForm.applyDate }}</td></tr>
          <tr><td>姓名</td><td>{{ travelApplicationForm.name }}</td><td>出差地点</td><td>{{ travelApplicationForm.destination }}</td></tr>
          <tr><td>起始时间</td><td>{{ travelApplicationForm.startTime }}</td><td>结束时间</td><td>{{ travelApplicationForm.endTime }}</td></tr>
          <tr><td>天数</td><td>{{ travelApplicationForm.days }}</td><td>预支金额</td><td>{{ formatAmount(travelApplicationForm.advanceAmountSmall) }}</td></tr>
          <tr><td>出差事由</td><td colspan="3" style="white-space: pre-wrap">{{ travelApplicationForm.reason }}</td></tr>
          <tr><td>备注</td><td colspan="3" style="white-space: pre-wrap">{{ travelApplicationForm.remarks }}</td></tr>
        </table>
      </div>

      <div ref="travelPrintRef" class="print-sheet">
        <h2>差旅费报销单</h2>
        <table class="print-table">
          <tr><td>姓名</td><td>{{ travelForm.name }}</td><td>日期</td><td>{{ travelForm.date }}</td></tr>
          <tr><td>差旅明细</td><td colspan="3">
            <div v-for="(item, idx) in travelForm.items" :key="'t'+idx">
              {{ item.date }} {{ item.route }} {{ item.transport }} {{ formatAmount(item.amount) }}
            </div>
          </td></tr>
          <tr><td>预支金额</td><td>{{ formatAmount(travelForm.advanceAmount) }}</td><td>报销金额</td><td>{{ formatAmount(travelReimbursementTotal) }}</td></tr>
          <tr><td>应退金额</td><td>{{ formatAmount(travelRefundAmount) }}</td><td>报销说明</td><td style="white-space: pre-wrap">{{ travelForm.reason }}</td></tr>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import html2canvas from 'html2canvas'
import jsPDF from 'jspdf'

const props = defineProps({
  hasUploadedAttachments: {
    type: Boolean,
    default: false
  },
  baseInfo: {
    type: Object,
    default: () => ({})
  }
})

const selectedOptionalForms = ref([])

const showSituationForm = computed(() => !props.hasUploadedAttachments)

const defaultCompany = '鄂尔多斯市汇邦人力资源有限责任公司'
const today = () => new Date().toISOString().split('T')[0]

const situationFormRef = ref(null)
const paymentFormRef = ref(null)
const reimbursementFormRef = ref(null)
const travelApplicationFormRef = ref(null)
const travelFormRef = ref(null)

const situationForm = reactive({
  companyName: defaultCompany,
  date: today(),
  project: '',
  matter: '',
  remarks: ''
})

const paymentForm = reactive({
  department: '',
  applyDate: new Date().toISOString().slice(0, 7),
  payee: '',
  amountSmall: '',
  amountLarge: '',
  paymentMethod: '',
  bank: '',
  bankAccount: '',
  purpose: '',
  remarks: ''
})

const reimbursementForm = reactive({
  companyName: defaultCompany,
  date: today(),
  applicant: '',
  bankName: '',
  cardNumber: '',
  paymentDate: today(),
  remarks: '',
  items: [{ projectName: '', amount: 0 }]
})

const travelApplicationForm = reactive({
  department: '',
  applyDate: today(),
  name: '',
  reason: '',
  destination: '',
  startTime: '',
  endTime: '',
  days: 0,
  advanceAmountSmall: 0,
  remarks: ''
})

const travelForm = reactive({
  name: '',
  date: today(),
  items: [{ date: '', route: '', transport: '', amount: 0 }],
  advanceAmount: 0,
  reason: ''
})

const situationRules = {
  companyName: [{ required: true, message: '请输入公司名称', trigger: 'blur' }],
  date: [{ required: true, message: '请选择日期', trigger: 'change' }],
  project: [{ required: true, message: '请输入项目内容', trigger: 'blur' }],
  matter: [{ required: true, message: '请输入事项说明', trigger: 'blur' }]
}

const paymentRules = {
  department: [{ required: true, message: '请输入部门', trigger: 'blur' }],
  payee: [{ required: true, message: '请输入支付对象', trigger: 'blur' }],
  amountSmall: [{ required: true, message: '请输入小写金额', trigger: 'blur' }],
  paymentMethod: [{ required: true, message: '请选择付款方式', trigger: 'change' }],
  purpose: [{ required: true, message: '请输入用途', trigger: 'blur' }]
}

const reimbursementRules = {
  companyName: [{ required: true, message: '请输入公司名称', trigger: 'blur' }],
  date: [{ required: true, message: '请选择日期', trigger: 'change' }],
  applicant: [{ required: true, message: '请输入报销人', trigger: 'blur' }]
}

const travelApplicationRules = {
  department: [{ required: true, message: '请输入部门', trigger: 'blur' }],
  applyDate: [{ required: true, message: '请选择申请日期', trigger: 'change' }],
  name: [{ required: true, message: '请输入姓名', trigger: 'blur' }],
  reason: [{ required: true, message: '请输入出差事由', trigger: 'blur' }],
  destination: [{ required: true, message: '请输入出差地点', trigger: 'blur' }],
  startTime: [{ required: true, message: '请选择起始时间', trigger: 'change' }],
  endTime: [{ required: true, message: '请选择结束时间', trigger: 'change' }]
}

const travelRules = {
  name: [{ required: true, message: '请输入姓名', trigger: 'blur' }],
  date: [{ required: true, message: '请选择日期', trigger: 'change' }]
}

const travelReimbursementTotal = computed(() => {
  return travelForm.items.reduce((sum, item) => sum + Number(item.amount || 0), 0)
})

const travelRefundAmount = computed(() => {
  return Number(travelForm.advanceAmount || 0) - travelReimbursementTotal.value
})

const addReimbursementItem = () => {
  reimbursementForm.items.push({ projectName: '', amount: 0 })
}

const removeReimbursementItem = (index) => {
  if (reimbursementForm.items.length <= 1) return
  reimbursementForm.items.splice(index, 1)
}

const addTravelItem = () => {
  travelForm.items.push({ date: '', route: '', transport: '', amount: 0 })
}

const removeTravelItem = (index) => {
  if (travelForm.items.length <= 1) return
  travelForm.items.splice(index, 1)
}

const formatAmount = (val) => {
  return Number(val || 0).toFixed(2)
}

const situationPrintRef = ref(null)
const paymentPrintRef = ref(null)
const reimbursementPrintRef = ref(null)
const travelApplicationPrintRef = ref(null)
const travelPrintRef = ref(null)

const validateReimbursementItems = () => {
  if (!reimbursementForm.items.length) return false
  return reimbursementForm.items.every(item => item.projectName && Number(item.amount) > 0)
}

const validateTravelItems = () => {
  if (!travelForm.items.length) return false
  return travelForm.items.every(item => item.route && Number(item.amount) >= 0)
}

const buildPdfFile = async (elRef, fileName, orientation = 'p') => {
  await nextTick()
  const el = elRef?.value
  if (!el) {
    throw new Error(`未找到${fileName}的打印区域`)
  }

  const canvas = await html2canvas(el, {
    scale: 2,
    useCORS: true,
    backgroundColor: '#ffffff'
  })

  const imgData = canvas.toDataURL('image/png')
  const pdf = new jsPDF(orientation, 'mm', 'a4')
  const pageWidth = orientation === 'l' ? 297 : 210
  const pageHeight = orientation === 'l' ? 210 : 297
  const margin = 10
  const imgWidth = pageWidth - margin * 2
  const imgHeight = (canvas.height * imgWidth) / canvas.width

  let heightLeft = imgHeight
  let position = margin

  pdf.addImage(imgData, 'PNG', margin, position, imgWidth, imgHeight)
  heightLeft -= (pageHeight - margin * 2)

  while (heightLeft > 0) {
    position = heightLeft - imgHeight + margin
    pdf.addPage()
    pdf.addImage(imgData, 'PNG', margin, position, imgWidth, imgHeight)
    heightLeft -= (pageHeight - margin * 2)
  }

  const blob = pdf.output('blob')
  return new File([blob], fileName, { type: 'application/pdf' })
}

const generateSelectedFormPdfs = async ({ requireSituationWhenNoAttachment = false } = {}) => {
  const tasks = []

  if (showSituationForm.value && requireSituationWhenNoAttachment) {
    await situationFormRef.value?.validate()
    tasks.push({
      ref: situationPrintRef,
      fileName: `情况说明单_${Date.now()}.pdf`
    })
  }

  if (selectedOptionalForms.value.includes('payment')) {
    await paymentFormRef.value?.validate()
    tasks.push({
      ref: paymentPrintRef,
      fileName: `付款申请单_${Date.now()}.pdf`
    })
  }

  if (selectedOptionalForms.value.includes('reimbursement')) {
    await reimbursementFormRef.value?.validate()
    if (!validateReimbursementItems()) {
      throw new Error('报销单明细至少填写1条，且项目和金额必须完整')
    }
    tasks.push({
      ref: reimbursementPrintRef,
      fileName: `报销单_${Date.now()}.pdf`
    })
  }

  if (selectedOptionalForms.value.includes('travelApplication')) {
    await travelApplicationFormRef.value?.validate()
    tasks.push({
      ref: travelApplicationPrintRef,
      fileName: `差旅申请单_${Date.now()}.pdf`
    })
  }

  if (selectedOptionalForms.value.includes('travel')) {
    await travelFormRef.value?.validate()
    if (!validateTravelItems()) {
      throw new Error('差旅费报销单明细至少填写1条，且线路和金额必须完整')
    }
    tasks.push({
      ref: travelPrintRef,
      fileName: `差旅费报销单_${Date.now()}.pdf`,
      orientation: 'l'
    })
  }

  const files = []
  for (const task of tasks) {
    const file = await buildPdfFile(task.ref, task.fileName, task.orientation || 'p')
    files.push(file)
  }

  return files
}

const reset = () => {
  selectedOptionalForms.value = []

  Object.assign(situationForm, {
    companyName: defaultCompany,
    date: today(),
    project: '',
    matter: '',
    remarks: ''
  })

  Object.assign(paymentForm, {
    department: '',
    applyDate: new Date().toISOString().slice(0, 7),
    payee: '',
    amountSmall: '',
    amountLarge: '',
    paymentMethod: '',
    bank: '',
    bankAccount: '',
    purpose: '',
    remarks: ''
  })

  Object.assign(reimbursementForm, {
    companyName: defaultCompany,
    date: today(),
    applicant: '',
    bankName: '',
    cardNumber: '',
    paymentDate: today(),
    remarks: '',
    items: [{ projectName: '', amount: 0 }]
  })

  Object.assign(travelApplicationForm, {
    department: '',
    applyDate: today(),
    name: '',
    reason: '',
    destination: '',
    startTime: '',
    endTime: '',
    days: 0,
    advanceAmountSmall: 0,
    remarks: ''
  })

  Object.assign(travelForm, {
    name: '',
    date: today(),
    items: [{ date: '', route: '', transport: '', amount: 0 }],
    advanceAmount: 0,
    reason: ''
  })

  situationFormRef.value?.clearValidate()
  paymentFormRef.value?.clearValidate()
  reimbursementFormRef.value?.clearValidate()
  travelApplicationFormRef.value?.clearValidate()
  travelFormRef.value?.clearValidate()
}

watch(
  () => props.baseInfo,
  (val) => {
    if (!val) return
    if (val.companyName) {
      situationForm.companyName = val.companyName
      reimbursementForm.companyName = val.companyName
    }
    if (val.date) {
      situationForm.date = val.date
      reimbursementForm.date = val.date
      travelApplicationForm.applyDate = val.date
      travelForm.date = val.date
      reimbursementForm.paymentDate = val.date
    }
    if (val.project && !situationForm.project) {
      situationForm.project = val.project
    }
    if (val.applicant) {
      reimbursementForm.applicant = val.applicant
      travelApplicationForm.name = val.applicant
      travelForm.name = val.applicant
    }
    if (val.amount && !paymentForm.amountSmall) {
      paymentForm.amountSmall = `¥${Number(val.amount).toFixed(2)}`
    }
  },
  { deep: true, immediate: true }
)

watch(
  () => props.hasUploadedAttachments,
  (hasUploaded) => {
    if (hasUploaded) {
      situationFormRef.value?.clearValidate()
    }
  }
)

defineExpose({
  generateSelectedFormPdfs,
  reset
})
</script>

<style scoped>
.selectable-reimbursement-forms {
  margin-top: 10px;
}

.inline-form-card {
  margin-bottom: 12px;
}

.card-title {
  font-weight: 600;
  color: #303133;
}

.items-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
  color: #606266;
}

.line-item {
  margin-bottom: 6px;
}

.print-root {
  position: fixed;
  left: -99999px;
  top: -99999px;
  width: 210mm;
  background: #fff;
  z-index: -1;
}

.print-sheet {
  width: 190mm;
  padding: 8mm;
  color: #000;
  font-size: 12px;
}

.print-sheet h2 {
  margin: 0 0 8px 0;
  text-align: center;
  font-size: 20px;
}

.print-table {
  width: 100%;
  border-collapse: collapse;
}

.print-table td {
  border: 1px solid #000;
  padding: 6px;
  vertical-align: top;
}
</style>
