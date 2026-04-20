<template>
  <div>
    <el-dialog
      v-model="dialogVisible"
      :title="title"
      width="750px"
      @close="handleClose"
    >
      <!-- 表单类型选择 -->
      <el-radio-group v-if="!props.onlyPayment && !props.onlySituation" v-model="formType" class="form-type-selector" @change="handleFormTypeChange">
        <el-radio-button value="situation">情况说明单</el-radio-button>
        <el-radio-button value="payment">付款申请单</el-radio-button>
        <el-radio-button value="reimbursement">报销单</el-radio-button>
        <el-radio-button value="travelApplication">差旅申请单</el-radio-button>
        <el-radio-button value="travel">差旅费报销单</el-radio-button>
      </el-radio-group>

      <!-- 已保存的表单列表 -->
      <el-alert
        v-if="savedForms.length > 0"
        type="success"
        :closable="false"
        style="margin-top: 15px;"
      >
        <template #title>
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <span style="font-weight: bold;">已保存 {{ savedForms.length }} 个表单</span>
            <el-button type="text" size="small" @click="clearSavedForms">清空所有</el-button>
          </div>
        </template>
        <div style="margin-top: 10px;">
          <el-tag
            v-for="(form, index) in savedForms"
            :key="index"
            type="success"
            closable
            @close="removeSavedForm(index)"
            style="margin-right: 10px; margin-bottom: 5px;"
          >
            {{ getFormTypeName(form.formType) }}
          </el-tag>
        </div>
      </el-alert>

      <!-- 情况说明单表单 -->
      <el-form 
        v-if="formType === 'situation'" 
        :model="situationForm" 
        :rules="situationRules" 
        ref="situationFormRef" 
        label-width="100px"
        style="margin-top: 20px;"
      >
        <el-form-item label="公司名称" prop="companyName">
          <el-input v-model="situationForm.companyName" placeholder="请输入公司名称" />
        </el-form-item>

        <el-form-item label="日期" prop="date">
          <el-date-picker
            v-model="situationForm.date"
            type="date"
            placeholder="选择日期"
            format="YYYY年MM月DD日"
            value-format="YYYY-MM-DD"
            style="width: 100%"
          />
        </el-form-item>

        <el-form-item label="项目" prop="project">
          <el-input
            v-model="situationForm.project"
            type="textarea"
            :rows="3"
            placeholder="请输入项目内容"
          />
        </el-form-item>

        <el-form-item label="事项" prop="matter">
          <el-input
            v-model="situationForm.matter"
            type="textarea"
            :rows="6"
            placeholder="请输入事项说明"
          />
        </el-form-item>

        <el-form-item label="备注">
          <el-input
            v-model="situationForm.remarks"
            type="textarea"
            :rows="3"
            placeholder="请输入备注信息（可选）"
          />
        </el-form-item>
      </el-form>

      <!-- 付款申请单表单 -->
      <el-form 
        v-if="formType === 'payment'" 
        :model="paymentForm" 
        :rules="paymentRules" 
        ref="paymentFormRef" 
        label-width="100px"
        style="margin-top: 20px;"
      >
        <el-form-item label="所在部门" prop="department">
          <el-input v-model="paymentForm.department" placeholder="请输入部门" />
        </el-form-item>

        <el-form-item label="申请日期" prop="applyDate">
          <el-date-picker
            v-model="paymentForm.applyDate"
            type="month"
            placeholder="选择年月"
            format="YYYY年MM月"
            value-format="YYYY-MM"
            style="width: 100%"
          />
        </el-form-item>

        <el-form-item label="支付对象" prop="payee">
          <el-input v-model="paymentForm.payee" placeholder="请输入支付对象" />
        </el-form-item>

        <el-form-item label="支付金额">
          <el-row :gutter="10">
            <el-col :span="12">
              <el-form-item prop="amountSmall">
                <el-input v-model="paymentForm.amountSmall" placeholder="小写金额">
                  <template #prepend>小写：</template>
                </el-input>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item prop="amountLarge">
                <el-input v-model="paymentForm.amountLarge" placeholder="大写金额">
                  <template #prepend>大写：</template>
                </el-input>
              </el-form-item>
            </el-col>
          </el-row>
        </el-form-item>

        <el-form-item label="付款方式" prop="paymentMethod">
          <el-radio-group v-model="paymentForm.paymentMethod">
            <el-radio label="现金">现金</el-radio>
            <el-radio label="转账">转账</el-radio>
            <el-radio label="支票">支票</el-radio>
            <el-radio label="电汇">电汇</el-radio>
            <el-radio label="承兑">承兑</el-radio>
            <el-radio label="其他">其他</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="开户行">
          <el-input v-model="paymentForm.bank" placeholder="请输入开户行" />
        </el-form-item>

        <el-form-item label="银行账号">
          <el-autocomplete
            v-model="paymentForm.bankAccount"
            :fetch-suggestions="queryHistoryAccounts"
            placeholder="请输入银行账号（可选择历史记录）"
            style="width: 100%"
            @select="handleSelectHistoryAccount"
            clearable
          >
            <template #default="{ item }">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-weight: 500;">{{ item.value }}</span>
                <span style="font-size: 12px; color: #999; margin-left: 10px;">{{ item.payee }}</span>
              </div>
            </template>
          </el-autocomplete>
        </el-form-item>

        <el-form-item label="付款用途">
          <el-input 
            v-model="paymentForm.purpose" 
            type="textarea"
            :rows="3"
            placeholder="请输入付款用途"
          />
        </el-form-item>

        <el-form-item label="开票情况">
          <el-radio-group v-model="paymentForm.invoiceStatus">
            <el-radio label="已开票">已开票</el-radio>
            <el-radio label="未开票">未开票</el-radio>
            <el-radio label="其他">其他</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="备注">
          <el-input 
            v-model="paymentForm.remarks" 
            type="textarea"
            :rows="2"
            placeholder="请输入备注信息（可选）"
          />
        </el-form-item>
      </el-form>

      <!-- 报销单表单 -->
      <el-form 
        v-if="formType === 'reimbursement'" 
        :model="reimbursementForm" 
        :rules="reimbursementRules" 
        ref="reimbursementFormRef" 
        label-width="100px"
        style="margin-top: 20px;"
      >
        <el-form-item label="公司名称" prop="companyName">
          <el-input v-model="reimbursementForm.companyName" placeholder="请输入公司名称" />
        </el-form-item>

        <el-form-item label="日期" prop="date">
          <el-date-picker
            v-model="reimbursementForm.date"
            type="date"
            placeholder="选择日期"
            format="YYYY年MM月DD日"
            value-format="YYYY-MM-DD"
            style="width: 100%"
          />
        </el-form-item>

        <el-divider content-position="left">报销项目</el-divider>

        <!-- 动态报销项目列表 -->
        <div v-for="(item, index) in reimbursementForm.items" :key="index" style="margin-bottom: 15px; padding: 15px; border: 1px solid #EBEEF5; border-radius: 4px; background-color: #F5F7FA;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <span style="font-weight: bold; color: #409EFF;">项目 {{ index + 1 }}</span>
            <el-button 
              type="danger" 
              size="small" 
              :icon="Delete" 
              circle
              @click="removeReimbursementItem(index)"
            />
          </div>
          
          <el-form-item :label="`报销内容`" style="margin-bottom: 10px;">
            <el-select 
              v-model="item.projectId" 
              placeholder="请选择项目" 
              style="width: 100%"
              @change="(value) => handleProjectChange(index, value)"
            >
              <el-option
                v-for="project in invoiceProjects"
                :key="project.id"
                :label="project.name"
                :value="project.id"
              />
            </el-select>
            <div v-if="item.projectName" style="margin-top: 5px; color: #909399; font-size: 12px;">
              已选择：{{ item.projectName }}
            </div>
          </el-form-item>

          <el-form-item :label="`报销金额`" style="margin-bottom: 0;">
            <el-input-number 
              v-model="item.amount" 
              :precision="2" 
              :min="0"
              :controls="false"
              placeholder="请输入金额"
              style="width: 100%"
            />
          </el-form-item>
        </div>

        <el-button 
          type="primary" 
          :icon="Plus" 
          style="width: 100%; margin-bottom: 20px;"
          @click="addReimbursementItem"
        >
          添加报销项目
        </el-button>

        <el-divider content-position="left">报销人信息</el-divider>

        <el-form-item label="报销人" prop="applicant">
          <el-input v-model="reimbursementForm.applicant" placeholder="请输入报销人" />
        </el-form-item>

        <el-form-item label="开户行">
          <el-input v-model="reimbursementForm.bankName" placeholder="请输入开户行" />
        </el-form-item>

        <el-form-item label="卡号">
          <el-input v-model="reimbursementForm.cardNumber" placeholder="请输入卡号" />
        </el-form-item>

        <el-form-item label="备注">
          <el-input
            v-model="reimbursementForm.remarks"
            type="textarea"
            :rows="3"
            placeholder="请输入备注信息"
          />
        </el-form-item>

        <el-divider content-position="left">其他信息</el-divider>

        <el-form-item label="公司">
          <el-input v-model="reimbursementForm.company" placeholder="请输入公司" />
        </el-form-item>

        <el-form-item label="">
          <el-row :gutter="20">
            <el-col :span="8">
              <div style="display: flex; align-items: center; gap: 8px;">
                <el-checkbox v-model="reimbursementForm.verified" :checked="true">
                  查验
                </el-checkbox>
              </div>
            </el-col>
            <el-col :span="8">
              <div style="display: flex; align-items: center; gap: 8px;">
                <el-checkbox v-model="reimbursementForm.status" :checked="true">
                  状态
                </el-checkbox>
              </div>
            </el-col>
            <el-col :span="8">
              <div style="display: flex; align-items: center; gap: 8px;">
                <el-checkbox v-model="reimbursementForm.accounted" :checked="true">
                  入账
                </el-checkbox>
              </div>
            </el-col>
          </el-row>
        </el-form-item>
      </el-form>

      <!-- 差旅申请单表单 -->
      <el-form 
        v-if="formType === 'travelApplication'" 
        :model="travelApplicationForm" 
        :rules="travelApplicationRules" 
        ref="travelApplicationFormRef" 
        label-width="100px"
        style="margin-top: 20px;"
      >
        <el-form-item label="所在部门" prop="department">
          <el-input v-model="travelApplicationForm.department" placeholder="请输入所在部门" />
        </el-form-item>

        <el-form-item label="申请日期" prop="applyDate">
          <el-date-picker
            v-model="travelApplicationForm.applyDate"
            type="date"
            placeholder="选择申请日期"
            format="YYYY年MM月DD日"
            value-format="YYYY-MM-DD"
            style="width: 100%"
          />
        </el-form-item>

        <el-form-item label="姓名" prop="name">
          <el-input v-model="travelApplicationForm.name" placeholder="请输入姓名" />
        </el-form-item>

        <el-form-item label="事由" prop="reason">
          <el-input
            v-model="travelApplicationForm.reason"
            type="textarea"
            :rows="3"
            placeholder="请输入出差事由"
          />
        </el-form-item>

        <el-form-item label="出差地" prop="destination">
          <el-input v-model="travelApplicationForm.destination" placeholder="请输入出差地点" />
        </el-form-item>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="起始时间" prop="startTime">
              <el-date-picker
                v-model="travelApplicationForm.startTime"
                type="datetime"
                placeholder="选择起始时间"
                format="YYYY年MM月DD日 HH时"
                value-format="YYYY-MM-DD HH:mm:ss"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>

          <el-col :span="12">
            <el-form-item label="结束时间" prop="endTime">
              <el-date-picker
                v-model="travelApplicationForm.endTime"
                type="datetime"
                placeholder="选择结束时间"
                format="YYYY年MM月DD日 HH时"
                value-format="YYYY-MM-DD HH:mm:ss"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="计划天数">
          <el-input-number 
            v-model="travelApplicationForm.days" 
            :min="0" 
            :controls="false"
            placeholder="自动计算"
            style="width: 100%"
            disabled
          />
        </el-form-item>

        <el-form-item label="预支差旅费">
          <el-row :gutter="10">
            <el-col :span="12">
              <el-input-number 
                v-model="travelApplicationForm.advanceAmountSmall" 
                :min="0" 
                :precision="2"
                :controls="false"
                placeholder="小写金额"
                style="width: 100%"
              >
                <template #prepend>小写</template>
              </el-input-number>
            </el-col>
            <el-col :span="12">
              <el-input v-model="travelApplicationForm.advanceAmountLarge" placeholder="自动转换" disabled>
                <template #prepend>大写</template>
              </el-input>
            </el-col>
          </el-row>
        </el-form-item>

        <el-form-item label="备注">
          <el-input
            v-model="travelApplicationForm.remarks"
            type="textarea"
            :rows="3"
            placeholder="请输入备注信息（可选）"
          />
        </el-form-item>
      </el-form>

      <!-- 差旅费报销单表单 -->
      <el-form 
        v-if="formType === 'travel'" 
        :model="travelForm" 
        :rules="travelRules" 
        ref="travelFormRef" 
        label-width="100px"
        style="margin-top: 20px;"
      >
        <el-form-item label="姓名" prop="name">
          <el-input v-model="travelForm.name" placeholder="请输入姓名" />
        </el-form-item>

        <el-form-item label="日期" prop="date">
          <el-date-picker
            v-model="travelForm.date"
            type="date"
            placeholder="选择日期"
            format="YYYY年MM月DD日"
            value-format="YYYY-MM-DD"
            style="width: 100%"
          />
        </el-form-item>

        <el-divider content-position="left">差旅明细</el-divider>

        <!-- 动态差旅明细列表 -->
        <div v-for="(item, index) in travelForm.items" :key="index" style="margin-bottom: 20px; padding: 15px; border: 1px solid #EBEEF5; border-radius: 4px; background-color: #F5F7FA;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <span style="font-weight: bold; color: #409EFF;">差旅明细 {{ index + 1 }}</span>
            <el-button 
              type="danger" 
              size="small" 
              :icon="Delete" 
              circle
              @click="removeTravelItem(index)"
            />
          </div>
          
          <!-- 起止日期 -->
          <el-row :gutter="20">
            <el-col :span="8">
              <el-form-item label="起始日期">
                <el-date-picker
                  v-model="item.startDate"
                  type="date"
                  placeholder="选择起始日期"
                  format="MM月DD日"
                  value-format="YYYY-MM-DD"
                  style="width: 100%"
                />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="结束日期">
                <el-date-picker
                  v-model="item.endDate"
                  type="date"
                  placeholder="选择结束日期"
                  format="MM月DD日"
                  value-format="YYYY-MM-DD"
                  style="width: 100%"
                />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="合计天数">
                <el-input-number 
                  v-model="item.totalDays" 
                  :min="0" 
                  :controls="false"
                  placeholder="自动计算"
                  style="width: 100%"
                  disabled
                />
              </el-form-item>
            </el-col>
          </el-row>

          <!-- 伙食补助 -->
          <el-row :gutter="20">
            <el-col :span="8">
              <el-form-item label="伙食天数">
                <el-input-number v-model="item.mealDays" :min="0" :controls="false" style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="伙食标准">
                <el-input-number v-model="item.mealStandard" :min="0" :precision="2" :controls="false" style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="伙食金额">
                <el-input-number v-model="item.mealAmount" :min="0" :precision="2" :controls="false" style="width: 100%" disabled />
              </el-form-item>
            </el-col>
          </el-row>

          <!-- 住宿补助 -->
          <el-row :gutter="20">
            <el-col :span="8">
              <el-form-item label="住宿天数">
                <el-input-number v-model="item.accommodationDays" :min="0" :controls="false" style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="住宿标准">
                <el-input-number v-model="item.accommodationStandard" :min="0" :precision="2" :controls="false" style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="住宿金额">
                <el-input-number v-model="item.accommodationAmount" :min="0" :precision="2" :controls="false" style="width: 100%" disabled />
              </el-form-item>
            </el-col>
          </el-row>

          <!-- 未买卧铺补助 -->
          <el-row :gutter="20">
            <el-col :span="8">
              <el-form-item label="卧铺票价">
                <el-input-number v-model="item.noBerthPrice" :min="0" :precision="2" :controls="false" style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="卧铺标准">
                <el-input-number v-model="item.noBerthStandard" :min="0" :precision="2" :controls="false" style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="卧铺金额">
                <el-input-number v-model="item.noBerthAmount" :min="0" :precision="2" :controls="false" style="width: 100%" disabled />
              </el-form-item>
            </el-col>
          </el-row>

          <!-- 夜间硬座补助 -->
          <el-form-item label="夜间硬座补助">
            <el-input-number v-model="item.nightHardSeatAmount" :min="0" :precision="2" :controls="false" style="width: 100%" />
          </el-form-item>

          <!-- 各类交通费 -->
          <el-row :gutter="20">
            <el-col :span="8">
              <el-form-item label="火车费">
                <el-input-number v-model="item.trainFee" :min="0" :precision="2" :controls="false" style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="汽车费">
                <el-input-number v-model="item.busFee" :min="0" :precision="2" :controls="false" style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="轮船费">
                <el-input-number v-model="item.shipFee" :min="0" :precision="2" :controls="false" style="width: 100%" />
              </el-form-item>
            </el-col>
          </el-row>

          <el-row :gutter="20">
            <el-col :span="8">
              <el-form-item label="飞机费">
                <el-input-number v-model="item.airplaneFee" :min="0" :precision="2" :controls="false" style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="市内交通">
                <el-input-number v-model="item.cityTransportFee" :min="0" :precision="2" :controls="false" style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="住宿费">
                <el-input-number v-model="item.accommodationFee" :min="0" :precision="2" :controls="false" style="width: 100%" />
              </el-form-item>
            </el-col>
          </el-row>

          <el-form-item label="其他杂支">
            <el-input-number v-model="item.otherFee" :min="0" :precision="2" :controls="false" style="width: 100%" />
          </el-form-item>

          <div style="text-align: right; font-weight: bold; font-size: 14px; color: #409EFF; margin-top: 10px;">
            小计：{{ formatAmount(getTravelItemTotal(item)) }}
          </div>
        </div>

        <el-button 
          type="primary" 
          :icon="Plus" 
          style="width: 100%; margin-bottom: 20px;"
          @click="addTravelItem"
        >
          添加差旅明细
        </el-button>

        <el-divider content-position="left">费用汇总</el-divider>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="原借差旅费">
              <el-input-number v-model="travelForm.advanceAmount" :min="0" :precision="2" :controls="false" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="报销">
              <el-input-number v-model="travelForm.reimbursementAmount" :min="0" :precision="2" :controls="false" style="width: 100%" disabled />
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="剩余交回">
          <el-input-number v-model="travelForm.refundAmount" :min="0" :precision="2" :controls="false" style="width: 100%" disabled />
        </el-form-item>

        <el-form-item label="出差事由">
          <el-input
            v-model="travelForm.reason"
            type="textarea"
            :rows="3"
            placeholder="请输入出差事由"
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
          <div>
            <el-button @click="handleClose">取消</el-button>
            <el-button type="warning" @click="fillTestData">
              一键填写测试数据
            </el-button>
          </div>
          <div>
            <el-button type="success" @click="saveCurrentForm" :loading="saving">
              保存当前表单
            </el-button>
            <el-button type="primary" @click="generateAllPdfs" :loading="generating" :disabled="savedForms.length === 0">
              生成PDF并添加为附件 ({{ savedForms.length }})
            </el-button>
          </div>
        </div>
      </template>
    </el-dialog>

    <!-- 隐藏的PDF内容区域 - 情况说明单 -->
    <div 
      ref="situationPdfRef" 
      style="position: absolute; left: -9999px; width: 794px; background: white; padding: 40px; font-family: 'Microsoft YaHei', SimSun, sans-serif;"
    >
      <div style="text-align: center; margin-bottom: 30px;">
        <h2 style="font-size: 20px; margin: 10px 0; font-weight: bold;">{{ situationForm.companyName }}</h2>
        <h3 style="font-size: 18px; margin: 15px 0; font-weight: bold;">情况说明单</h3>
        <p style="text-align: right; font-size: 14px; margin: 10px 0;">
          日期: {{ formatDateChinese(situationForm.date) }}
        </p>
      </div>
      
      <table style="width: 100%; border-collapse: collapse; border: 2px solid #000;">
        <tr>
          <td style="border: 1px solid #000; padding: 10px; width: 80px; text-align: center; font-weight: bold; vertical-align: middle;">项目</td>
          <td style="border: 1px solid #000; padding: 10px; white-space: pre-wrap; line-height: 1.6;">{{ situationForm.project }}</td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold; height: 200px; vertical-align: top;">事项</td>
          <td style="border: 1px solid #000; padding: 10px; white-space: pre-wrap; vertical-align: top; line-height: 1.6;">{{ situationForm.matter }}</td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold; vertical-align: middle;">备注</td>
          <td style="border: 1px solid #000; padding: 10px; white-space: pre-wrap; line-height: 1.6;">{{ situationForm.remarks || '' }}</td>
        </tr>
      </table>
    </div>

    <!-- 隐藏的PDF内容区域 - 付款申请单 -->
    <div 
      ref="paymentPdfRef" 
      style="position: absolute; left: -9999px; width: 794px; background: white; padding: 40px; font-family: 'Microsoft YaHei', SimSun, sans-serif;"
    >
      <!-- 标题区域固定高度80px，保证日期位置一致 -->
      <div style="height: 80px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
        <h2 style="font-size: 22px; margin: 0; font-weight: bold;">付款申请单</h2>
      </div>
      
      <table style="width: 100%; border-collapse: collapse; border: 2px solid #000; font-size: 14px;">
        <tr>
          <td style="border: 1px solid #000; padding: 8px; width: 100px; text-align: center; font-weight: bold;">付款日期</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="5">{{ formatDateChinese(paymentForm.paymentDate) }}</td>
          <td rowspan="10" style="border: 1px solid #000; padding: 8px; width: 80px; text-align: center; font-weight: bold; vertical-align: top;">
            <div style="margin-bottom: 10px;">摘要</div>
            <div style="font-size: 12px; font-weight: normal; line-height: 2.5;">
              <div>票据共 {{ currentInvoiceCount }} 张</div>
              <div style="margin-top: 15px;">附件共 {{ currentAttachmentCount }} 张</div>
            </div>
          </td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">所在部门</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="3">{{ paymentForm.department }}</td>
          <td style="border: 1px solid #000; padding: 8px; width: 100px; text-align: center; font-weight: bold;">申请日期</td>
          <td style="border: 1px solid #000; padding: 8px;">{{ formatMonthChinese(paymentForm.applyDate) }}</td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">支付对象</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="5">{{ paymentForm.payee }}</td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">支付金额</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="2">小写：{{ paymentForm.amountSmall }}</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="3">大写：{{ paymentForm.amountLarge }}</td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">付款方式</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="5">
            {{ paymentForm.paymentMethod === '现金' ? '☑' : '☐' }}现金
            {{ paymentForm.paymentMethod === '转账' ? '☑' : '☐' }}转账
            {{ paymentForm.paymentMethod === '支票' ? '☑' : '☐' }}支票
            {{ paymentForm.paymentMethod === '电汇' ? '☑' : '☐' }}电汇
            {{ paymentForm.paymentMethod === '承兑' ? '☑' : '☐' }}承兑
            {{ paymentForm.paymentMethod === '其他' ? '☑' : '☐' }}其他
          </td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">开户行</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="5">{{ paymentForm.bank }}</td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">银行账号</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="5">{{ paymentForm.bankAccount }}</td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold; vertical-align: top;">付款用途</td>
          <td style="border: 1px solid #000; padding: 8px; white-space: pre-wrap; line-height: 1.6;" colspan="5">{{ paymentForm.purpose }}</td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">开票情况</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="5">
            {{ paymentForm.invoiceStatus === '已开票' ? '☑' : '☐' }}已开票
            {{ paymentForm.invoiceStatus === '未开票' ? '☑' : '☐' }}未开票
            {{ paymentForm.invoiceStatus === '其他' ? '☑' : '☐' }}其他
          </td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold; vertical-align: top;">备注</td>
          <td style="border: 1px solid #000; padding: 8px; white-space: pre-wrap; line-height: 1.6;" colspan="5">{{ paymentForm.remarks || '' }}</td>
        </tr>
      </table>
      
      <!-- 底部无边框的签名行 -->
      <div style="display: flex; justify-content: space-around; margin-top: 20px; padding: 0 10px; font-size: 14px;">
        <div style="flex: 1; text-align: left; padding-left: 20px;">申请人：</div>
        <div style="flex: 1; text-align: left; padding-left: 20px;">审批：</div>
        <div style="flex: 1; text-align: left; padding-left: 20px;">审核：</div>
      </div>
    </div>

    <!-- 隐藏的PDF内容区域 - 报销单 -->
    <div 
      ref="reimbursementPdfRef" 
      style="position: absolute; left: -9999px; width: 794px; background: white; padding: 40px; font-family: 'Microsoft YaHei', SimSun, sans-serif;"
    >
      <!-- 标题区域固定高度80px，保证日期位置一致 -->
      <div style="height: 80px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
        <h2 style="font-size: 22px; margin: 0 0 10px 0; font-weight: bold;">{{ reimbursementForm.companyName }}</h2>
        <h3 style="font-size: 18px; margin: 0; font-weight: bold;">报 销 单</h3>
      </div>
      
      <table style="width: 100%; border-collapse: collapse; border: 2px solid #000; font-size: 14px; margin-bottom: 20px;">
        <!-- 支付日期 - 放在第1行 -->
        <tr>
          <td style="border: 1px solid #000; padding: 10px; width: 120px; text-align: center; font-weight: bold; background-color: #f5f5f5;">支付日期</td>
          <td style="border: 1px solid #000; padding: 10px;" colspan="3">{{ formatDateChinese(reimbursementForm.paymentDate) || '' }}</td>
          <td :rowspan="(reimbursementForm.items.length * 2) + 7" style="border: 1px solid #000; padding: 10px; width: 80px; text-align: center; font-weight: bold; vertical-align: top; background-color: #f5f5f5;">
            <div>票<br/>据<br/>{{ currentInvoiceCount }}<br/>张</div>
            <div style="margin-top: 20px; border-top: 1px solid #ccc; padding-top: 20px;">
              附<br/>件<br/>{{ currentAttachmentCount }}<br/>张
            </div>
          </td>
        </tr>
        <!-- 动态报销项目 -->
        <template v-for="(item, index) in reimbursementForm.items" :key="index">
          <tr>
            <td style="border: 1px solid #000; padding: 10px; width: 120px; text-align: center; font-weight: bold; background-color: #f5f5f5;">报销内容</td>
            <td style="border: 1px solid #000; padding: 10px;" colspan="3">{{ item.projectName || getProjectNameById(item.projectId) || '' }}</td>
          </tr>
          <tr>
            <td style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold; background-color: #f5f5f5;">报销金额</td>
            <td style="border: 1px solid #000; padding: 10px; text-align: right; font-weight: bold;" colspan="3">{{ formatAmount(item.amount) }}</td>
          </tr>
        </template>
        
        <!-- 报销人 -->
        <tr>
          <td style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold; background-color: #f5f5f5;">报销人</td>
          <td style="border: 1px solid #000; padding: 10px;" colspan="3">{{ reimbursementForm.applicant }}</td>
        </tr>
        
        <!-- 开户行 -->
        <tr>
          <td style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold; background-color: #f5f5f5;">开户行</td>
          <td style="border: 1px solid #000; padding: 10px;" colspan="3">{{ reimbursementForm.bankName || '' }}</td>
        </tr>
        
        <!-- 卡号 -->
        <tr>
          <td style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold; background-color: #f5f5f5;">卡号</td>
          <td style="border: 1px solid #000; padding: 10px;" colspan="3">{{ reimbursementForm.cardNumber || '' }}</td>
        </tr>
        
        <!-- 合计金额 -->
        <tr>
          <td style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold; background-color: #f5f5f5;">合计金额（大写）</td>
          <td style="border: 1px solid #000; padding: 10px;">{{ convertToChinese(getTotalAmount()) }}</td>
          <td style="border: 1px solid #000; padding: 10px; width: 80px; text-align: center; font-weight: bold; background-color: #f5f5f5;">（小写）</td>
          <td style="border: 1px solid #000; padding: 10px; text-align: right; font-weight: bold; font-size: 16px;">{{ formatAmount(getTotalAmount()) }}</td>
        </tr>
        
        <!-- 备注 -->
        <tr>
          <td style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold; vertical-align: top; background-color: #f5f5f5;">备注</td>
          <td style="border: 1px solid #000; padding: 10px; white-space: pre-wrap; line-height: 1.6;" colspan="3">{{ reimbursementForm.remarks || '' }}</td>
        </tr>
        
        <!-- 公司 -->
        <tr>
          <td style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold; background-color: #f5f5f5;">公司</td>
          <td style="border: 1px solid #000; padding: 10px;" colspan="3">{{ reimbursementForm.company || reimbursementForm.companyName || '' }}</td>
        </tr>
        
        <!-- 查验、状态、入账 -->
        <tr>
          <td style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold; background-color: #f5f5f5;">查验</td>
          <td style="border: 1px solid #000; padding: 10px; text-align: center; font-size: 18px;">{{ reimbursementForm.verified ? '✓' : '' }}</td>
          <td style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold; background-color: #f5f5f5;">状态</td>
          <td style="border: 1px solid #000; padding: 10px; text-align: center; font-size: 18px;">{{ reimbursementForm.status ? '✓' : '' }}</td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold; background-color: #f5f5f5;">入账</td>
          <td style="border: 1px solid #000; padding: 10px; text-align: center; font-size: 18px;" colspan="3">{{ reimbursementForm.accounted ? '✓' : '' }}</td>
        </tr>
      </table>

      <!-- 底部签名行 -->
      <div style="display: flex; justify-content: space-around; padding: 0 20px; font-size: 14px;">
        <div style="flex: 1; text-align: left;">经办人</div>
        <div style="flex: 1; text-align: center;">审核人</div>
        <div style="flex: 1; text-align: right;">审批人</div>
      </div>
    </div>

    <!-- 隐藏的PDF内容区域 - 差旅申请单（测试用） -->
    <div 
      ref="travelApplicationTestPdfRef" 
      style="position: absolute; left: -9999px; width: 794px; background: white; padding: 40px; font-family: 'Microsoft YaHei', SimSun, sans-serif;"
    >
      <!-- 标题区域固定高度80px，保证日期位置一致 -->
      <div style="height: 80px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
        <h2 style="font-size: 22px; margin: 0; font-weight: bold;">差旅申请单</h2>
      </div>
      
      <table style="width: 100%; border-collapse: collapse; border: 2px solid #000; font-size: 14px;">
        <tr>
          <td style="border: 1px solid #000; padding: 8px; width: 100px; text-align: center; font-weight: bold;">付款日期</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="5">{{ testTravelApplicationData.paymentDate }}</td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">所在部门</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="3">{{ testTravelApplicationData.department }}</td>
          <td style="border: 1px solid #000; padding: 8px; width: 100px; text-align: center; font-weight: bold;">申请日期</td>
          <td style="border: 1px solid #000; padding: 8px;">{{ testTravelApplicationData.applyDate }}</td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">姓名</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="5">{{ testTravelApplicationData.name }}</td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold; vertical-align: top;">事由</td>
          <td style="border: 1px solid #000; padding: 8px; white-space: pre-wrap; line-height: 1.6; min-height: 80px;" colspan="5">{{ testTravelApplicationData.reason }}</td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">出差地</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="5">{{ testTravelApplicationData.destination }}</td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">日期</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="5">
            自 {{ testTravelApplicationData.startTime }} 时至 {{ testTravelApplicationData.endTime }} 时止计 {{ testTravelApplicationData.days }} 天
          </td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">预支差旅费</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="2">小写：{{ testTravelApplicationData.advanceAmountSmall }}</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="3">大写：{{ testTravelApplicationData.advanceAmountLarge }}</td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold; vertical-align: top;">备注</td>
          <td style="border: 1px solid #000; padding: 8px; white-space: pre-wrap; line-height: 1.6;" colspan="5">{{ testTravelApplicationData.remarks }}</td>
        </tr>
      </table>
      
      <!-- 底部签名行 -->
      <div style="display: flex; justify-content: space-around; margin-top: 20px; padding: 0 10px; font-size: 14px;">
        <div style="flex: 1; text-align: left; padding-left: 20px;">申请人：</div>
        <div style="flex: 1; text-align: center;">审批：</div>
        <div style="flex: 1; text-align: right; padding-right: 20px;">审核：</div>
      </div>
    </div>

    <!-- 隐藏的PDF内容区域 - 差旅申请单（正式） -->
    <div 
      ref="travelApplicationPdfRef" 
      style="position: absolute; left: -9999px; width: 794px; background: white; padding: 40px; font-family: 'Microsoft YaHei', SimSun, sans-serif;"
    >
      <!-- 标题区域固定高度80px，保证日期位置一致 -->
      <div style="height: 80px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
        <h2 style="font-size: 22px; margin: 0; font-weight: bold;">差旅申请单</h2>
      </div>
      
      <table style="width: 100%; border-collapse: collapse; border: 2px solid #000; font-size: 14px;">
        <tr>
          <td style="border: 1px solid #000; padding: 8px; width: 100px; text-align: center; font-weight: bold;">付款日期</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="5">{{ formatDateChinese(travelApplicationForm.paymentDate) || '' }}</td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">所在部门</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="3">{{ travelApplicationForm.department }}</td>
          <td style="border: 1px solid #000; padding: 8px; width: 100px; text-align: center; font-weight: bold;">申请日期</td>
          <td style="border: 1px solid #000; padding: 8px;">{{ formatDateChinese(travelApplicationForm.applyDate) }}</td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">姓名</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="5">{{ travelApplicationForm.name }}</td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold; vertical-align: top;">事由</td>
          <td style="border: 1px solid #000; padding: 8px; white-space: pre-wrap; line-height: 1.6; min-height: 80px;" colspan="5">{{ travelApplicationForm.reason }}</td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">出差地</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="5">{{ travelApplicationForm.destination }}</td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">日期</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="5">
            自 {{ formatDateTimeShort(travelApplicationForm.startTime) }} 时至 {{ formatDateTimeShort(travelApplicationForm.endTime) }} 时止计 {{ travelApplicationForm.days }} 天
          </td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold;">预支差旅费</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="2">小写：{{ formatAmount(travelApplicationForm.advanceAmountSmall) }}</td>
          <td style="border: 1px solid #000; padding: 8px;" colspan="3">大写：{{ travelApplicationForm.advanceAmountLarge }}</td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold; vertical-align: top;">备注</td>
          <td style="border: 1px solid #000; padding: 8px; white-space: pre-wrap; line-height: 1.6;" colspan="5">{{ travelApplicationForm.remarks || '' }}</td>
        </tr>
      </table>
      
      <!-- 底部签名行 -->
      <div style="display: flex; justify-content: space-around; margin-top: 20px; padding: 0 10px; font-size: 14px;">
        <div style="flex: 1; text-align: left; padding-left: 20px;">申请人：</div>
        <div style="flex: 1; text-align: center;">审批：</div>
        <div style="flex: 1; text-align: right; padding-right: 20px;">审核：</div>
      </div>
    </div>

    <!-- 隐藏的PDF内容区域 - 差旅费报销单 -->
    <div 
      ref="travelPdfRef" 
      style="position: absolute; left: -9999px; width: 1123px; background: white; padding: 40px; font-family: 'Microsoft YaHei', SimSun, sans-serif;"
    >
      <!-- 标题 -->
      <div style="text-align: center; margin-bottom: 20px;">
        <h2 style="font-size: 24px; margin: 0; font-weight: bold; letter-spacing: 8px;">差旅费报销单</h2>
      </div>

      <!-- 姓名、时间、单位 -->
      <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13px; padding: 0 5px;">
        <div style="flex: 1;">姓名：{{ travelForm.name || '' }}</div>
        <div style="flex: 2; text-align: center;">时间：{{ formatDateChineseYearMonthDay(travelForm.date) }}</div>
        <div style="flex: 1; text-align: right;">单位：元</div>
      </div>

      <!-- 主表格 -->
      <table style="width: 100%; border-collapse: collapse; border: 2px solid #000; font-size: 11px;">
        <!-- 第一行：起日、止日、合计天数、各项补助费、车船杂支费、合计、附件 -->
        <tr>
          <td colspan="2" rowspan="2" style="border: 1px solid #000; padding: 4px; text-align: center; font-weight: bold; vertical-align: middle;">起日</td>
          <td colspan="2" rowspan="2" style="border: 1px solid #000; padding: 4px; text-align: center; font-weight: bold; vertical-align: middle;">止日</td>
          <td rowspan="3" style="border: 1px solid #000; padding: 4px; text-align: center; vertical-align: middle; font-weight: bold; width: 40px;">合<br/>计<br/>天<br/>数</td>
          <td colspan="11" style="border: 1px solid #000; padding: 4px; text-align: center; font-weight: bold;">各 项 补 助 费</td>
          <td colspan="7" style="border: 1px solid #000; padding: 4px; text-align: center; font-weight: bold;">车 船 杂 支 费</td>
          <td rowspan="3" style="border: 1px solid #000; padding: 4px; text-align: center; vertical-align: middle; font-weight: bold; width: 50px;">合计</td>
          <td rowspan="2" style="border: 1px solid #000; padding: 4px; text-align: center; vertical-align: middle; font-weight: bold; width: 45px;">附<br/>件</td>
        </tr>

        <!-- 第二行：各项细分标题 -->
        <tr>
          <td colspan="3" style="border: 1px solid #000; padding: 3px; text-align: center; font-weight: bold; font-size: 10px;">伙食补助</td>
          <td colspan="3" style="border: 1px solid #000; padding: 3px; text-align: center; font-weight: bold; font-size: 10px;">住宿补助</td>
          <td colspan="3" style="border: 1px solid #000; padding: 3px; text-align: center; font-weight: bold; font-size: 10px;">未买卧铺补助</td>
          <td colspan="2" rowspan="2" style="border: 1px solid #000; padding: 3px; text-align: center; font-weight: bold; font-size: 10px; vertical-align: middle;">夜间乘硬座<br/>超12小时补助</td>
          <td rowspan="2" style="border: 1px solid #000; padding: 3px; text-align: center; font-weight: bold; font-size: 10px; vertical-align: middle;">火车<br/>费</td>
          <td rowspan="2" style="border: 1px solid #000; padding: 3px; text-align: center; font-weight: bold; font-size: 10px; vertical-align: middle;">汽车<br/>费</td>
          <td rowspan="2" style="border: 1px solid #000; padding: 3px; text-align: center; font-weight: bold; font-size: 10px; vertical-align: middle;">轮船<br/>费</td>
          <td rowspan="2" style="border: 1px solid #000; padding: 3px; text-align: center; font-weight: bold; font-size: 10px; vertical-align: middle;">飞机<br/>费</td>
          <td rowspan="2" style="border: 1px solid #000; padding: 3px; text-align: center; font-weight: bold; font-size: 10px; vertical-align: middle;">市内<br/>交通</td>
          <td rowspan="2" style="border: 1px solid #000; padding: 3px; text-align: center; font-weight: bold; font-size: 10px; vertical-align: middle;">住宿<br/>费</td>
          <td rowspan="2" style="border: 1px solid #000; padding: 3px; text-align: center; font-weight: bold; font-size: 10px; vertical-align: middle;">其他<br/>杂支</td>
        </tr>

        <!-- 第三行：天数标准金额标签行 -->
        <tr>
          <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 9px;">月</td>
          <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 9px;">日</td>
          <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 9px;">月</td>
          <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 9px;">日</td>
          <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 9px;">天数</td>
          <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 9px;">标准</td>
          <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 9px;">金额</td>
          <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 9px;">天数</td>
          <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 9px;">标准</td>
          <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 9px;">金额</td>
          <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 9px;">票价</td>
          <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 9px;">标准</td>
          <td style="border: 1px solid #000; padding: 2px; text-align: center; font-size: 9px;">金额</td>
          <!-- 夜间硬座和车船杂支费已在第2行合并，这里不需要 -->
          <td :rowspan="(travelForm.items.length || 0) + 2" style="border: 1px solid #000; padding: 4px; text-align: center; vertical-align: middle; font-size: 11px; font-weight: bold;">{{ currentAttachmentCount }}<br/>张</td>
        </tr>

        <!-- 动态数据行 -->
        <template v-for="(item, index) in travelForm.items" :key="index">
          <tr>
            <td style="border: 1px solid #000; padding: 3px; text-align: center; font-size: 10px;">{{ formatMonthDay(item.startDate, 'month') }}</td>
            <td style="border: 1px solid #000; padding: 3px; text-align: center; font-size: 10px;">{{ formatMonthDay(item.startDate, 'day') }}</td>
            <td style="border: 1px solid #000; padding: 3px; text-align: center; font-size: 10px;">{{ formatMonthDay(item.endDate, 'month') }}</td>
            <td style="border: 1px solid #000; padding: 3px; text-align: center; font-size: 10px;">{{ formatMonthDay(item.endDate, 'day') }}</td>
            <td style="border: 1px solid #000; padding: 3px; text-align: center; font-size: 10px;">{{ item.totalDays || '' }}</td>
            <td style="border: 1px solid #000; padding: 3px; text-align: center; font-size: 10px;">{{ item.mealDays || '' }}</td>
            <td style="border: 1px solid #000; padding: 3px; text-align: center; font-size: 10px;">{{ item.mealStandard || '' }}</td>
            <td style="border: 1px solid #000; padding: 3px; text-align: right; font-size: 10px;">{{ formatAmount(item.mealAmount) }}</td>
            <td style="border: 1px solid #000; padding: 3px; text-align: center; font-size: 10px;">{{ item.accommodationDays || '' }}</td>
            <td style="border: 1px solid #000; padding: 3px; text-align: center; font-size: 10px;">{{ item.accommodationStandard || '' }}</td>
            <td style="border: 1px solid #000; padding: 3px; text-align: right; font-size: 10px;">{{ formatAmount(item.accommodationAmount) }}</td>
            <td style="border: 1px solid #000; padding: 3px; text-align: center; font-size: 10px;">{{ item.noBerthPrice || '' }}</td>
            <td style="border: 1px solid #000; padding: 3px; text-align: center; font-size: 10px;">{{ item.noBerthStandard || '' }}</td>
            <td style="border: 1px solid #000; padding: 3px; text-align: right; font-size: 10px;">{{ formatAmount(item.noBerthAmount) }}</td>
            <td colspan="2" style="border: 1px solid #000; padding: 3px; text-align: right; font-size: 10px;">{{ formatAmount(item.nightHardSeatAmount) }}</td>
            <td style="border: 1px solid #000; padding: 3px; text-align: right; font-size: 10px;">{{ formatAmount(item.trainFee) }}</td>
            <td style="border: 1px solid #000; padding: 3px; text-align: right; font-size: 10px;">{{ formatAmount(item.busFee) }}</td>
            <td style="border: 1px solid #000; padding: 3px; text-align: right; font-size: 10px;">{{ formatAmount(item.shipFee) }}</td>
            <td style="border: 1px solid #000; padding: 3px; text-align: right; font-size: 10px;">{{ formatAmount(item.airplaneFee) }}</td>
            <td style="border: 1px solid #000; padding: 3px; text-align: right; font-size: 10px;">{{ formatAmount(item.cityTransportFee) }}</td>
            <td style="border: 1px solid #000; padding: 3px; text-align: right; font-size: 10px;">{{ formatAmount(item.accommodationFee) }}</td>
            <td style="border: 1px solid #000; padding: 3px; text-align: right; font-size: 10px;">{{ formatAmount(item.otherFee) }}</td>
            <td style="border: 1px solid #000; padding: 3px; text-align: right; font-weight: bold; font-size: 10px;">{{ formatAmount(getTravelItemTotal(item)) }}</td>
          </tr>
        </template>

        <!-- 固定的2行空白行 -->
        <tr>
          <td style="border: 1px solid #000; padding: 3px; height: 25px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
        </tr>
        <tr>
          <td style="border: 1px solid #000; padding: 3px; height: 25px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
          <td style="border: 1px solid #000; padding: 3px;"></td>
        </tr>

        <!-- 第9行：合计人民币大写（长格子） -->
        <tr>
          <td colspan="25" style="border: 1px solid #000; padding: 6px; height: 32px;">
            <div style="display: flex; align-items: center; justify-content: space-between; font-size: 11px;">
              <div style="display: flex; align-items: center;">
                <span style="font-weight: bold; margin-right: 15px;">合计人民币大写</span>
                <span style="margin-right: 10px;">{{ getChineseDigit(getTravelTotalAmount(), 4) }}</span>
                <span style="font-weight: bold; margin-right: 15px;">万</span>
                <span style="margin-right: 10px;">{{ getChineseDigit(getTravelTotalAmount(), 3) }}</span>
                <span style="font-weight: bold; margin-right: 15px;">仟</span>
                <span style="margin-right: 10px;">{{ getChineseDigit(getTravelTotalAmount(), 2) }}</span>
                <span style="font-weight: bold; margin-right: 15px;">佰</span>
                <span style="margin-right: 10px;">{{ getChineseDigit(getTravelTotalAmount(), 1) }}</span>
                <span style="font-weight: bold; margin-right: 15px;">拾</span>
                <span style="margin-right: 10px;">{{ getChineseDigit(getTravelTotalAmount(), 0) }}</span>
                <span style="font-weight: bold; margin-right: 15px;">元</span>
                <span style="margin-right: 10px;">{{ getChineseDigit(getTravelTotalAmount(), -1) }}</span>
                <span style="font-weight: bold; margin-right: 15px;">角</span>
                <span style="margin-right: 10px;">{{ getChineseDigit(getTravelTotalAmount(), -2) }}</span>
                <span style="font-weight: bold;">分</span>
              </div>
              <span style="font-weight: bold; font-size: 12px;">{{ formatAmount(getTravelTotalAmount()) }}</span>
            </div>
          </td>
        </tr>

        <!-- 第10行：原借差旅费、报销、剩余交回（长格子） -->
        <tr>
          <td colspan="25" style="border: 1px solid #000; padding: 6px; height: 32px;">
            <div style="display: flex; align-items: center; font-size: 11px;">
              <span style="font-weight: bold; margin-right: 15px;">原借差旅费</span>
              <span style="margin-right: 30px;">{{ formatAmount(travelForm.advanceAmount) }}</span>
              <span style="font-weight: bold; margin-right: 50px;">元</span>
              <span style="font-weight: bold; margin-right: 15px;">报销</span>
              <span style="margin-right: 80px; font-weight: bold;">{{ formatAmount(travelForm.reimbursementAmount) }}</span>
              <span style="font-weight: bold; margin-right: 50px;">元</span>
              <span style="font-weight: bold; margin-right: 15px;">剩余交回</span>
              <span style="margin-right: 30px;">{{ formatAmount(travelForm.refundAmount) }}</span>
              <span style="font-weight: bold;">元</span>
            </div>
          </td>
        </tr>

        <!-- 出差事由行 -->
        <tr>
          <td colspan="4" style="border: 1px solid #000; padding: 8px; text-align: center; font-weight: bold; font-size: 11px;">出差事由</td>
          <td colspan="21" style="border: 1px solid #000; padding: 8px; white-space: pre-wrap; line-height: 1.5; min-height: 40px; font-size: 11px;">{{ travelForm.reason || '' }}</td>
        </tr>
      </table>

      <!-- 底部签名行 -->
      <div style="display: flex; justify-content: space-between; padding: 10px 20px 0; font-size: 13px;">
        <div style="flex: 1; text-align: left;">申批：</div>
        <div style="flex: 1; text-align: center;">审核：</div>
        <div style="flex: 1; text-align: right;">签字：</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, watch, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Delete } from '@element-plus/icons-vue'
