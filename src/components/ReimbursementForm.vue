<template>
  <div style="margin-bottom: 15px; text-align: right;">
    <el-button 
      type="info" 
      :icon="MagicStick"
      @click="handleFillTestData"
      size="small"
    >
      填充示例
    </el-button>
  </div>
  <el-form :model="formData" :rules="rules" ref="formRef" label-width="120px">
    <!-- 项目 -->
    <el-form-item label="项目" prop="project">
      <el-select 
        v-model="formData.project" 
        placeholder="请选择项目" 
        style="width: 100%"
        filterable
      >
        <el-option
          v-for="proj in projectList"
          :key="proj.id"
          :label="proj.name"
          :value="proj.name"
        />
      </el-select>
    </el-form-item>

    <!-- 申请日期 -->
    <el-form-item label="申请日期" prop="applyDate">
      <el-date-picker
        v-model="formData.applyDate"
        type="date"
        placeholder="选择申请日期"
        format="YYYY-MM-DD"
        value-format="YYYY-MM-DD"
        style="width: 100%"
      />
    </el-form-item>

    <el-divider content-position="left">发票信息</el-divider>

    <!-- 单位名称 -->
    <el-form-item label="单位名称" prop="unitName">
      <el-input v-model="formData.unitName" placeholder="请输入单位名称" style="width: 100%" />
    </el-form-item>

    <!-- 发票号码 -->
    <el-form-item label="发票号码" prop="invoiceNumber">
      <el-input v-model="formData.invoiceNumber" placeholder="请输入发票号码" style="width: 100%" />
    </el-form-item>

    <!-- 查验 -->
    <el-form-item label="查验">
      <el-checkbox v-model="formData.verified" :checked="true">已查验</el-checkbox>
    </el-form-item>

    <!-- 打款日期 -->
    <el-form-item label="打款日期" prop="paymentDate">
      <el-date-picker
        v-model="formData.paymentDate"
        type="date"
        placeholder="选择打款日期"
        format="YYYY-MM-DD"
        value-format="YYYY-MM-DD"
        style="width: 100%"
      />
    </el-form-item>

    <!-- 支出金额 -->
    <el-form-item label="支出金额" prop="expenditureAmount">
      <el-input-number
        v-model="formData.expenditureAmount"
        :precision="2"
        :min="0"
        placeholder="请输入支出金额"
        style="width: 100%"
      />
    </el-form-item>

    <!-- 类目 -->
    <el-form-item label="类目" prop="category">
      <el-select v-model="formData.category" placeholder="请选择类目" style="width: 100%">
        <el-option label="报销" value="报销" />
        <el-option label="差旅" value="差旅" />
        <el-option label="采购" value="采购" />
        <el-option label="项目" value="项目" />
        <el-option label="其他" value="其他" />
      </el-select>
    </el-form-item>

    <!-- 项目 -->
    <el-form-item label="项目" prop="projectName">
      <el-input v-model="formData.projectName" placeholder="请输入项目名称" style="width: 100%" />
    </el-form-item>

    <!-- 摘要 -->
    <el-form-item label="摘要" prop="summary">
      <el-input
        v-model="formData.summary"
        type="textarea"
        :rows="2"
        placeholder="请输入摘要"
      />
    </el-form-item>

    <!-- 收到发票 -->
    <el-form-item label="收到发票">
      <el-checkbox v-model="formData.invoiceReceived">已收到发票</el-checkbox>
    </el-form-item>

    <!-- 发票类型 -->
    <el-form-item label="发票类型" prop="invoiceType">
      <el-select v-model="formData.invoiceType" placeholder="请选择发票类型" style="width: 100%">
        <el-option label="专票" value="专票" />
        <el-option label="普票" value="普票" />
        <el-option label="通行费发票" value="通行费发票" />
        <el-option label="定额发票" value="定额发票" />
        <el-option label="票据" value="票据" />
        <el-option label="电子发票" value="电子发票" />
      </el-select>
    </el-form-item>

    <!-- 开票金额 -->
    <el-form-item label="开票金额" prop="invoiceAmount">
      <el-input-number
        v-model="formData.invoiceAmount"
        :precision="2"
        :min="0"
        placeholder="请输入开票金额"
        style="width: 100%"
      />
    </el-form-item>

    <!-- 税率 -->
    <el-form-item label="税率" prop="taxRate">
      <el-select v-model="formData.taxRate" placeholder="请选择税率" style="width: 100%">
        <el-option label="1%" value="1%" />
        <el-option label="3%" value="3%" />
        <el-option label="5%" value="5%" />
        <el-option label="6%" value="6%" />
        <el-option label="9%" value="9%" />
        <el-option label="13%" value="13%" />
        <el-option label="免税" value="免税" />
      </el-select>
    </el-form-item>

    <!-- 扣除额 -->
    <el-form-item label="扣除额" prop="deductionAmount">
      <el-input-number
        v-model="formData.deductionAmount"
        :precision="2"
        :min="0"
        placeholder="请输入扣除额"
        style="width: 100%"
      />
    </el-form-item>

    <!-- 不含税金额 -->
    <el-form-item label="不含税金额" prop="amountExcludingTax">
      <el-input-number
        v-model="formData.amountExcludingTax"
        :precision="2"
        :min="0"
        placeholder="请输入不含税金额"
        style="width: 100%"
      />
    </el-form-item>

    <!-- 税金 -->
    <el-form-item label="税金" prop="taxAmount">
      <el-input-number
        v-model="formData.taxAmount"
        :precision="2"
        :min="0"
        placeholder="请输入税金"
        style="width: 100%"
      />
    </el-form-item>

    <!-- 是否一致 -->
    <el-form-item label="是否一致">
      <el-checkbox v-model="formData.isConsistent">一致</el-checkbox>
    </el-form-item>

    <!-- 状态 -->
    <el-form-item label="状态">
      <el-checkbox v-model="formData.status" :checked="true">已确认</el-checkbox>
    </el-form-item>

    <!-- 勾选月份 -->
    <el-form-item label="勾选月份">
      <el-date-picker
        v-model="formData.selectedMonth"
        type="month"
        placeholder="选择月份"
        format="YYYY-MM"
        value-format="YYYY-MM"
        style="width: 100%"
      />
    </el-form-item>

    <!-- 报销人 -->
    <el-form-item label="报销人" prop="reimburser">
      <el-input v-model="formData.reimburser" placeholder="请输入报销人" style="width: 100%" />
    </el-form-item>

    <!-- 开票日期 -->
    <el-form-item label="开票日期">
      <el-date-picker
        v-model="formData.invoiceDate"
        type="date"
        placeholder="选择开票日期"
        format="YYYY-MM-DD"
        value-format="YYYY-MM-DD"
        style="width: 100%"
      />
    </el-form-item>

    <!-- 入账 -->
    <el-form-item label="入账">
      <el-checkbox v-model="formData.accounted" :checked="true">已入账</el-checkbox>
    </el-form-item>

    <!-- 公司 -->
    <el-form-item label="公司">
      <el-input 
        v-model="formData.company" 
        placeholder="请输入公司名称"
        style="width: 100%"
      />
    </el-form-item>

  </el-form>
