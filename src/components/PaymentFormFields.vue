<template>
  <div class="payment-form-fields">
    <div style="margin-bottom: 15px; text-align: right;">
      <el-button type="info" size="small" @click="fillTestData">填写测试数据</el-button>
    </div>
    
    <el-row :gutter="20">
      <el-col :span="12">
        <el-form-item label="申请日期">
          <el-date-picker
            v-model="formData.apply_date"
            type="date"
            placeholder="选择申请日期"
            format="YYYY-MM-DD"
            value-format="YYYY-MM-DD"
            style="width: 100%"
          />
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="单位名称">
          <el-input v-model="formData.unit_name" placeholder="请输入单位名称" />
        </el-form-item>
      </el-col>
    </el-row>

    <el-row :gutter="20">
      <el-col :span="12">
        <el-form-item label="报销人">
          <el-input v-model="formData.reimburser" placeholder="请输入报销人" />
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="发票号码">
          <el-input v-model="formData.invoice_number" placeholder="请输入发票号码" />
        </el-form-item>
      </el-col>
    </el-row>

    <el-row :gutter="20">
      <el-col :span="12">
        <el-form-item label="发票类型">
          <el-select v-model="formData.invoice_type" placeholder="请选择发票类型" style="width: 100%">
            <el-option label="专票" value="专票" />
            <el-option label="普票" value="普票" />
            <el-option label="通行费发票" value="通行费发票" />
            <el-option label="定额发票" value="定额发票" />
            <el-option label="票据" value="票据" />
            <el-option label="电子发票" value="电子发票" />
          </el-select>
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="开票金额">
          <el-input v-model="formData.invoice_amount" placeholder="请输入开票金额">
            <template #append>元</template>
          </el-input>
        </el-form-item>
      </el-col>
    </el-row>

    <el-row :gutter="20">
      <el-col :span="12">
        <el-form-item label="税率">
          <el-select v-model="formData.tax_rate" placeholder="请选择税率" style="width: 100%">
            <el-option label="1%" value="1%" />
            <el-option label="3%" value="3%" />
            <el-option label="5%" value="5%" />
            <el-option label="6%" value="6%" />
            <el-option label="9%" value="9%" />
            <el-option label="13%" value="13%" />
            <el-option label="免税" value="免税" />
          </el-select>
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="税金">
          <el-input v-model="formData.tax_amount" placeholder="请输入税金">
            <template #append>元</template>
          </el-input>
        </el-form-item>
      </el-col>
    </el-row>

    <el-row :gutter="20">
      <el-col :span="12">
        <el-form-item label="扣除额">
          <el-input v-model="formData.deduction_amount" placeholder="请输入扣除额">
            <template #append>元</template>
          </el-input>
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="不含税金额">
          <el-input v-model="formData.amount_excluding_tax" placeholder="请输入不含税金额">
            <template #append>元</template>
          </el-input>
        </el-form-item>
      </el-col>
    </el-row>

    <el-row :gutter="20">
      <el-col :span="12">
        <el-form-item label="打款日期">
          <el-date-picker
            v-model="formData.payment_date"
            type="date"
            placeholder="选择打款日期"
            format="YYYY-MM-DD"
            value-format="YYYY-MM-DD"
            style="width: 100%"
          />
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <!-- 空白占位 -->
      </el-col>
    </el-row>

    <el-form-item label="摘要">
      <el-input
        v-model="formData.summary"
        type="textarea"
        :rows="2"
        placeholder="请输入摘要"
      />
    </el-form-item>
  </div>
</template>

<script setup>
import { reactive, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['update:modelValue'])

// 表单数据
const formData = reactive({
  apply_date: '',
  unit_name: '',
  reimburser: '',
  invoice_number: '',
  invoice_type: '',
  invoice_amount: '',
  tax_rate: '',
  tax_amount: '',
  deduction_amount: '',
  amount_excluding_tax: '',
  payment_date: '',
  summary: ''
})

// 监听外部传入的值
watch(() => props.modelValue, (newVal) => {
  if (newVal) {
    Object.assign(formData, newVal)
  }
}, { immediate: true, deep: true })

// 监听内部变化，同步到外部
watch(formData, (newVal) => {
  emit('update:modelValue', { ...newVal })
}, { deep: true })

// 填写测试数据
const fillTestData = () => {
  formData.apply_date = new Date().toISOString().split('T')[0]
  formData.unit_name = '测试单位有限公司'
  formData.reimburser = '张三'
  formData.invoice_number = 'FP' + Date.now().toString().slice(-8)
  formData.invoice_type = '专票'
  formData.invoice_amount = '1000.00'
  formData.tax_rate = '6%'
  formData.tax_amount = '56.60'
  formData.deduction_amount = '0.00'
  formData.amount_excluding_tax = '943.40'
  formData.payment_date = new Date().toISOString().split('T')[0]
  formData.summary = '测试付款摘要'
}

// 重置表单
const resetForm = () => {
  formData.apply_date = ''
  formData.unit_name = ''
  formData.reimburser = ''
  formData.invoice_number = ''
  formData.invoice_type = ''
  formData.invoice_amount = ''
  formData.tax_rate = ''
  formData.tax_amount = ''
  formData.deduction_amount = ''
  formData.amount_excluding_tax = ''
  formData.payment_date = ''
  formData.summary = ''
}

// 获取表单数据（用于提交）
const getFormData = () => {
  return {
    applyDate: formData.apply_date,
    unitName: formData.unit_name,
    reimburser: formData.reimburser,
    invoiceNumber: formData.invoice_number,
    invoiceType: formData.invoice_type,
    invoiceAmount: formData.invoice_amount,
    taxRate: formData.tax_rate,
    taxAmount: formData.tax_amount,
    deductionAmount: formData.deduction_amount,
    amountExcludingTax: formData.amount_excluding_tax,
    paymentDate: formData.payment_date,
    summary: formData.summary
  }
}

// 暴露方法给父组件
defineExpose({
  resetForm,
  getFormData
})
</script>

<style scoped>
.payment-form-fields {
  margin-top: 10px;
}
</style>