import jsPDF from 'jspdf'
import html2canvas from 'html2canvas'
import { getProjects } from '@/api/projects'
import { useAccountSetStore } from '@/stores/accountSet'

const accountSetStore = useAccountSetStore()

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: '填写表单'
  },
  // 发票数量
  invoiceCount: {
    type: Number,
    default: 0
  },
  // 附件数量
  attachmentCount: {
    type: Number,
    default: 0
  },
  // 只显示情况说明单
  onlySituation: {
    type: Boolean,
    default: false
  },
  // 只显示付款申请单
  onlyPayment: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'word-generated'])

const dialogVisible = ref(props.modelValue)
const generating = ref(false)
const formType = ref(props.onlySituation ? 'situation' : (props.onlyPayment ? 'payment' : 'situation')) // 'situation' | 'payment' | 'reimbursement' | 'travelApplication' | 'travel'
const situationFormRef = ref(null)
const paymentFormRef = ref(null)
const reimbursementFormRef = ref(null)
const travelApplicationFormRef = ref(null)
const travelFormRef = ref(null)
const situationPdfRef = ref(null)
const paymentPdfRef = ref(null)
const reimbursementPdfRef = ref(null)
const travelApplicationTestPdfRef = ref(null)
const travelApplicationPdfRef = ref(null)
const travelPdfRef = ref(null)