</template>

<script setup>
import { ref, reactive, watch } from 'vue'
import { MagicStick } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'

// Props
const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({})
  },
  projectList: {
    type: Array,
    default: () => []
  }
})

// Emits
const emit = defineEmits(['update:modelValue'])

// 表单引用
const formRef = ref(null)

// 表单数据
const formData = reactive({
  project: '',
  applyDate: '',
  // 发票信息字段
  unitName: '',
  invoiceNumber: '',
  verified: true,
  paymentDate: '',
  expenditureAmount: null,
  category: '',
  projectName: '',
  summary: '',
  invoiceReceived: false,
  invoiceType: '',
  invoiceAmount: null,
  taxRate: '',
  deductionAmount: null,
  amountExcludingTax: null,
  taxAmount: null,
  isConsistent: false,
  status: true,
  selectedMonth: '',
  reimburser: '',
  invoiceDate: '',
  accounted: true,
  company: '鄂尔多斯市汇邦人力资源有限责任公司'
})


// 表单验证规则
const rules = {
  project: [
    { required: true, message: '请选择项目', trigger: 'change' }
  ],
  applyDate: [
    { required: true, message: '请选择申请日期', trigger: 'change' }
  ],
  unitName: [
    { required: true, message: '请输入单位名称', trigger: 'blur' }
  ],
  reimburser: [
    { required: true, message: '请输入报销人', trigger: 'blur' }
  ],
  invoiceNumber: [
    { required: true, message: '请输入发票号码', trigger: 'blur' }
  ],
  invoiceType: [
    { required: true, message: '请选择发票类型', trigger: 'change' }
  ],
  invoiceAmount: [
    { required: true, message: '请输入开票金额', trigger: 'blur' }
  ],
  taxRate: [
    { required: true, message: '请选择税率', trigger: 'change' }
  ],
  taxAmount: [
    { required: true, message: '请输入税金', trigger: 'blur' }
  ],
  deductionAmount: [
    { required: true, message: '请输入扣除额', trigger: 'blur' }
  ],
  amountExcludingTax: [
    { required: true, message: '请输入不含税金额', trigger: 'blur' }
  ],
  paymentDate: [
    { required: true, message: '请选择打款日期', trigger: 'change' }
  ],
  expenditureAmount: [
    { required: true, message: '请输入支出金额', trigger: 'blur' }
  ],
  summary: [
    { required: true, message: '请输入摘要', trigger: 'blur' }
  ]
}

