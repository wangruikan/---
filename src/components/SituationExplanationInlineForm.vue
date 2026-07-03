<template>
  <div class="situation-inline-form">
    <el-divider content-position="left">情况说明单</el-divider>

    <el-alert
      v-if="skipRequired"
      title="已勾选稍后上传，情况说明单已自动隐藏"
      type="info"
      :closable="false"
      style="margin-bottom: 12px;"
    />
    <el-alert
      v-else-if="hasUploadedAttachments"
      title="已上传附件，情况说明单已自动隐藏"
      type="success"
      :closable="false"
      style="margin-bottom: 12px;"
    />
    <el-alert
      v-else
      title="未上传附件时，提交前必须填写情况说明单"
      type="warning"
      :closable="false"
      style="margin-bottom: 12px;"
    />

    <el-card v-if="showSituationForm" shadow="never" body-style="padding: 12px 12px 4px 12px">
      <el-form ref="formRef" :model="formData" :rules="rules" label-width="110px">
        <el-form-item label="公司名称" prop="companyName">
          <el-input v-model="formData.companyName" />
        </el-form-item>
        <el-form-item label="日期" prop="date">
          <el-date-picker
            v-model="formData.date"
            type="date"
            value-format="YYYY-MM-DD"
            format="YYYY-MM-DD"
            style="width: 100%;"
          />
        </el-form-item>
        <el-form-item label="项目" prop="project">
          <el-input v-model="formData.project" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item label="事项" prop="matter">
          <el-input v-model="formData.matter" type="textarea" :rows="4" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="formData.remarks" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
    </el-card>

    <div class="print-area">
      <div ref="printRef" class="print-sheet">
        <h2>情况说明单</h2>
        <table>
          <tr><td>公司名称</td><td>{{ formData.companyName }}</td></tr>
          <tr><td>日期</td><td>{{ formData.date }}</td></tr>
          <tr><td>项目</td><td style="white-space: pre-wrap;">{{ formData.project }}</td></tr>
          <tr><td>事项</td><td style="white-space: pre-wrap;">{{ formData.matter }}</td></tr>
          <tr><td>备注</td><td style="white-space: pre-wrap;">{{ formData.remarks }}</td></tr>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue'
import html2canvas from 'html2canvas'
import jsPDF from 'jspdf'

const props = defineProps({
  hasUploadedAttachments: {
    type: Boolean,
    default: false
  },
  skipRequired: {
    type: Boolean,
    default: false
  },
  baseInfo: {
    type: Object,
    default: () => ({})
  }
})

const defaultCompany = '鄂尔多斯市汇邦人力资源有限责任公司'
const today = () => new Date().toISOString().split('T')[0]

const formRef = ref(null)
const printRef = ref(null)

const formData = reactive({
  companyName: defaultCompany,
  date: today(),
  project: '',
  matter: '',
  remarks: ''
})

const rules = {
  companyName: [{ required: true, message: '请输入公司名称', trigger: 'blur' }],
  date: [{ required: true, message: '请选择日期', trigger: 'change' }],
  project: [{ required: true, message: '请输入项目内容', trigger: 'blur' }],
  matter: [{ required: true, message: '请输入事项说明', trigger: 'blur' }]
}

const showSituationForm = computed(() => !props.hasUploadedAttachments && !props.skipRequired)

const buildPdfFile = async (fileName) => {
  await nextTick()
  const el = printRef.value
  if (!el) {
    throw new Error('未找到情况说明单打印区域')
  }

  const canvas = await html2canvas(el, {
    scale: 2,
    useCORS: true,
    backgroundColor: '#ffffff'
  })

  const imgData = canvas.toDataURL('image/png')
  const pdf = new jsPDF('p', 'mm', 'a4')
  const pageWidth = 210
  const pageHeight = 297
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

const generateSituationPdfIfNeeded = async ({ requireWhenNoAttachment = false } = {}) => {
  if (!showSituationForm.value) {
    return null
  }

  if (!requireWhenNoAttachment) {
    return null
  }

  await formRef.value?.validate()
  return buildPdfFile(`情况说明单_${Date.now()}.pdf`)
}

const reset = () => {
  formData.companyName = defaultCompany
  formData.date = today()
  formData.project = ''
  formData.matter = ''
  formData.remarks = ''
  formRef.value?.clearValidate()
}

watch(
  () => props.baseInfo,
  (val) => {
    if (!val) return
    if (val.companyName) {
      formData.companyName = val.companyName
    }
    if (val.date) {
      formData.date = val.date
    }
    if (val.project && !formData.project) {
      formData.project = val.project
    }
    if (val.matter && !formData.matter) {
      formData.matter = val.matter
    }
    if (val.remarks && !formData.remarks) {
      formData.remarks = val.remarks
    }
  },
  { deep: true, immediate: true }
)

watch(
  () => [props.hasUploadedAttachments, props.skipRequired],
  ([hasUploaded, skipRequired]) => {
    if (hasUploaded || skipRequired) {
      formRef.value?.clearValidate()
    }
  }
)

defineExpose({
  generateSituationPdfIfNeeded,
  reset
})
</script>

<style scoped>
.situation-inline-form {
  margin-top: 10px;
}

.print-area {
  position: fixed;
  left: -9999px;
  top: 0;
  width: 800px;
  z-index: -1;
  opacity: 0;
  pointer-events: none;
}

.print-sheet {
  background: #ffffff;
  color: #000000;
  padding: 20px;
  box-sizing: border-box;
  border: 1px solid #e5e5e5;
}

.print-sheet h2 {
  text-align: center;
  margin: 0 0 14px;
  font-size: 22px;
}

.print-sheet table {
  width: 100%;
  border-collapse: collapse;
}

.print-sheet td {
  border: 1px solid #000000;
  padding: 8px;
  vertical-align: top;
  font-size: 14px;
}

.print-sheet td:first-child {
  width: 110px;
  font-weight: 600;
}
</style>