// 已保存的表单列表
const savedForms = ref([])
const saving = ref(false)

// 动态的附件和发票数量（用于PDF生成时的统计）
const currentInvoiceCount = ref(0)
const currentAttachmentCount = ref(0)

// 测试用差旅申请单数据
const testTravelApplicationData = reactive({
  department: '财务部',
  applyDate: '2025 年 08 月 01 日',
  name: '张三',
  reason: '前往北京参加业务培训会议',
  destination: '北京市',
  startTime: '2025 年 08 月 05 日 08',
  endTime: '2025 年 08 月 07 日 18',
  days: 3,
  advanceAmountSmall: '¥2,000.00',
  advanceAmountLarge: '贰仟元整',
  paymentDate: '2025 年 08 月 03 日',
  remarks: '住宿标准300元/天，交通费实报实销'
})

// 情况说明单表单数据
const situationForm = reactive({
  companyName: '鄂尔多斯市汇邦人力资源有限责任公司',
  date: new Date().toISOString().split('T')[0],
  project: '',
  matter: '',
  remarks: ''
})

// 付款申请单表单数据
const paymentForm = reactive({
  department: '',
  applyDate: new Date().toISOString().slice(0, 7), // YYYY-MM
  payee: '',
  amountSmall: '',
  amountLarge: '',
  paymentMethod: '',
  bank: '',
  bankAccount: '',
  purpose: '',
  invoiceStatus: '',
  paymentDate: new Date().toISOString().split('T')[0],
  remarks: ''
})