// 监听外部传入的值
watch(() => props.modelValue, (newVal) => {
  if (newVal && Object.keys(newVal).length > 0) {
    Object.assign(formData, newVal)
  }
}, { deep: true, immediate: true })

// 监听表单数据变化，同步到外部
watch(formData, (newVal) => {
  emit('update:modelValue', { ...newVal })
}, { deep: true })

// 填充示例数据
const handleFillTestData = () => {
  const today = new Date().toISOString().split('T')[0]
  const currentMonth = new Date().toISOString().slice(0, 7)
  
  Object.assign(formData, {
    project: props.projectList.length > 0 ? props.projectList[0].name : '',
    applyDate: today,
    unitName: '鄂尔多斯市汇邦人力资源有限责任公司',
    invoiceNumber: 'INV' + Date.now().toString().slice(-8),
    verified: true,
    paymentDate: today,
    expenditureAmount: 1000.00,
    category: '报销',
    projectName: props.projectList.length > 0 ? props.projectList[0].name : '测试项目',
    summary: '测试报销摘要',
    invoiceReceived: true,
    invoiceType: '专票',
    invoiceAmount: 1000.00,
    taxRate: '6%',
    deductionAmount: 0,
    amountExcludingTax: 943.40,
    taxAmount: 56.60,
    isConsistent: true,
    status: true,
    selectedMonth: currentMonth,
    reimburser: '测试报销人',
    invoiceDate: today,
    accounted: true,
    company: '鄂尔多斯市汇邦人力资源有限责任公司'
  })
  
  ElMessage.success('已填充示例数据')
}

// 暴露方法
defineExpose({
  formRef,
  formData,
  validate: () => formRef.value?.validate(),
  resetFields: () => {
    formRef.value?.resetFields()
    Object.assign(formData, {
      project: '',
      applyDate: '',
      unitName: '',
      invoiceNumber: '',
      verified: true,
      paymentDate: '',
      expenditureAmount: null,
      category: '',
      projectName: '',
      summary: '',
      invoiceReceived: false,
      invoiceType: '',
      invoiceAmount: null,
      taxRate: '',
      deductionAmount: null,
      amountExcludingTax: null,
      taxAmount: null,
      isConsistent: false,
      status: true,
      selectedMonth: '',
      reimburser: '',
      invoiceDate: '',
      accounted: true,
      company: '鄂尔多斯市汇邦人力资源有限责任公司'
    })
  },
  fillTestData: handleFillTestData
})
</script>

<style scoped>
/* 组件样式 */
</style>