// 发票项目列表
const invoiceProjects = ref([])

// 报销单表单数据
const reimbursementForm = reactive({
  companyName: '鄂尔多斯市汇邦人力资源有限责任公司',
  company: '鄂尔多斯市汇邦人力资源有限责任公司',
  date: new Date().toISOString().split('T')[0],
  items: [], // 动态报销项目列表 [{projectId, projectName, amount}]
  applicant: '',
  bankName: '',
  cardNumber: '',
  paymentDate: '',
  remarks: '',
  verified: true,
  status: true,
  accounted: true
})

// 差旅申请单表单数据
const travelApplicationForm = reactive({
  department: '',
  applyDate: new Date().toISOString().split('T')[0],
  name: '',
  reason: '',
  destination: '',
  startTime: '',
  endTime: '',
  days: 0,
  advanceAmountSmall: null,
  advanceAmountLarge: '',
  paymentDate: '',
  remarks: ''
})

// 差旅费报销单表单数据
const travelForm = reactive({
  name: '',
  date: new Date().toISOString().split('T')[0],
  items: [], // 动态差旅明细列表
  // 费用汇总
  advanceAmount: 0,
  reimbursementAmount: 0,
  refundAmount: 0,
  reason: ''
})

// 情况说明单验证规则
const situationRules = {
  companyName: [
    { required: true, message: '请输入公司名称', trigger: 'blur' }
  ],
  date: [
    { required: true, message: '请选择日期', trigger: 'change' }
  ],
  project: [
    { required: true, message: '请输入项目内容', trigger: 'blur' }
  ],
  matter: [
    { required: true, message: '请输入事项说明', trigger: 'blur' }
  ]
}

// 付款申请单验证规则
const paymentRules = {
  department: [
    { required: true, message: '请输入部门', trigger: 'blur' }
  ],
  applyDate: [
    { required: true, message: '请选择申请日期', trigger: 'change' }
  ],
  payee: [
    { required: true, message: '请输入支付对象', trigger: 'blur' }
  ],
  amountSmall: [
    { required: true, message: '请输入小写金额', trigger: 'blur' }
  ],
  amountLarge: [
    { required: true, message: '请输入大写金额', trigger: 'blur' }
  ],
  paymentMethod: [
    { required: true, message: '请选择付款方式', trigger: 'change' }
  ]
}

// 报销单验证规则
const reimbursementRules = {
  companyName: [
    { required: true, message: '请输入公司名称', trigger: 'blur' }
  ],
  date: [
    { required: true, message: '请选择日期', trigger: 'change' }
  ],
  applicant: [
    { required: true, message: '请输入报销人', trigger: 'blur' }
  ]
}

// 差旅申请单验证规则
const travelApplicationRules = {
  department: [
    { required: true, message: '请输入所在部门', trigger: 'blur' }
  ],
  applyDate: [
    { required: true, message: '请选择申请日期', trigger: 'change' }
  ],
  name: [
    { required: true, message: '请输入姓名', trigger: 'blur' }
  ],
  reason: [
    { required: true, message: '请输入出差事由', trigger: 'blur' }
  ],
  destination: [
    { required: true, message: '请输入出差地点', trigger: 'blur' }
  ],
  startTime: [
    { required: true, message: '请选择起始时间', trigger: 'change' }
  ],
  endTime: [
    { required: true, message: '请选择结束时间', trigger: 'change' }
  ]
}

// 差旅费报销单验证规则
const travelRules = {
  name: [
    { required: true, message: '请输入姓名', trigger: 'blur' }
  ],
  date: [
    { required: true, message: '请选择日期', trigger: 'change' }
  ]
}

const handleClose = () => {
  emit('update:modelValue', false)
}

const handleFormTypeChange = () => {
  // 切换表单类型时清除验证
  if (situationFormRef.value) {
    situationFormRef.value.clearValidate()
  }
  if (paymentFormRef.value) {
    paymentFormRef.value.clearValidate()
  }
  if (reimbursementFormRef.value) {
    reimbursementFormRef.value.clearValidate()
  }
  if (travelApplicationFormRef.value) {
    travelApplicationFormRef.value.clearValidate()
  }
  if (travelFormRef.value) {
    travelFormRef.value.clearValidate()
  }
}

// 获取表单类型名称
const getFormTypeName = (type) => {
  const names = {
    situation: '情况说明单',
    payment: '付款申请单',
    reimbursement: '报销单',
    travelApplication: '差旅申请单',
    travel: '差旅费报销单'
  }
  return names[type] || type
}

// 一键填写测试数据
const fillTestData = () => {
  const today = new Date().toISOString().split('T')[0]
  const currentMonth = new Date().toISOString().slice(0, 7)
  
  if (formType.value === 'situation') {
    // 情况说明单测试数据
    situationForm.companyName = '鄂尔多斯市汇邦人力资源有限责任公司'
    situationForm.date = today
    situationForm.project = '2025年度员工培训项目'
    situationForm.matter = '因本次培训活动由外部培训机构提供，培训机构为小规模纳税人，无法开具增值税专用发票，仅能提供增值税普通发票。\n\n经与培训机构沟通确认，培训费用共计人民币伍仟元整（¥5,000.00），已按合同约定完成全部培训内容，培训效果良好。\n\n特此说明。'
    situationForm.remarks = '附：培训签到表、培训照片'
    ElMessage.success('情况说明单测试数据已填充')
  } else if (formType.value === 'payment') {
    // 付款申请单测试数据
    paymentForm.department = '人力资源部'
    paymentForm.applyDate = currentMonth
    paymentForm.payee = '北京某某培训有限公司'
    paymentForm.amountSmall = '¥5,000.00'
    paymentForm.amountLarge = '伍仟元整'
    paymentForm.paymentMethod = '转账'
    paymentForm.bank = '中国工商银行北京分行'
    paymentForm.bankAccount = '6222021234567890123'
    paymentForm.purpose = '支付2025年度员工培训费用'
    paymentForm.invoiceStatus = '已开票'
    paymentForm.paymentDate = today
    paymentForm.remarks = '培训已完成，发票已收到'
    ElMessage.success('付款申请单测试数据已填充')
  } else if (formType.value === 'reimbursement') {
    // 报销单测试数据
    reimbursementForm.companyName = '鄂尔多斯市汇邦人力资源有限责任公司'
    reimbursementForm.company = '鄂尔多斯市汇邦人力资源有限责任公司'
    reimbursementForm.date = today
    reimbursementForm.applicant = '张三'
    reimbursementForm.bankName = '中国工商银行'
    reimbursementForm.cardNumber = '6222021234567890123'
    reimbursementForm.paymentDate = today
    reimbursementForm.remarks = '业务招待费报销'
    reimbursementForm.verified = true
    reimbursementForm.status = true
    reimbursementForm.accounted = true
    // 添加一个报销项目
    if (reimbursementForm.items.length === 0) {
      reimbursementForm.items.push({
        projectId: invoiceProjects.value.length > 0 ? invoiceProjects.value[0].id : null,
        projectName: invoiceProjects.value.length > 0 ? invoiceProjects.value[0].name : '办公费',
        amount: 1500.00
      })
    }
    ElMessage.success('报销单测试数据已填充')
  } else if (formType.value === 'travelApplication') {
    // 差旅申请单测试数据
    travelApplicationForm.department = '人力资源部'
    travelApplicationForm.applyDate = today
    travelApplicationForm.name = '张三'
    travelApplicationForm.reason = '前往北京参加2025年度人力资源管理培训会议'
    travelApplicationForm.destination = '北京市朝阳区'
    travelApplicationForm.startTime = '2025年12月15日 08:00'
    travelApplicationForm.endTime = '2025年12月17日 18:00'
    travelApplicationForm.days = 3
    travelApplicationForm.advanceAmountSmall = 3000
    travelApplicationForm.advanceAmountLarge = '叁仟元整'
    travelApplicationForm.paymentDate = today
    travelApplicationForm.remarks = '住宿标准300元/天，交通费实报实销'
    ElMessage.success('差旅申请单测试数据已填充')
  } else if (formType.value === 'travel') {
    // 差旅费报销单测试数据
    travelForm.name = '张三'
    travelForm.date = today
    travelForm.reason = '参加北京人力资源管理培训会议'
    travelForm.advanceAmount = 3000
    // 添加差旅明细
    if (travelForm.items.length === 0) {
      travelForm.items = [
        {
          date: '2025-12-15',
          departure: '鄂尔多斯',
          destination: '北京',
          transportation: '飞机',
          transportationFee: 800,
          accommodation: '如家酒店',
          accommodationFee: 300,
          meals: 100,
          otherFee: 50,
          subtotal: 1250
        },
        {
          date: '2025-12-16',
          departure: '北京',
          destination: '北京',
          transportation: '地铁',
          transportationFee: 20,
          accommodation: '如家酒店',
          accommodationFee: 300,
          meals: 100,
          otherFee: 0,
          subtotal: 420
        },
        {
          date: '2025-12-17',
          departure: '北京',
          destination: '鄂尔多斯',
          transportation: '飞机',
          transportationFee: 800,
          accommodation: '',
          accommodationFee: 0,
          meals: 80,
          otherFee: 30,
          subtotal: 910
        }
      ]
    }
    // 计算汇总
    travelForm.reimbursementAmount = travelForm.items.reduce((sum, item) => sum + (item.subtotal || 0), 0)
    travelForm.refundAmount = travelForm.advanceAmount - travelForm.reimbursementAmount
    ElMessage.success('差旅费报销单测试数据已填充')
  }
}

// 保存当前表单
const saveCurrentForm = async () => {
  try {
    saving.value = true
    
    // 根据表单类型验证对应的表单
    const formRef = formType.value === 'situation' 
      ? situationFormRef.value 
      : formType.value === 'payment' 
        ? paymentFormRef.value 
        : formType.value === 'travelApplication'
          ? travelApplicationFormRef.value
          : formType.value === 'travel'
            ? travelFormRef.value
            : reimbursementFormRef.value
    await formRef.validate()

    // 如果是报销单，验证至少有一个报销项目
    if (formType.value === 'reimbursement') {
      if (!reimbursementForm.items || reimbursementForm.items.length === 0) {
        ElMessage.warning('请至少添加一个报销项目')
        return
      }
      
      // 验证每个项目都有内容和金额，并确保项目名称已设置
      for (let i = 0; i < reimbursementForm.items.length; i++) {
        const item = reimbursementForm.items[i]
        if (!item.projectId) {
          ElMessage.warning(`请选择第 ${i + 1} 个报销项目`)
          return
        }
        if (!item.amount || item.amount <= 0) {
          ElMessage.warning(`请输入第 ${i + 1} 个报销项目的金额`)
          return
        }
        
        // 确保项目名称已设置
        if (!item.projectName) {
          const project = invoiceProjects.value.find(p => p.id === item.projectId)
          if (project) {
            item.projectName = project.name
          }
        }
      }
    }

    // 深拷贝当前表单数据
    const formData = formType.value === 'situation' 
      ? JSON.parse(JSON.stringify(situationForm))
      : formType.value === 'payment'
        ? JSON.parse(JSON.stringify(paymentForm))
        : formType.value === 'travelApplication'
          ? JSON.parse(JSON.stringify(travelApplicationForm))
          : formType.value === 'travel'
            ? JSON.parse(JSON.stringify(travelForm))
            : JSON.parse(JSON.stringify(reimbursementForm))

    // 保存到列表
    savedForms.value.push({
      formType: formType.value,
      formData: formData
    })

    ElMessage.success(`${getFormTypeName(formType.value)}已保存！可以继续填写其他表单`)
    
    // 清空当前表单，准备填写下一个
    resetCurrentForm()
    
  } catch (error) {
    if (error !== false) {
      console.error('Save form error:', error)
      ElMessage.error('保存表单失败')
    }
  } finally {
    saving.value = false
  }
}

// 重置当前表单
const resetCurrentForm = () => {
  if (formType.value === 'situation') {
    Object.assign(situationForm, {
      companyName: '鄂尔多斯市汇邦人力资源有限责任公司',
      date: new Date().toISOString().split('T')[0],
      project: '',
      matter: '',
      remarks: ''
    })
    situationFormRef.value?.clearValidate()
  } else if (formType.value === 'payment') {
    Object.assign(paymentForm, {
      department: '',
      applyDate: new Date().toISOString().slice(0, 7),
      payee: '',
      amountSmall: '',
      amountLarge: '',
      paymentMethod: [],
      bank: '',
      bankAccount: '',
      purpose: '',
      invoiceStatus: [],
      paymentDate: new Date().toISOString().split('T')[0],
      remarks: ''
    })
    paymentFormRef.value?.clearValidate()
  } else if (formType.value === 'reimbursement') {
    Object.assign(reimbursementForm, {
      companyName: '鄂尔多斯市汇邦人力资源有限责任公司',
      company: '鄂尔多斯市汇邦人力资源有限责任公司',
      date: new Date().toISOString().split('T')[0],
      items: [],
      applicant: '',
      bankName: '',
      cardNumber: '',
      paymentDate: '',
      remarks: '',
      verified: true,
      status: true,
      accounted: true
    })
    reimbursementFormRef.value?.clearValidate()
  } else if (formType.value === 'travelApplication') {
    Object.assign(travelApplicationForm, {
      department: '',
      applyDate: new Date().toISOString().split('T')[0],
      name: '',
      reason: '',
      destination: '',
      startTime: '',
      endTime: '',
      days: 0,
      advanceAmountSmall: null,
      advanceAmountLarge: '',
      paymentDate: '',
      remarks: ''
    })
    travelApplicationFormRef.value?.clearValidate()
  } else if (formType.value === 'travel') {
    Object.assign(travelForm, {
      name: '',
      date: new Date().toISOString().split('T')[0],
      items: [],
      advanceAmount: 0,
      reimbursementAmount: 0,
      refundAmount: 0,
      reason: ''
    })
    travelFormRef.value?.clearValidate()
  }
}

// 删除已保存的表单
const removeSavedForm = (index) => {
  savedForms.value.splice(index, 1)
  ElMessage.info('已移除该表单')
}

// 清空所有已保存的表单
const clearSavedForms = () => {
  ElMessageBox.confirm('确定要清空所有已保存的表单吗？', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    savedForms.value = []
    ElMessage.success('已清空所有表单')
  }).catch(() => {})
}

// 测试生成差旅申请单PDF（使用预设数据，不做校验）
const testGenerateTravelApplication = async () => {
  try {
    generating.value = true
    ElMessage.info('正在生成差旅申请单PDF预览...')

    // 等待DOM更新
    await new Promise(resolve => setTimeout(resolve, 300))

    const canvas = await html2canvas(travelApplicationTestPdfRef.value, {
      scale: 2,
      useCORS: true,
      logging: false,
      backgroundColor: '#ffffff'
    })

    const imgData = canvas.toDataURL('image/png')
    const imgWidth = 210 // A4 竖向
    const pageHeight = 297
    const imgHeight = (canvas.height * imgWidth) / canvas.width
    let heightLeft = imgHeight

    const doc = new jsPDF('p', 'mm', 'a4')
    let position = 0

    doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight)
    heightLeft -= pageHeight

    while (heightLeft >= 0) {
      position = heightLeft - imgHeight
      doc.addPage()
      doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight)
      heightLeft -= pageHeight
    }

    // 在浏览器新窗口打开PDF预览
    const pdfBlob = doc.output('blob')
    const pdfUrl = URL.createObjectURL(pdfBlob)
    window.open(pdfUrl, '_blank')

    ElMessage.success('差旅申请单PDF预览已打开！')

  } catch (error) {
    console.error('Generate travel application PDF error:', error)
    ElMessage.error('生成PDF预览失败，请重试')
  } finally {
    generating.value = false
  }
}

// 添加报销项目
const addReimbursementItem = () => {
  reimbursementForm.items.push({
    projectId: null,
    projectName: '',
    amount: null
  })
}

// 删除报销项目
const removeReimbursementItem = (index) => {
  reimbursementForm.items.splice(index, 1)
}

// 添加差旅明细
const addTravelItem = () => {
  travelForm.items.push({
    startDate: '',
    endDate: '',
    totalDays: 0,
    // 伙食补助
    mealDays: 0,
    mealStandard: 0,
    mealAmount: 0,
    // 住宿补助
    accommodationDays: 0,
    accommodationStandard: 0,
    accommodationAmount: 0,
    // 未买卧铺补助
    noBerthPrice: 0,
    noBerthStandard: 0,
    noBerthAmount: 0,
    // 夜间乘硬座超12小时补助
    nightHardSeatAmount: 0,
    // 交通费用
    trainFee: 0,
    busFee: 0,
    shipFee: 0,
    airplaneFee: 0,
    cityTransportFee: 0,
    accommodationFee: 0,
    otherFee: 0
  })
}

// 删除差旅明细
const removeTravelItem = (index) => {
  travelForm.items.splice(index, 1)
}

// 计算单行差旅费用小计
const getTravelItemTotal = (item) => {
  return (item.mealAmount || 0) +
    (item.accommodationAmount || 0) +
    (item.noBerthAmount || 0) +
    (item.nightHardSeatAmount || 0) +
    (item.trainFee || 0) +
    (item.busFee || 0) +
    (item.shipFee || 0) +
    (item.airplaneFee || 0) +
    (item.cityTransportFee || 0) +
    (item.accommodationFee || 0) +
    (item.otherFee || 0)
}

// 处理项目选择变化
const handleProjectChange = (index, projectId) => {
  console.log('项目选择变化:', index, projectId)
  const project = invoiceProjects.value.find(p => p.id === projectId)
  console.log('找到的项目:', project)
  if (project) {
    // 直接修改对象属性，确保响应式
    reimbursementForm.items[index].projectName = project.name // 项目管理的字段是 name
    console.log('更新后的项目名称:', reimbursementForm.items[index].projectName)
  }
}

// 加载项目列表
const loadInvoiceProjects = async () => {
  try {
    const res = await getProjects({
      current_account_set_id: accountSetStore.currentAccountSetId,
      all: true // 获取所有项目，不分页
    })
    if (res.success && res.data) {
      // 如果返回的是分页数据，取data属性；如果直接是数组，就用它
      invoiceProjects.value = Array.isArray(res.data) ? res.data : (res.data.data || [])
      console.log('项目加载成功:', invoiceProjects.value)
    }
  } catch (error) {
    console.error('加载项目失败:', error)
  }
}

// 根据项目ID获取项目名称（用于PDF生成）
const getProjectNameById = (projectId) => {
  const project = invoiceProjects.value.find(p => p.id === projectId)
  return project ? project.name : '' // 项目管理的字段是 name，不是 project_name
}

// 组件挂载时加载项目
onMounted(() => {
  loadInvoiceProjects()
  // 初始化附件和发票数量
  currentInvoiceCount.value = props.invoiceCount
  currentAttachmentCount.value = props.attachmentCount
})

// 格式化金额
const formatAmount = (amount) => {
  if (!amount && amount !== 0) return ''
  return `¥${Number(amount).toFixed(2)}`
}

// 计算报销单总金额
const getTotalAmount = () => {
  return reimbursementForm.items.reduce((total, item) => {
    return total + (item.amount || 0)
  }, 0)
}

// 获取指定位数的中文数字
const getChineseDigit = (amount, position) => {
  if (!amount && amount !== 0) return ''
  
  const cnNums = ['零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖']
  
  // 将金额转为字符串并补齐小数位
  const amountStr = Number(amount).toFixed(2)
  const [integerPart, decimalPart] = amountStr.split('.')
  
  // 整数部分补齐到5位（万位到个位）
  const paddedInteger = integerPart.padStart(5, '0')
  
  // position: 4=万位, 3=仟位, 2=佰位, 1=拾位, 0=个位/元位, -1=角位, -2=分位
  let digitStr = ''
  if (position >= 0) {
    // 整数部分：从右往左数，0是个位，1是十位...
    const index = 4 - position
    digitStr = paddedInteger[index]
  } else if (position === -1) {
    // 角位（小数第一位）
    digitStr = decimalPart[0]
  } else if (position === -2) {
    // 分位（小数第二位）
    digitStr = decimalPart[1]
  }
  
  const digit = parseInt(digitStr)
  return cnNums[digit] || ''
}

// 数字转中文大写金额
const convertToChinese = (money) => {
  if (!money && money !== 0) return ''
  
  const cnNums = ['零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖']
  const cnIntRadice = ['', '拾', '佰', '仟']
  const cnIntUnits = ['', '万', '亿', '兆']
  const cnDecUnits = ['角', '分', '毫', '厘']
  const cnInteger = '整'
  const cnIntLast = '元'
  const maxNum = 999999999999.9999
  
  let integerNum
  let decimalNum
  let chineseStr = ''
  let parts
  
  if (money === '') {
    return ''
  }
  money = parseFloat(money)
  if (money >= maxNum) {
    return ''
  }
  if (money === 0) {
    chineseStr = cnNums[0] + cnIntLast + cnInteger
    return chineseStr
  }
  money = money.toString()
  if (money.indexOf('.') === -1) {
    integerNum = money
    decimalNum = ''
  } else {
    parts = money.split('.')
    integerNum = parts[0]
    decimalNum = parts[1].substr(0, 4)
  }
  
  if (parseInt(integerNum, 10) > 0) {
    let zeroCount = 0
    const IntLen = integerNum.length
    for (let i = 0; i < IntLen; i++) {
      const n = integerNum.substr(i, 1)
      const p = IntLen - i - 1
      const q = p / 4
      const m = p % 4
      if (n === '0') {
        zeroCount++
      } else {
        if (zeroCount > 0) {
          chineseStr += cnNums[0]
        }
        zeroCount = 0
        chineseStr += cnNums[parseInt(n)] + cnIntRadice[m]
      }
      if (m === 0 && zeroCount < 4) {
        chineseStr += cnIntUnits[q]
      }
    }
    chineseStr += cnIntLast
  }
  
  if (decimalNum !== '') {
    const decLen = decimalNum.length
    for (let i = 0; i < decLen; i++) {
      const n = decimalNum.substr(i, 1)
      if (n !== '0') {
        chineseStr += cnNums[Number(n)] + cnDecUnits[i]
      }
    }
  }
  
  if (chineseStr === '') {
    chineseStr += cnNums[0] + cnIntLast + cnInteger
  } else if (decimalNum === '') {
    chineseStr += cnInteger
  }
  
  return chineseStr
}

// 格式化日期为中文
const formatDateChinese = (dateStr) => {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return `${date.getFullYear()}年${String(date.getMonth() + 1).padStart(2, '0')}月${String(date.getDate()).padStart(2, '0')}日`
}

// 格式化日期为中文（带XX年XX月XX日格式）
const formatDateChineseYearMonthDay = (dateStr) => {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return `${date.getFullYear()} 年 ${String(date.getMonth() + 1).padStart(2, '0')} 月 ${String(date.getDate()).padStart(2, '0')} 日`
}

// 格式化月日
const formatMonthDay = (dateStr, type) => {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  if (type === 'month') {
    return String(date.getMonth() + 1).padStart(2, '0')
  } else if (type === 'day') {
    return String(date.getDate()).padStart(2, '0')
  }
  return ''
}

// 格式化日期时间为短格式（用于差旅申请单）
const formatDateTimeShort = (dateTimeStr) => {
  if (!dateTimeStr) return ''
  const date = new Date(dateTimeStr)
  return `${date.getFullYear()} 年 ${String(date.getMonth() + 1).padStart(2, '0')} 月 ${String(date.getDate()).padStart(2, '0')} 日 ${String(date.getHours()).padStart(2, '0')}`
}

// 格式化年月为中文
const formatMonthChinese = (dateStr) => {
  if (!dateStr) return ''
  const [year, month] = dateStr.split('-')
  return `${year}年${month}月`
}

// 计算差旅费报销单总金额
const getTravelTotalAmount = () => {
  if (!travelForm.items || travelForm.items.length === 0) {
    return 0
  }
  return travelForm.items.reduce((total, item) => {
    return total + getTravelItemTotal(item)
  }, 0)
}

// 保存付款申请单历史记录到数据库
const savePaymentHistory = async () => {
  try {
    const bankAccount = paymentForm.bankAccount
    if (!bankAccount) return

    const response = await fetch('/api/payment-form-history', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('token')}`
      },
      body: JSON.stringify({
        bank_account: paymentForm.bankAccount,
        department: paymentForm.department,
        payee: paymentForm.payee,
        amount_small: paymentForm.amountSmall,
        amount_large: paymentForm.amountLarge,
        payment_method: paymentForm.paymentMethod,
        bank: paymentForm.bank,
        purpose: paymentForm.purpose,
        invoice_status: paymentForm.invoiceStatus
      })
    })

    const result = await response.json()
    if (!result.success) {
      console.error('保存历史记录失败:', result.message)
    }
  } catch (error) {
    console.error('保存历史记录失败:', error)
  }
}

// 查询历史银行账号
const queryHistoryAccounts = async (queryString, callback) => {
  try {
    const url = queryString 
      ? `/api/payment-form-history?keyword=${encodeURIComponent(queryString)}`
      : '/api/payment-form-history'

    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`
      }
    })

    const result = await response.json()
    
    if (result.success && result.data) {
      const accounts = result.data.map(item => ({
        value: item.bank_account,
        payee: item.payee || '',
        data: {
          department: item.department,
          payee: item.payee,
          amountSmall: item.amount_small,
          amountLarge: item.amount_large,
          paymentMethod: item.payment_method || [],
          bank: item.bank,
          bankAccount: item.bank_account,
          purpose: item.purpose,
          invoiceStatus: item.invoice_status || []
        }
      }))
      callback(accounts)
    } else {
      callback([])
    }
  } catch (error) {
    console.error('查询历史记录失败:', error)
    callback([])
  }
}

// 选择历史记录后自动填充
const handleSelectHistoryAccount = (item) => {
  const data = item.data
  if (!data) return

  // 自动填充所有字段
  paymentForm.department = data.department || ''
  paymentForm.payee = data.payee || ''
  paymentForm.amountSmall = data.amountSmall || ''
  paymentForm.amountLarge = data.amountLarge || ''
  paymentForm.paymentMethod = data.paymentMethod || []
  paymentForm.bank = data.bank || ''
  paymentForm.bankAccount = data.bankAccount || ''
  paymentForm.purpose = data.purpose || ''
  paymentForm.invoiceStatus = data.invoiceStatus || []

  ElMessage.success('已自动填充历史数据')
}

// 生成所有已保存的表单PDF
const generateAllPdfs = async () => {
  if (savedForms.value.length === 0) {
    ElMessage.warning('请先保存至少一个表单')
    return
  }

  try {
    generating.value = true
    ElMessage.info(`正在生成 ${savedForms.value.length} 个PDF文件...`)

    // 计算总的附件和发票数量
    // 附件数量 = 上传的其他附件数量 + 保存的表单数量（这些表单会生成PDF作为附件）
    const totalAttachmentCount = (props.attachmentCount || 0) + savedForms.value.length
    // 发票数量 = 上传的发票数量
    const totalInvoiceCount = props.invoiceCount || 0

    console.log('=== 开始生成PDF ===')
    console.log('上传的发票数量 (props.invoiceCount):', props.invoiceCount)
    console.log('上传的附件数量 (props.attachmentCount):', props.attachmentCount)
    console.log('保存的表单数量 (savedForms.value.length):', savedForms.value.length)
    console.log('计算后总发票数量 (totalInvoiceCount):', totalInvoiceCount)
    console.log('计算后总附件数量 (totalAttachmentCount):', totalAttachmentCount)

    // 设置当前生成PDF使用的附件和发票数量
    currentInvoiceCount.value = totalInvoiceCount
    currentAttachmentCount.value = totalAttachmentCount
    
    console.log('已设置 currentInvoiceCount:', currentInvoiceCount.value)
    console.log('已设置 currentAttachmentCount:', currentAttachmentCount.value)

    // 等待Vue更新DOM，确保数量显示正确
    await new Promise(resolve => setTimeout(resolve, 500))

    const generatedFiles = []
    
    for (let i = 0; i < savedForms.value.length; i++) {
      const savedForm = savedForms.value[i]
      ElMessage.info(`正在生成第 ${i + 1}/${savedForms.value.length} 个PDF...`)
      
      // ⚠️ 重要：在恢复表单数据之前，先确保附件和发票数量是最新的
      // 这样恢复的表单数据中的附件数量字段就会被覆盖
      currentAttachmentCount.value = totalAttachmentCount
      currentInvoiceCount.value = totalInvoiceCount
      
      // 临时恢复表单数据到对应的表单对象
      if (savedForm.formType === 'situation') {
        Object.assign(situationForm, savedForm.formData)
      } else if (savedForm.formType === 'payment') {
        Object.assign(paymentForm, savedForm.formData)
      } else if (savedForm.formType === 'reimbursement') {
        Object.assign(reimbursementForm, savedForm.formData)
      } else if (savedForm.formType === 'travelApplication') {
        Object.assign(travelApplicationForm, savedForm.formData)
      } else if (savedForm.formType === 'travel') {
        Object.assign(travelForm, savedForm.formData)
      }

      // 再次确认附件和发票数量（防止被表单数据覆盖）
      currentAttachmentCount.value = totalAttachmentCount
      currentInvoiceCount.value = totalInvoiceCount
      
      console.log(`生成第 ${i + 1} 个PDF - 附件数量: ${currentAttachmentCount.value}, 发票数量: ${currentInvoiceCount.value}`)

      // 等待Vue更新DOM
      await new Promise(resolve => setTimeout(resolve, 300))

      // 获取对应的PDF内容区域
      const pdfContent = savedForm.formType === 'situation' 
        ? situationPdfRef.value 
        : savedForm.formType === 'payment' 
          ? paymentPdfRef.value 
          : savedForm.formType === 'travelApplication'
            ? travelApplicationPdfRef.value
            : savedForm.formType === 'travel'
              ? travelPdfRef.value
              : reimbursementPdfRef.value
      
      const canvas = await html2canvas(pdfContent, {
        scale: 2,
        useCORS: true,
        logging: false,
        backgroundColor: '#ffffff'
      })

      const imgData = canvas.toDataURL('image/png')
      
      // 差旅费报销单使用横向A4，其他表单使用竖向A4
      let doc, imgWidth, pageHeight, imgHeight, heightLeft
      
      if (savedForm.formType === 'travel') {
        imgWidth = 297
        pageHeight = 210
        imgHeight = (canvas.height * imgWidth) / canvas.width
        heightLeft = imgHeight
        doc = new jsPDF('l', 'mm', 'a4')
      } else {
        imgWidth = 210
        pageHeight = 297
        imgHeight = (canvas.height * imgWidth) / canvas.width
        heightLeft = imgHeight
        doc = new jsPDF('p', 'mm', 'a4')
      }

      let position = 0

      doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight)
      heightLeft -= pageHeight

      while (heightLeft >= 0) {
        position = heightLeft - imgHeight
        doc.addPage()
        doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight)
        heightLeft -= pageHeight
      }

      const pdfBlob = doc.output('blob')

      const timestamp = new Date().getTime()
      const fileName = savedForm.formType === 'situation' 
        ? `情况说明单_${timestamp}_${i + 1}.pdf`
        : savedForm.formType === 'payment'
          ? `付款申请单_${timestamp}_${i + 1}.pdf`
          : savedForm.formType === 'travelApplication'
            ? `差旅申请单_${timestamp}_${i + 1}.pdf`
            : savedForm.formType === 'travel'
              ? `差旅费报销单_${timestamp}_${i + 1}.pdf`
              : `报销单_${timestamp}_${i + 1}.pdf`
      const pdfFile = new File([pdfBlob], fileName, { type: 'application/pdf' })

      generatedFiles.push({
        file: pdfFile,
        fileName: fileName,
        formData: savedForm.formData,
        formType: savedForm.formType
      })
    }

    ElMessage.success(`成功生成 ${generatedFiles.length} 个PDF文件！`)

    // 发送所有生成的PDF文件
    for (const fileData of generatedFiles) {
      emit('word-generated', fileData)
      // 添加延迟以避免并发问题
      await new Promise(resolve => setTimeout(resolve, 200))
    }

    // 清空已保存的表单列表
    savedForms.value = []

    handleClose()

  } catch (error) {
    console.error('Generate PDFs error:', error)
    ElMessage.error('生成PDF失败，请重试')
  } finally {
    generating.value = false
  }
}

const generatePdf = async () => {
  try {
    // 根据表单类型验证对应的表单
    const formRef = formType.value === 'situation' 
      ? situationFormRef.value 
      : formType.value === 'payment' 
        ? paymentFormRef.value 
        : formType.value === 'travel'
          ? travelFormRef.value
          : reimbursementFormRef.value
    await formRef.validate()

    // 如果是报销单，验证至少有一个报销项目
    if (formType.value === 'reimbursement') {
      if (!reimbursementForm.items || reimbursementForm.items.length === 0) {
        ElMessage.warning('请至少添加一个报销项目')
        return
      }
      
      // 验证每个项目都有内容和金额，并确保项目名称已设置
      for (let i = 0; i < reimbursementForm.items.length; i++) {
        const item = reimbursementForm.items[i]
        if (!item.projectId) {
          ElMessage.warning(`请选择第 ${i + 1} 个报销项目`)
          return
        }
        if (!item.amount || item.amount <= 0) {
          ElMessage.warning(`请输入第 ${i + 1} 个报销项目的金额`)
          return
        }
        
        // 确保项目名称已设置（如果没有，从项目列表中获取）
        if (!item.projectName) {
          const project = invoiceProjects.value.find(p => p.id === item.projectId)
          if (project) {
            item.projectName = project.name
            console.log(`自动设置项目名称: ${item.projectName}`)
          }
        }
      }
      
      console.log('生成PDF前的报销项目数据:', JSON.stringify(reimbursementForm.items, null, 2))
    }

    // 如果是付款申请单，需要提示用户确认已上传附件和发票
    if (formType.value === 'payment') {
      try {
        await ElMessageBox.confirm(
          '请确认已上传附件和发票，以免造成统计出错。是否继续生成PDF？',
          '温馨提示',
          {
            confirmButtonText: '确认',
            cancelButtonText: '取消',
            type: 'warning'
          }
        )
      } catch {
        // 用户点击取消
        return
      }

      // 保存付款申请单历史记录
      savePaymentHistory()
    }

    generating.value = true
    ElMessage.info('正在生成PDF...')

    // 获取对应的PDF内容区域
    const pdfContent = formType.value === 'situation' 
      ? situationPdfRef.value 
      : formType.value === 'payment' 
        ? paymentPdfRef.value 
        : formType.value === 'travel'
          ? travelPdfRef.value
          : reimbursementPdfRef.value
    
    const canvas = await html2canvas(pdfContent, {
      scale: 2,
      useCORS: true,
      logging: false,
      backgroundColor: '#ffffff'
    })

    const imgData = canvas.toDataURL('image/png')
    
    // 差旅费报销单使用横向A4，其他表单使用竖向A4
    let doc
    let imgWidth, pageHeight, imgHeight, heightLeft
    
    if (formType.value === 'travel') {
      imgWidth = 297 // A4 landscape width in mm
      pageHeight = 210 // A4 landscape height in mm
      imgHeight = (canvas.height * imgWidth) / canvas.width
      heightLeft = imgHeight
      doc = new jsPDF('l', 'mm', 'a4') // 'l' for landscape
    } else {
      imgWidth = 210 // A4 portrait width in mm
      pageHeight = 297 // A4 portrait height in mm
      imgHeight = (canvas.height * imgWidth) / canvas.width
      heightLeft = imgHeight
      doc = new jsPDF('p', 'mm', 'a4') // 'p' for portrait
    }

    let position = 0

    doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight)
    heightLeft -= pageHeight

    while (heightLeft >= 0) {
      position = heightLeft - imgHeight
      doc.addPage()
      doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight)
      heightLeft -= pageHeight
    }

    const pdfBlob = doc.output('blob')

    const timestamp = new Date().getTime()
    const fileName = formType.value === 'situation' 
      ? `情况说明单_${timestamp}.pdf`
      : formType.value === 'payment'
        ? `付款申请单_${timestamp}.pdf`
        : formType.value === 'travel'
          ? `差旅费报销单_${timestamp}.pdf`
          : `报销单_${timestamp}.pdf`
    const pdfFile = new File([pdfBlob], fileName, { type: 'application/pdf' })

    ElMessage.success('PDF文件生成成功！')

    emit('word-generated', {
      file: pdfFile,
      fileName: fileName,
      formData: formType.value === 'situation' 
        ? { ...situationForm } 
        : formType.value === 'payment'
          ? { ...paymentForm }
          : formType.value === 'travel'
            ? { ...travelForm }
            : { ...reimbursementForm },
      formType: formType.value
    })

    handleClose()

  } catch (error) {
    if (error !== false) {
      console.error('Generate PDF error:', error)
      ElMessage.error('生成PDF失败，请重试')
    }
  } finally {
    generating.value = false
  }
}

// 监听差旅申请单的起止时间，自动计算天数
watch(() => [travelApplicationForm.startTime, travelApplicationForm.endTime], ([start, end]) => {
  if (start && end) {
    const startDate = new Date(start)
    const endDate = new Date(end)
    const diffTime = Math.abs(endDate - startDate)
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1 // 包含起始和结束日期
    travelApplicationForm.days = diffDays
  }
})

// 监听差旅申请单的小写金额，自动转换为大写
watch(() => travelApplicationForm.advanceAmountSmall, (amount) => {
  if (amount) {
    travelApplicationForm.advanceAmountLarge = convertToChinese(amount)
  } else {
    travelApplicationForm.advanceAmountLarge = ''
  }
})

// 监听差旅费表单items的变化，自动计算每行的金额
watch(() => travelForm.items, (items) => {
  items.forEach(item => {
    // 自动计算天数（根据起始和结束日期）
    if (item.startDate && item.endDate) {
      const start = new Date(item.startDate)
      const end = new Date(item.endDate)
      const diffTime = Math.abs(end - start)
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1 // 包含起始和结束日期
      item.totalDays = diffDays
    }
    
    // 自动计算伙食补助金额
    item.mealAmount = (item.mealDays || 0) * (item.mealStandard || 0)
    // 自动计算住宿补助金额
    item.accommodationAmount = (item.accommodationDays || 0) * (item.accommodationStandard || 0)
    // 自动计算未买卧铺补助金额
    item.noBerthAmount = (item.noBerthPrice || 0) * (item.noBerthStandard || 0)
  })
}, { deep: true })

// 监听差旅费总金额变化，自动计算报销和剩余交回
watch(() => getTravelTotalAmount(), (total) => {
  travelForm.reimbursementAmount = total
  travelForm.refundAmount = (travelForm.advanceAmount || 0) - total
})

watch(() => travelForm.advanceAmount, (advance) => {
  travelForm.refundAmount = (advance || 0) - getTravelTotalAmount()
})

// 监听props的附件和发票数量变化
watch(() => [props.invoiceCount, props.attachmentCount], ([newInvoiceCount, newAttachmentCount]) => {
  currentInvoiceCount.value = newInvoiceCount
  currentAttachmentCount.value = newAttachmentCount
})

// Watch modelValue changes
watch(() => props.modelValue, (val) => {
  dialogVisible.value = val
  if (val) {
    // Reset forms
    formType.value = 'situation'
    savedForms.value = []
    
    // 初始化附件和发票数量
    currentInvoiceCount.value = props.invoiceCount
    currentAttachmentCount.value = props.attachmentCount
    
    Object.assign(situationForm, {
      companyName: '鄂尔多斯市汇邦人力资源有限责任公司',
      date: new Date().toISOString().split('T')[0],
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
      paymentMethod: [],
      bank: '',
      bankAccount: '',
      purpose: '',
      invoiceStatus: [],
      paymentDate: new Date().toISOString().split('T')[0],
      remarks: ''
    })
    
    Object.assign(reimbursementForm, {
      companyName: '鄂尔多斯市汇邦人力资源有限责任公司',
      company: '鄂尔多斯市汇邦人力资源有限责任公司',
      date: new Date().toISOString().split('T')[0],
      items: [],
      applicant: '',
      bankName: '',
      cardNumber: '',
      paymentDate: '',
      remarks: '',
      verified: true,
      status: true,
      accounted: true
    })
    
    Object.assign(travelApplicationForm, {
      department: '',
      applyDate: new Date().toISOString().split('T')[0],
      name: '',
      reason: '',
      destination: '',
      startTime: '',
      endTime: '',
      days: 0,
      advanceAmountSmall: null,
      advanceAmountLarge: '',
      paymentDate: '',
      remarks: ''
    })
    
    Object.assign(travelForm, {
      name: '',
      date: new Date().toISOString().split('T')[0],
      items: [],
      advanceAmount: 0,
      reimbursementAmount: 0,
      refundAmount: 0,
      reason: ''
    })
    
    if (situationFormRef.value) {
      situationFormRef.value.clearValidate()
    }
    if (paymentFormRef.value) {
      paymentFormRef.value.clearValidate()
    }
    if (reimbursementFormRef.value) {
      reimbursementFormRef.value.clearValidate()
    }
    if (travelApplicationFormRef.value) {
      travelApplicationFormRef.value.clearValidate()
    }
    if (travelFormRef.value) {
      travelFormRef.value.clearValidate()
    }
  }
})

watch(dialogVisible, (val) => {
  if (!val) {
    emit('update:modelValue', false)
  }
})
</script>

<style scoped>
:deep(.el-dialog__body) {
  max-height: 65vh;
  overflow-y: auto;
}

.form-type-selector {
  display: flex;
  justify-content: center;
  margin-bottom: 10px;
}

:deep(.el-radio-button__inner) {
  padding: 12px 30px;
  font-size: 15px;
  font-weight: 500;
}
</style>
