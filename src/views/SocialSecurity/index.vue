<template>
  <div class="social-security-container">
    <div class="page-header">
      <h2>社保与医保管理</h2>
    </div>

    <!-- 标签页切换 -->
    <el-tabs v-model="activeTab" type="card" class="tabs-container">
      <!-- 社保细分标签页 -->
      <el-tab-pane label="社保细分" name="social">
        <div class="tab-header">
          <el-button v-if="canCreateSocialSecurity" type="primary" @click="showCreateDialog = true">
            <el-icon><Plus /></el-icon>
            新建社保地区
          </el-button>
        </div>

        <!-- 社保地区列表 -->
    <el-card class="region-list-card">
      <template #header>
        <div class="card-header">
          <span>社保地区列表</span>
        </div>
      </template>

      <el-table :data="regions" v-loading="loading" stripe @selection-change="handleSelectionChange">
        <el-table-column type="selection" width="55" />
        <el-table-column prop="name" label="地区名称" width="200" />
        <el-table-column prop="code" label="社保编号" width="180" />
        <el-table-column label="进行中上下限" width="230">
          <template #default="{ row }">
            ¥{{ Number(row.current_limits?.min_base_amount ?? row.min_base_amount ?? 0).toFixed(2) }} -
            ¥{{ Number(row.current_limits?.max_base_amount ?? row.max_base_amount ?? 0).toFixed(2) }}
          </template>
        </el-table-column>
        <el-table-column label="待生效上下限" width="230">
          <template #default="{ row }">
            <span v-if="row.pending_limits">
              ¥{{ Number(row.pending_limits.min_base_amount || 0).toFixed(2) }} -
              ¥{{ Number(row.pending_limits.max_base_amount || 0).toFixed(2) }}
            </span>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column label="待生效日期" width="130">
          <template #default="{ row }">
            {{ row.pending_limits?.effective_date || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="creator.name" label="创建人" width="120" />
        <el-table-column prop="created_at" label="创建时间" width="180" />
        <el-table-column label="社保类型数量" width="120">
          <template #default="{ row }">
            <el-tag type="info">{{ row.social_security_types?.length || 0 }} 种</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="500">
          <template #default="{ row }">
            <el-button type="primary" size="small" @click="viewTypes(row)">
              查看类型
            </el-button>
            <el-button
              type="info"
              size="small"
              @click="showSocialRegionHistory(row)"
            >
              历史
            </el-button>
            <el-button type="warning" size="small" @click="editRegion(row)">
              编辑
            </el-button>
            <el-button
              v-if="row.adjustment_base && row.effective_date"
              type="info"
              size="small"
              @click="cancelAdjustment(row)"
            >
              取消调整
            </el-button>
            <el-button type="danger" size="small" @click="deleteRegion(row)">
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 创建/编辑地区对话框 -->
    <el-dialog
      v-model="showCreateDialog"
      :title="editingRegion ? '编辑社保地区' : '新建社保地区'"
      width="500px"
    >
      <el-form :model="regionForm" :rules="regionRules" ref="regionFormRef" label-width="120px">
        <el-form-item label="地区名称" prop="name">
          <el-input v-model="regionForm.name" placeholder="请输入地区名称" />
        </el-form-item>
        <el-form-item label="社保编号" prop="code">
          <el-input v-model="regionForm.code" placeholder="请输入社保编号（可选）" />
        </el-form-item>
        <el-form-item label="单位" prop="company">
          <el-input v-model="regionForm.company" placeholder="请输入单位/公司名称" />
        </el-form-item>
        <el-form-item label="最低基数" prop="min_base_amount">
          <el-input-number
            v-model="regionForm.min_base_amount"
            :min="0"
            :precision="2"
            placeholder="请输入最低基数"
            style="width: 100%"
          />
          <div class="form-tip">该地区所有社保类型共用此基数下限</div>
        </el-form-item>
        <el-form-item label="最高基数" prop="max_base_amount">
          <el-input-number
            v-model="regionForm.max_base_amount"
            :min="0"
            :precision="2"
            placeholder="请输入最高基数"
            style="width: 100%"
          />
          <div class="form-tip">该地区所有社保类型共用此基数上限</div>
        </el-form-item>
        <el-form-item label="上下限生效日期" prop="limit_effective_date" v-if="editingRegion">
          <el-date-picker
            v-model="regionForm.limit_effective_date"
            type="date"
            value-format="YYYY-MM-DD"
            placeholder="请选择上下限生效日期"
            style="width: 100%"
          />
          <div class="form-tip" v-if="editingRegion?.pending_limits">
            当前待生效：¥{{ Number(editingRegion.pending_limits.min_base_amount || 0).toFixed(2) }} -
            ¥{{ Number(editingRegion.pending_limits.max_base_amount || 0).toFixed(2) }}，生效日：{{ editingRegion.pending_limits.effective_date }}
          </div>
          <div class="form-tip">仅修改上下限时必填，生效前 current 不变</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitRegion" :loading="submitting">
          {{ editingRegion ? '更新' : '创建' }}
        </el-button>
      </template>
    </el-dialog>

    <!-- 社保类型管理对话框 -->
    <el-dialog
      v-model="showTypesDialog"
      :title="`${currentRegion?.name} - 社保类型管理`"
      width="800px"
    >
      <div class="types-header">
        <el-button type="primary" @click="openAddTypeDialog">
          <el-icon><Plus /></el-icon>
          添加社保类型
        </el-button>
      </div>

      <el-table :data="currentRegion?.social_security_types || []" stripe class="types-table">
        <el-table-column prop="name" label="保险名称" width="200" />
        <el-table-column prop="employee_ratio" label="员工缴纳比例" width="150">
          <template #default="{ row }">
            {{ (row.employee_ratio * 100).toFixed(2) }}%
          </template>
        </el-table-column>
        <el-table-column prop="company_ratio" label="公司缴纳比例" width="150">
          <template #default="{ row }">
            {{ (row.company_ratio * 100).toFixed(2) }}%
          </template>
        </el-table-column>
        <el-table-column prop="unit" label="单位" width="100">
          <template #default="{ row }">
            {{ row.unit || '元' }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="150">
          <template #default="{ row }">
            <el-button type="warning" size="small" @click="editType(row)">
              编辑
            </el-button>
            <el-button type="danger" size="small" @click="deleteType(row)">
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>
      <div class="form-tip" style="margin-top: 10px;">
        基数上下限已统一在地区层级设置，该地区的最低基数: ¥{{ currentRegion?.min_base_amount || 0 }}，最高基数: ¥{{ currentRegion?.max_base_amount || 0 }}
      </div>
    </el-dialog>

    <!-- 添加/编辑社保类型对话框 -->
    <el-dialog
      v-model="showAddTypeDialog"
      :title="editingType ? '编辑社保类型' : '添加社保类型'"
      width="500px"
      @closed="resetTypeForm"
    >
      <el-form :model="typeForm" :rules="typeRules" ref="typeFormRef" label-width="120px">
        <el-form-item label="保险名称" prop="name">
          <el-input v-model="typeForm.name" placeholder="请输入保险名称，如：养老保险" />
          <div class="form-tip">基数上下限已在地区层级统一设置</div>
        </el-form-item>
        <el-form-item label="仅单位缴纳">
          <el-switch v-model="typeForm.only_company_pay" @change="handleOnlyCompanyPayChange" />
        </el-form-item>
        <el-form-item label="员工缴纳比例" prop="employee_ratio">
          <el-input-number
            v-model="typeForm.employee_ratio"
            :min="0"
            :max="100"
            :precision="2"
            :step="0.01"
            placeholder="请输入员工缴纳比例（%）"
            style="width: 100%"
            :disabled="typeForm.only_company_pay"
          />
          <div class="form-tip">例如：8 表示 8%</div>
        </el-form-item>
        <el-form-item label="公司缴纳比例" prop="company_ratio">
          <el-input-number
            v-model="typeForm.company_ratio"
            :min="0"
            :max="100"
            :precision="2"
            :step="0.01"
            placeholder="请输入公司缴纳比例（%）"
            style="width: 100%"
          />
          <div class="form-tip">例如：16 表示 16%</div>
        </el-form-item>
        <el-form-item label="单位" prop="unit">
          <el-input v-model="typeForm.unit" placeholder="请输入单位，如：元" />
          <div class="form-tip">缴纳金额的单位，通常为"元"</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAddTypeDialog = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitType" :loading="submitting">
          {{ editingType ? '更新' : '添加' }}
        </el-button>
      </template>
    </el-dialog>
      </el-tab-pane>

      <!-- 医保细分标签页 -->
      <el-tab-pane label="医保细分" name="medical">
        <div class="tab-header">
          <el-button type="primary" @click="showCreateMedicalDialog = true">
            <el-icon><Plus /></el-icon>
            新建医保地区
          </el-button>
        </div>

        <!-- 医保地区列表 -->
        <el-card class="region-list-card">
          <template #header>
            <div class="card-header">
              <span>医保地区列表</span>
            </div>
          </template>

          <el-table :data="medicalRegions" v-loading="medicalLoading" stripe @selection-change="handleMedicalSelectionChange">
            <el-table-column type="selection" width="55" />
            <el-table-column prop="name" label="地区名称" width="200" />
            <el-table-column prop="code" label="医保编号" width="180" />
            <el-table-column label="进行中上下限" width="230">
              <template #default="{ row }">
                ¥{{ Number(row.current_limits?.min_base_amount ?? row.min_base_amount ?? 0).toFixed(2) }} -
                ¥{{ Number(row.current_limits?.max_base_amount ?? row.max_base_amount ?? 0).toFixed(2) }}
              </template>
            </el-table-column>
            <el-table-column label="待生效上下限" width="230">
              <template #default="{ row }">
                <span v-if="row.pending_limits">
                  ¥{{ Number(row.pending_limits.min_base_amount || 0).toFixed(2) }} -
                  ¥{{ Number(row.pending_limits.max_base_amount || 0).toFixed(2) }}
                </span>
                <span v-else>-</span>
              </template>
            </el-table-column>
            <el-table-column label="待生效日期" width="130">
              <template #default="{ row }">
                {{ row.pending_limits?.effective_date || '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="creator.name" label="创建人" width="120" />
            <el-table-column prop="created_at" label="创建时间" width="180" />
            <el-table-column label="员工缴纳比例" width="130">
              <template #default="{ row }">
                <span v-if="row.medical_insurance_types?.[0]">
                  {{ (Number(row.medical_insurance_types[0].employee_ratio || 0) * 100).toFixed(2) }}%
                </span>
                <span v-else>-</span>
              </template>
            </el-table-column>
            <el-table-column label="公司缴纳比例" width="130">
              <template #default="{ row }">
                <span v-if="row.medical_insurance_types?.[0]">
                  {{ (Number(row.medical_insurance_types[0].company_ratio || 0) * 100).toFixed(2) }}%
                </span>
                <span v-else>-</span>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="260">
              <template #default="{ row }">
                <el-button type="info" size="small" @click="showMedicalRegionHistory(row)">
                  历史
                </el-button>
                <el-button type="warning" size="small" @click="editMedicalRegion(row)">
                  编辑
                </el-button>
                <el-button type="danger" size="small" @click="deleteMedicalRegion(row)">
                  删除
                </el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>

        <!-- 创建/编辑医保地区对话框 -->
        <el-dialog
          v-model="showCreateMedicalDialog"
          :title="editingMedicalRegion ? '编辑医保地区' : '新建医保地区'"
          width="500px"
        >
          <el-form :model="medicalRegionForm" :rules="regionRules" ref="medicalRegionFormRef" label-width="120px">
            <el-form-item label="地区名称" prop="name">
              <el-input v-model="medicalRegionForm.name" placeholder="请输入地区名称" />
            </el-form-item>
            <el-form-item label="医保编号" prop="code">
              <el-input v-model="medicalRegionForm.code" placeholder="请输入医保编号（可选）" />
            </el-form-item>
            <el-form-item label="单位" prop="company">
              <el-input v-model="medicalRegionForm.company" placeholder="请输入单位/公司名称" />
            </el-form-item>
            <el-form-item label="最低基数" prop="min_base_amount">
              <el-input-number
                v-model="medicalRegionForm.min_base_amount"
                :min="0"
                :precision="2"
                placeholder="请输入最低基数"
                style="width: 100%"
              />
              <div class="form-tip">该地区所有医保类型共用此基数下限</div>
            </el-form-item>
            <el-form-item label="最高基数" prop="max_base_amount">
              <el-input-number
                v-model="medicalRegionForm.max_base_amount"
                :min="0"
                :precision="2"
                placeholder="请输入最高基数"
                style="width: 100%"
              />
              <div class="form-tip">该地区所有医保类型共用此基数上限</div>
            </el-form-item>
            <el-form-item label="员工缴纳比例">
              <el-input-number
                v-model="medicalRegionForm.type_employee_ratio"
                :min="0"
                :max="100"
                :precision="2"
                :step="0.01"
                placeholder="请输入员工缴纳比例（%）"
                style="width: 100%"
              />
            </el-form-item>
            <el-form-item label="公司缴纳比例">
              <el-input-number
                v-model="medicalRegionForm.type_company_ratio"
                :min="0"
                :max="100"
                :precision="2"
                :step="0.01"
                placeholder="请输入公司缴纳比例（%）"
                style="width: 100%"
              />
            </el-form-item>
            <el-form-item label="上下限生效日期" prop="limit_effective_date" v-if="editingMedicalRegion">
              <el-date-picker
                v-model="medicalRegionForm.limit_effective_date"
                type="date"
                value-format="YYYY-MM-DD"
                placeholder="请选择上下限生效日期"
                style="width: 100%"
              />
              <div class="form-tip" v-if="editingMedicalRegion?.pending_limits">
                当前待生效：¥{{ Number(editingMedicalRegion.pending_limits.min_base_amount || 0).toFixed(2) }} -
                ¥{{ Number(editingMedicalRegion.pending_limits.max_base_amount || 0).toFixed(2) }}，生效日：{{ editingMedicalRegion.pending_limits.effective_date }}
              </div>
              <div class="form-tip">仅修改上下限时必填，生效前 current 不变</div>
            </el-form-item>
          </el-form>
          <template #footer>
            <el-button @click="showCreateMedicalDialog = false">取消</el-button>
            <el-button type="primary" @click="handleSubmitMedicalRegion" :loading="submitting">
              {{ editingMedicalRegion ? '更新' : '创建' }}
            </el-button>
          </template>
        </el-dialog>

        <!-- 医保类型管理对话框 -->
        <el-dialog
          v-model="showMedicalTypesDialog"
          :title="`${currentMedicalRegion?.name} - 医保类型管理`"
          width="800px"
        >
          <div class="types-header">
            <el-button
              v-if="(currentMedicalRegion?.medical_insurance_types?.length || 0) === 0"
              type="primary"
              @click="showAddMedicalTypeDialog = true"
            >
              <el-icon><Plus /></el-icon>
              添加医保类型
            </el-button>
            <div v-else class="form-tip">该地区已存在配置，如需调整请编辑当前配置</div>
          </div>

          <el-table :data="currentMedicalRegion?.medical_insurance_types || []" stripe class="types-table">
            <el-table-column prop="name" label="保险名称" width="200" />
            <el-table-column prop="employee_ratio" label="员工缴纳比例" width="150">
              <template #default="{ row }">
                {{ (row.employee_ratio * 100).toFixed(2) }}%
              </template>
            </el-table-column>
            <el-table-column prop="company_ratio" label="公司缴纳比例" width="150">
              <template #default="{ row }">
                {{ (row.company_ratio * 100).toFixed(2) }}%
              </template>
            </el-table-column>
            <el-table-column prop="unit" label="单位" width="100">
              <template #default="{ row }">
                {{ row.unit || '元' }}
              </template>
            </el-table-column>
            <el-table-column label="操作" width="150">
              <template #default="{ row }">
                <el-button type="warning" size="small" @click="editMedicalType(row)">
                  编辑
                </el-button>
                <el-button type="danger" size="small" @click="deleteMedicalType(row)">
                  删除
                </el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="form-tip" style="margin-top: 10px;">
            基数上下限已统一在地区层级设置，该地区的最低基数: ¥{{ currentMedicalRegion?.min_base_amount || 0 }}，最高基数: ¥{{ currentMedicalRegion?.max_base_amount || 0 }}
          </div>
        </el-dialog>

        <!-- 添加/编辑医保类型对话框 -->
        <el-dialog
          v-model="showAddMedicalTypeDialog"
          :title="editingMedicalType ? '编辑医保类型' : '添加医保类型'"
          width="500px"
        >
          <el-form :model="medicalTypeForm" :rules="typeRules" ref="medicalTypeFormRef" label-width="120px">
            <el-form-item label="保险名称" prop="name">
              <el-input v-model="medicalTypeForm.name" placeholder="请输入保险名称，如：医疗保险" />
              <div class="form-tip">基数上下限已在地区层级统一设置</div>
            </el-form-item>
            <el-form-item label="员工缴纳比例" prop="employee_ratio">
              <el-input-number
                v-model="medicalTypeForm.employee_ratio"
                :min="0"
                :max="100"
                :precision="2"
                :step="0.01"
                placeholder="请输入员工缴纳比例（%）"
                style="width: 100%"
              />
              <div class="form-tip">例如：2 表示 2%</div>
            </el-form-item>
            <el-form-item label="公司缴纳比例" prop="company_ratio">
              <el-input-number
                v-model="medicalTypeForm.company_ratio"
                :min="0"
                :max="100"
                :precision="2"
                :step="0.01"
                placeholder="请输入公司缴纳比例（%）"
                style="width: 100%"
              />
              <div class="form-tip">例如：10 表示 10%</div>
            </el-form-item>
          </el-form>
          <template #footer>
            <el-button @click="showAddMedicalTypeDialog = false">取消</el-button>
            <el-button type="primary" @click="handleSubmitMedicalType" :loading="submitting">
              {{ editingMedicalType ? '更新' : '添加' }}
            </el-button>
          </template>
        </el-dialog>
      </el-tab-pane>

      <el-tab-pane label="大额医疗" name="large-medical">
        <LargeMedicalInsuranceView />
      </el-tab-pane>
    </el-tabs>

    <el-dialog
      v-model="showRegionHistoryDialog"
      :title="regionHistoryTitle"
      width="600px"
    >
      <el-table :data="regionHistories" v-loading="regionHistoryLoading" stripe>
        <el-table-column prop="changed_at" label="修改时间" width="180" />
        <el-table-column prop="min_base_amount" label="下限基数" width="180">
          <template #default="{ row }">
            {{ row.min_base_amount === null || row.min_base_amount === undefined ? '-' : `¥${Number(row.min_base_amount).toFixed(2)}` }}
          </template>
        </el-table-column>
        <el-table-column prop="max_base_amount" label="上限基数" width="180">
          <template #default="{ row }">
            {{ row.max_base_amount === null || row.max_base_amount === undefined ? '-' : `¥${Number(row.max_base_amount).toFixed(2)}` }}
          </template>
        </el-table-column>
      </el-table>
    </el-dialog>

    <!-- 复制模板对话框 -->
    <el-dialog
      v-model="showCopyTemplateDialog"
      title="复制模板到其他地区"
      width="500px"
    >
      <el-form label-width="100px">
        <el-form-item label="源地区">
          <el-input :value="copyTemplateSource?.name" disabled />
        </el-form-item>
        <el-form-item label="目标地区" required>
          <el-select
            v-model="copyTargetRegionIds"
            multiple
            filterable
            placeholder="请选择目标地区（可多选）"
            style="width: 100%"
          >
            <el-option
              v-for="region in copyTargetRegionOptions"
              :key="region.id"
              :label="region.name"
              :value="region.id"
            />
          </el-select>
        </el-form-item>
        <div class="form-tip" style="color: #909399; font-size: 12px; margin-top: -10px;">
          如果目标地区已有模板，将会被覆盖
        </div>
      </el-form>
      <template #footer>
        <el-button @click="showCopyTemplateDialog = false">取消</el-button>
        <el-button type="primary" @click="handleCopyTemplate" :loading="copyingTemplate">
          确认复制
        </el-button>
      </template>
    </el-dialog>

    <!-- 模板设计器组件 -->
    <ReportTemplateDesigner
      v-model:visible="showTemplateDesigner"
      :available-fields="availableFields"
      :template-type="currentTemplateType"
      :region="currentTemplateRegion"
      :region-name-field="'name'"
      :is-batch-mode="isBatchCreateMode"
      :batch-regions="batchCreateRegions"
      :edit-template="editingTemplate"
      :account-set-id="currentAccountSetId"
      @save="handleTemplateSaved"
      @close="handleTemplateDesignerClose"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Delete, Back, Right, Grid, Document, Close, Edit } from '@element-plus/icons-vue'
import { useAccountSetStore } from '@/stores/accountSet'
import { usePermissionStore } from '@/stores/permission'
import request from '@/api/request'
import ReportTemplateDesigner from '@/components/ReportTemplateDesigner.vue'
import LargeMedicalInsuranceView from '@/views/LargeMedicalInsurance/index.vue'
import {
  getSocialSecurityRegions,
  getSocialSecurityRegion,
  createSocialSecurityRegion,
  updateSocialSecurityRegion,
  deleteSocialSecurityRegion,
  addSocialSecurityType,
  updateSocialSecurityType,
  deleteSocialSecurityType,
  getSocialSecurityRegionLimitHistories
} from '@/api/socialSecurity'
import {
  getMedicalInsuranceRegions,
  getMedicalInsuranceRegion,
  createMedicalInsuranceRegion,
  updateMedicalInsuranceRegion,
  deleteMedicalInsuranceRegion,
  addMedicalInsuranceType,
  updateMedicalInsuranceType,
  deleteMedicalInsuranceType,
  getMedicalInsuranceRegionLimitHistories
} from '@/api/medicalInsurance'

const route = useRoute()
const router = useRouter()
const accountSetStore = useAccountSetStore()
const permissionStore = usePermissionStore()

// 计算属性
const currentAccountSetId = computed(() => accountSetStore.currentAccountSetId)

// 权限控制
const canCreateSocialSecurity = computed(() => permissionStore.hasPermission('social_security.create'))
const canEditSocialSecurity = computed(() => permissionStore.hasPermission('social_security.edit'))
const canDeleteSocialSecurity = computed(() => permissionStore.hasPermission('social_security.delete'))

// 检查是否可以调整基数
const canAdjustBase = computed(() => {
  const currentAccountSet = accountSetStore.currentAccountSet
  if (!currentAccountSet || !currentAccountSet.base_adjustment_months) {
    return false
  }
  
  const currentMonth = new Date().getMonth() + 1 // 获取当前月份 (1-12)
  return currentAccountSet.base_adjustment_months.includes(currentMonth)
})

// 复制模板目标地区选项（排除源地区）
const copyTargetRegionOptions = computed(() => {
  if (!copyTemplateSource.value) return []
  
  if (copyTemplateType.value === 'social_security') {
    return regions.value.filter(r => r.id !== copyTemplateSource.value.id)
  } else if (copyTemplateType.value === 'medical_insurance') {
    return medicalRegions.value.filter(r => r.id !== copyTemplateSource.value.id)
  }
  return []
})

// 标签页
const tabRouteMap = {
  '/social-security': 'social',
  '/large-medical-insurance': 'large-medical'
}

const activeTab = ref(tabRouteMap[route.path] || 'social')

// 模板设计器相关
const showTemplateDesigner = ref(false)
const currentTemplateRegion = ref(null)
const currentTemplateType = ref('social_security') // 'social_security', 'medical_insurance', 'housing_fund'
const isBatchCreateMode = ref(false)
const batchCreateRegions = ref([])
const isEditMode = ref(false)
const editingTemplateId = ref(null)
const editingTemplate = ref(null)
const templateForm = ref({
  name: '',
  description: ''
})

// 复制模板相关
const showCopyTemplateDialog = ref(false)
const copyTemplateSource = ref(null)
const copyTemplateType = ref('')
const copyTargetRegionIds = ref([])
const copyingTemplate = ref(false)

// 侧边栏标签页
const sidebarTab = ref('header')

// 表格设计器相关
const templateColumns = ref([])
const selectedColumnIndex = ref(null)
const previewTableRef = ref()
const draggedField = ref(null)

// 表头字段相关
const reportTitle = ref('')
const showEditTitleDialog = ref(false)
const reportHeaderFields = ref([])
const selectedHeaderFieldIndex = ref(null)
const showAddHeaderFieldDialog = ref(false)
const newHeaderField = ref({
  label: '',
  type: 'system',
  systemField: '',
  value: '',
  dateFormat: 'YYYY-MM-DD',
  row: 1
})

// 表尾字段相关
const reportFooterFields = ref([])
const selectedFooterFieldIndex = ref(null)
const showAddFooterFieldDialog = ref(false)
const newFooterField = ref({
  label: '',
  type: 'text',
  systemField: '',
  value: '',
  dateFormat: 'YYYY-MM-DD',
  row: 1
})

// 选中的表头字段
const selectedHeaderField = computed(() => {
  if (selectedHeaderFieldIndex.value !== null && reportHeaderFields.value[selectedHeaderFieldIndex.value]) {
    return reportHeaderFields.value[selectedHeaderFieldIndex.value]
  }
  return null
})

// 选中的列
const selectedColumn = computed(() => {
  if (selectedColumnIndex.value !== null && templateColumns.value[selectedColumnIndex.value]) {
    return templateColumns.value[selectedColumnIndex.value]
  }
  return null
})

// 是否可以左移
const canMoveLeft = computed(() => {
  return selectedColumnIndex.value !== null && selectedColumnIndex.value > 0
})

// 是否可以右移
const canMoveRight = computed(() => {
  return selectedColumnIndex.value !== null && selectedColumnIndex.value < templateColumns.value.length - 1
})

// 按行分组表头字段
const groupedHeaderFields = computed(() => {
  const groups = {}
  reportHeaderFields.value.forEach((field, index) => {
    const row = field.row || 1
    if (!groups[row]) {
      groups[row] = []
    }
    groups[row].push({ ...field, index })
  })
  return groups
})

// 按行分组表尾字段
const groupedFooterFields = computed(() => {
  const groups = {}
  reportFooterFields.value.forEach((field, index) => {
    const row = field.row || 1
    if (!groups[row]) {
      groups[row] = []
    }
    groups[row].push({ ...field, index })
  })
  return groups
})

// 预览数据（示例数据）
const previewData = ref([
  {
    serial_number: 1,
    employee_name: '张三',
    id_type: '居民身份证',
    id_number: '110101199001011234',
    project_name: '项目A',
    enrollment_date: '2023-01-15',
    type: '正常',
    period: '2026-01',
    medical_base: 10000,
    social_security_base: 10000,
    housing_fund_base: 10000,
    pension_company: 1600,
    pension_employee: 800,
    pension_ratio_company: 16,
    pension_ratio_employee: 8,
    medical_company: 800,
    medical_employee: 200,
    medical_ratio_company: 8,
    medical_ratio_employee: 2,
    unemployment_company: 70,
    unemployment_employee: 30,
    unemployment_ratio_company: 0.7,
    unemployment_ratio_employee: 0.3,
    injury_company: 80,
    injury_ratio_company: 0.8,
    maternity_company: 80,
    maternity_ratio_company: 0.8,
    housing_fund_company: 1200,
    housing_fund_employee: 1200,
    housing_fund_ratio: 12,
    company_total: 3830,
    employee_total: 2230,
    social_security_total: 3660,
    grand_total: 6060,
    remarks: '',
    current_user_name: '管理员',
    current_user_phone: '13800138000'
  },
  {
    serial_number: 2,
    employee_name: '李四',
    id_type: '居民身份证',
    id_number: '110101199102021234',
    project_name: '项目B',
    enrollment_date: '2022-06-20',
    type: '正常',
    period: '2026-01',
    medical_base: 15000,
    social_security_base: 15000,
    housing_fund_base: 15000,
    pension_company: 2400,
    pension_employee: 1200,
    pension_ratio_company: 16,
    pension_ratio_employee: 8,
    medical_company: 1200,
    medical_employee: 300,
    medical_ratio_company: 8,
    medical_ratio_employee: 2,
    unemployment_company: 105,
    unemployment_employee: 45,
    unemployment_ratio_company: 0.7,
    unemployment_ratio_employee: 0.3,
    injury_company: 120,
    injury_ratio_company: 0.8,
    maternity_company: 120,
    maternity_ratio_company: 0.8,
    housing_fund_company: 1800,
    housing_fund_employee: 1800,
    housing_fund_ratio: 12,
    company_total: 5745,
    employee_total: 3345,
    social_security_total: 5490,
    grand_total: 9090,
    remarks: '',
    current_user_name: '管理员',
    current_user_phone: '13800138000'
  }
])

// 可用的字段列表
const availableFields = ref([
  // 基础信息
  { key: 'serial_number', label: '序号', isSerial: true },
  { key: 'employee_name', label: '员工姓名' },
  { key: 'employee_number', label: '员工工号' },
  { key: 'id_type', label: '身份证件类型' },
  { key: 'id_number', label: '身份证号' },
  { key: 'project_name', label: '项目名称' },
  { key: 'enrollment_date', label: '参保日期' },
  { key: 'type', label: '类型' },
  { key: 'change_type', label: '变更类型' },
  { key: 'period', label: '费款所属期' },
  
  // 工资
  { key: 'basic_salary', label: '月工资额' },
  
  // 基数
  { key: 'medical_base', label: '医保基数' },
  { key: 'social_security_base', label: '社保基数' },
  { key: 'housing_fund_base', label: '公积金基数' },
  
  // 社保 - 养老保险
  { key: 'pension_company', label: '养老保险-单位' },
  { key: 'pension_employee', label: '养老保险-个人' },
  { key: 'pension_ratio_company', label: '养老保险-单位比例' },
  { key: 'pension_ratio_employee', label: '养老保险-个人比例' },
  
  // 社保 - 医疗保险
  { key: 'medical_company', label: '医疗保险-单位' },
  { key: 'medical_employee', label: '医疗保险-个人' },
  { key: 'medical_ratio_company', label: '医疗保险-单位比例' },
  { key: 'medical_ratio_employee', label: '医疗保险-个人比例' },
  
  // 社保 - 失业保险
  { key: 'unemployment_company', label: '失业保险-单位' },
  { key: 'unemployment_employee', label: '失业保险-个人' },
  { key: 'unemployment_ratio_company', label: '失业保险-单位比例' },
  { key: 'unemployment_ratio_employee', label: '失业保险-个人比例' },
  
  // 社保 - 工伤保险
  { key: 'injury_company', label: '工伤保险-单位' },
  { key: 'injury_ratio_company', label: '工伤保险-单位比例' },
  
  // 社保 - 生育保险
  { key: 'maternity_company', label: '生育保险-单位' },
  { key: 'maternity_ratio_company', label: '生育保险-单位比例' },
  
  // 公积金
  { key: 'housing_fund_company', label: '公积金-单位' },
  { key: 'housing_fund_employee', label: '公积金-个人' },
  { key: 'housing_fund_ratio', label: '公积金-比例' },
  
  // 合计
  { key: 'company_total', label: '单位缴纳合计' },
  { key: 'employee_total', label: '个人缴纳合计' },
  { key: 'social_security_total', label: '社保合计' },
  { key: 'grand_total', label: '总计' },
  
  // 其他
  { key: 'remarks', label: '备注' },
  
  // 当前用户信息
  { key: 'current_user_name', label: '当前用户昵称' },
  { key: 'current_user_phone', label: '当前用户手机号' }
])

// 社保响应式数据
const loading = ref(false)
const submitting = ref(false)
const regions = ref([])
const selectedRegions = ref([])
const showCreateDialog = ref(false)
const showTypesDialog = ref(false)
const showAddTypeDialog = ref(false)
const editingRegion = ref(null)
const editingType = ref(null)
const currentRegion = ref(null)
const showRegionHistoryDialog = ref(false)
const regionHistoryLoading = ref(false)
const regionHistories = ref([])
const regionHistoryTitle = ref('')

// 基数调整相关
const showAdjustmentDialogFlag = ref(false)
const currentAdjustmentRegion = ref({})
const adjustmentForm = ref({
  adjustment_base: null,
  effective_date: null
})
const adjustmentFormRef = ref()

// 医保响应式数据
const medicalLoading = ref(false)
const medicalRegions = ref([])
const selectedMedicalRegions = ref([])
const showCreateMedicalDialog = ref(false)
const showMedicalTypesDialog = ref(false)
const showAddMedicalTypeDialog = ref(false)
const editingMedicalRegion = ref(null)
const editingMedicalType = ref(null)
const currentMedicalRegion = ref(null)

// 表单数据
const regionForm = ref({
  name: '',
  code: '',
  company: '',
  min_base_amount: null,
  max_base_amount: null,
  limit_effective_date: ''
})

const typeForm = ref({
  name: '',
  employee_ratio: 0,
  company_ratio: 0,
  unit: '元',
  only_company_pay: false
})

// 基数组自定义验证器：最低基数、最高基数、生效时间必须同时填写或同时为空
const validateBaseAmountGroup = (rule, value, callback) => {
  // 新建模式不验证
  if (!editingRegion.value) {
    callback()
    return
  }

  const { min_base_amount, max_base_amount, limit_effective_date } = regionForm.value

  // 三个都为空或都填写，通过验证
  const allEmpty = !min_base_amount && !max_base_amount && !limit_effective_date
  const allFilled = min_base_amount && max_base_amount && limit_effective_date

  if (allEmpty || allFilled) {
    callback()
  } else {
    callback(new Error('最低基数、最高基数、生效时间必须同时填写，或同时为空'))
  }
}

// 表单验证规则
const regionRules = {
  name: [
    { required: true, message: '请输入地区名称', trigger: 'blur' },
    { min: 2, max: 100, message: '地区名称长度在 2 到 100 个字符', trigger: 'blur' }
  ],
  min_base_amount: [
    { validator: validateBaseAmountGroup, trigger: 'change' }
  ],
  max_base_amount: [
    { validator: validateBaseAmountGroup, trigger: 'change' }
  ],
  limit_effective_date: [
    { validator: validateBaseAmountGroup, trigger: 'change' }
  ]
}

const typeRules = {
  name: [
    { required: true, message: '请输入保险名称', trigger: 'blur' },
    { min: 2, max: 100, message: '保险名称长度在 2 到 100 个字符', trigger: 'blur' }
  ],
  employee_ratio: [
    { required: true, message: '请输入员工缴纳比例', trigger: 'blur' }
  ],
  company_ratio: [
    { required: true, message: '请输入公司缴纳比例', trigger: 'blur' }
  ]
}

// 基数调整验证规则
const adjustmentRules = {
  adjustment_base: [
    { required: true, message: '请输入调整基数', trigger: 'blur' },
    { type: 'number', min: 0, message: '调整基数必须大于等于0', trigger: 'blur' }
  ],
  effective_date: [
    { required: true, message: '请选择生效时间', trigger: 'change' }
  ]
}

// 医保表单数据
const medicalRegionForm = ref({
  name: '',
  code: '',
  company: '',
  min_base_amount: null,
  max_base_amount: null,
  limit_effective_date: '',
  type_id: null,
  type_name: '默认配置',
  type_employee_ratio: 0,
  type_company_ratio: 0
})

const medicalTypeForm = ref({
  name: '',
  employee_ratio: 0,
  company_ratio: 0
})

// 表单引用
const regionFormRef = ref()
const typeFormRef = ref()
const medicalRegionFormRef = ref()
const medicalTypeFormRef = ref()

// 加载数据
const loadRegions = async () => {
  if (!currentAccountSetId.value) {
    ElMessage.warning('请先选择账套')
    return
  }

  loading.value = true
  try {
    const response = await getSocialSecurityRegions({
      account_set_id: currentAccountSetId.value
    })
    regions.value = response.data
  } catch (error) {
    console.error('加载社保地区失败:', error)
    ElMessage.error('加载社保地区失败')
  } finally {
    loading.value = false
  }
}

// 查看类型
const viewTypes = (region) => {
  currentRegion.value = region
  showTypesDialog.value = true
}

const showSocialRegionHistory = async (region) => {
  regionHistoryTitle.value = `${region.name} - 上下限历史`
  showRegionHistoryDialog.value = true
  regionHistoryLoading.value = true
  try {
    const response = await getSocialSecurityRegionLimitHistories(region.id)
    regionHistories.value = response.data || []
  } catch (error) {
    console.error('加载社保上下限历史失败:', error)
    ElMessage.error('加载历史失败')
    regionHistories.value = []
  } finally {
    regionHistoryLoading.value = false
  }
}

// 编辑地区
const editRegion = async (region) => {
  try {
    // 从服务器获取最新数据
    const response = await getSocialSecurityRegion(region.id)
    const latestRegion = response.data
    
    editingRegion.value = latestRegion
    regionForm.value = {
      name: latestRegion.name,
      code: latestRegion.code || '',
      company: latestRegion.company || '',
      min_base_amount: null,
      max_base_amount: null,
      limit_effective_date: ''
    }
    showCreateDialog.value = true
  } catch (error) {
    console.error('获取地区详情失败:', error)
    ElMessage.error('获取地区详情失败')
  }
}

// 删除地区
const deleteRegion = async (region) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除地区"${region.name}"吗？删除后将同时删除该地区下的所有社保类型。`,
      '确认删除',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    await deleteSocialSecurityRegion(region.id)
    ElMessage.success('删除成功')
    loadRegions()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除地区失败:', error)
      ElMessage.error('删除失败')
    }
  }
}

// 提交地区表单
const handleSubmitRegion = async () => {
  if (!regionFormRef.value) return

  try {
    await regionFormRef.value.validate()
    submitting.value = true

    const data = {
      name: regionForm.value.name,
      code: regionForm.value.code || null,
      company: regionForm.value.company || null,
      min_base_amount: regionForm.value.min_base_amount || null,
      max_base_amount: regionForm.value.max_base_amount || null,
      limit_effective_date: regionForm.value.limit_effective_date || null,
      account_set_id: currentAccountSetId.value
    }

    // 调试日志：查看提交的数据
    console.log('提交社保地区数据:', data)

    if (editingRegion.value) {
      await updateSocialSecurityRegion(editingRegion.value.id, data)
      ElMessage.success('更新成功')
    } else {
      await createSocialSecurityRegion(data)
      ElMessage.success('创建成功')
    }

    showCreateDialog.value = false
    resetRegionForm()
    loadRegions()
  } catch (error) {
    console.error('提交地区表单失败:', error)
    ElMessage.error(editingRegion.value ? '更新失败' : '创建失败')
  } finally {
    submitting.value = false
  }
}

// 重置地区表单
const resetRegionForm = () => {
  editingRegion.value = null
  regionForm.value = {
    name: '',
    code: '',
    company: '',
    min_base_amount: null,
    max_base_amount: null,
    limit_effective_date: ''
  }
  if (regionFormRef.value) {
    regionFormRef.value.resetFields()
  }
}

const decimalToPercent = (value) => {
  if (value === null || value === undefined || value === '') return 0
  return Number((Number(value) * 100).toFixed(2))
}

const percentToDecimal = (value) => {
  if (value === null || value === undefined || value === '') return 0
  return Number((Number(value) / 100).toFixed(4))
}

const handleOnlyCompanyPayChange = (value) => {
  if (value) {
    typeForm.value.employee_ratio = 0
  }
}

const openAddTypeDialog = () => {
  resetTypeForm()
  showAddTypeDialog.value = true
}

// 编辑类型
const editType = (type) => {
  editingType.value = type
  typeForm.value = {
    name: type.name,
    employee_ratio: decimalToPercent(type.employee_ratio),
    company_ratio: decimalToPercent(type.company_ratio),
    unit: type.unit || '元',
    only_company_pay: Number(type.employee_ratio) === 0
  }
  showAddTypeDialog.value = true
}

// 删除类型
const deleteType = async (type) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除社保类型"${type.name}"吗？`,
      '确认删除',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    await deleteSocialSecurityType(type.id)
    ElMessage.success('删除成功')
    loadRegions() // 重新加载以更新类型列表
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除类型失败:', error)
      ElMessage.error('删除失败')
    }
  }
}

// 提交类型表单
const handleSubmitType = async () => {
  if (!typeFormRef.value) return

  try {
    await typeFormRef.value.validate()
    submitting.value = true

    const data = {
      name: typeForm.value.name,
      employee_ratio: typeForm.value.only_company_pay ? 0 : percentToDecimal(typeForm.value.employee_ratio),
      company_ratio: percentToDecimal(typeForm.value.company_ratio),
      only_company_pay: typeForm.value.only_company_pay
    }

    if (editingType.value) {
      await updateSocialSecurityType(editingType.value.id, data)
      ElMessage.success('更新成功')
    } else {
      await addSocialSecurityType(currentRegion.value.id, data)
      ElMessage.success('添加成功')
    }

    showAddTypeDialog.value = false
    showTypesDialog.value = false
    resetTypeForm()
    loadRegions() // 重新加载以更新类型列表
  } catch (error) {
    console.error('提交类型表单失败:', error)
    ElMessage.error(editingType.value ? '更新失败' : '添加失败')
  } finally {
    submitting.value = false
  }
}

// 重置类型表单
const resetTypeForm = () => {
  editingType.value = null
  typeForm.value = {
    name: '',
    employee_ratio: 0,
    company_ratio: 0,
    unit: '元',
    only_company_pay: false
  }
  if (typeFormRef.value) {
    typeFormRef.value.resetFields()
  }
}

// 监听账套变化
const unwatchAccountSet = accountSetStore.$subscribe((mutation, state) => {
  if (state.currentAccountSetId) {
    loadRegions()
  }
})

watch(
  () => route.path,
  (path) => {
    activeTab.value = tabRouteMap[path] || 'social'
  },
  { immediate: true }
)

watch(activeTab, (tab) => {
  const targetPath = tab === 'large-medical' ? '/large-medical-insurance' : '/social-security'
  if (route.path !== targetPath) {
    router.replace(targetPath)
  }
})

onMounted(async () => {
  // 先初始化账套信息
  await accountSetStore.loadMyAccountSets()

  if (currentAccountSetId.value) {
    loadRegions()
    loadMedicalRegions()
  }
})

onUnmounted(() => {
  // 清理订阅
  if (unwatchAccountSet) {
    unwatchAccountSet()
  }
})

// ==================== 医保管理功能 ====================

// 加载医保地区
const loadMedicalRegions = async () => {
  if (!currentAccountSetId.value) {
    ElMessage.warning('请先选择账套')
    return
  }

  medicalLoading.value = true
  try {
    const response = await getMedicalInsuranceRegions({
      account_set_id: currentAccountSetId.value
    })
    medicalRegions.value = response.data
  } catch (error) {
    console.error('加载医保地区失败:', error)
    ElMessage.error('加载医保地区失败')
  } finally {
    medicalLoading.value = false
  }
}

const showMedicalRegionHistory = async (region) => {
  regionHistoryTitle.value = `${region.name} - 上下限历史`
  showRegionHistoryDialog.value = true
  regionHistoryLoading.value = true
  try {
    const response = await getMedicalInsuranceRegionLimitHistories(region.id, {
      account_set_id: currentAccountSetId.value
    })
    regionHistories.value = response.data || []
  } catch (error) {
    console.error('加载医保上下限历史失败:', error)
    ElMessage.error('加载历史失败')
    regionHistories.value = []
  } finally {
    regionHistoryLoading.value = false
  }
}

// 编辑医保地区
const editMedicalRegion = async (region) => {
  try {
    // 从服务器获取最新数据
    const response = await getMedicalInsuranceRegion(region.id)
    const latestRegion = response.data
    const firstType = latestRegion.medical_insurance_types?.[0] || null
    
    editingMedicalRegion.value = latestRegion
    medicalRegionForm.value = {
      name: latestRegion.name,
      code: latestRegion.code || '',
      company: latestRegion.company || '',
      min_base_amount: null,
      max_base_amount: null,
      limit_effective_date: '',
      type_id: firstType?.id || null,
      type_name: firstType?.name || '默认配置',
      type_employee_ratio: decimalToPercent(firstType?.employee_ratio || 0),
      type_company_ratio: decimalToPercent(firstType?.company_ratio || 0)
    }
    showCreateMedicalDialog.value = true
  } catch (error) {
    console.error('获取医保地区详情失败:', error)
    ElMessage.error('获取医保地区详情失败')
  }
}

// 删除医保地区
const deleteMedicalRegion = async (region) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除地区"${region.name}"吗？删除后将同时删除该地区下的所有医保类型。`,
      '确认删除',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    await deleteMedicalInsuranceRegion(region.id, {
      account_set_id: currentAccountSetId.value
    })
    ElMessage.success('删除成功')
    loadMedicalRegions()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除医保地区失败:', error)
      ElMessage.error('删除失败')
    }
  }
}

// 提交医保地区表单
const handleSubmitMedicalRegion = async () => {
  if (!medicalRegionFormRef.value) return

  try {
    await medicalRegionFormRef.value.validate()

    if (medicalRegionForm.value.type_employee_ratio === null || medicalRegionForm.value.type_employee_ratio === undefined) {
      ElMessage.warning('请输入员工缴纳比例')
      return
    }
    if (medicalRegionForm.value.type_company_ratio === null || medicalRegionForm.value.type_company_ratio === undefined) {
      ElMessage.warning('请输入公司缴纳比例')
      return
    }

    submitting.value = true

    const data = {
      name: medicalRegionForm.value.name,
      code: medicalRegionForm.value.code || null,
      company: medicalRegionForm.value.company || null,
      min_base_amount: medicalRegionForm.value.min_base_amount || null,
      max_base_amount: medicalRegionForm.value.max_base_amount || null,
      limit_effective_date: medicalRegionForm.value.limit_effective_date || null,
      account_set_id: currentAccountSetId.value
    }

    const typePayload = {
      name: (medicalRegionForm.value.type_name || medicalRegionForm.value.name || '默认配置').trim(),
      employee_ratio: percentToDecimal(medicalRegionForm.value.type_employee_ratio),
      company_ratio: percentToDecimal(medicalRegionForm.value.type_company_ratio),
      account_set_id: currentAccountSetId.value
    }

    if (editingMedicalRegion.value) {
      await updateMedicalInsuranceRegion(editingMedicalRegion.value.id, {
        ...data,
        account_set_id: currentAccountSetId.value
      })

      if (medicalRegionForm.value.type_id) {
        await updateMedicalInsuranceType(medicalRegionForm.value.type_id, typePayload)
      } else {
        await addMedicalInsuranceType(editingMedicalRegion.value.id, typePayload)
      }

      ElMessage.success('更新成功')
    } else {
      data.type_name = typePayload.name
      data.type_employee_ratio = typePayload.employee_ratio
      data.type_company_ratio = typePayload.company_ratio
      await createMedicalInsuranceRegion(data)
      ElMessage.success('创建成功')
    }

    showCreateMedicalDialog.value = false
    resetMedicalRegionForm()
    loadMedicalRegions()
  } catch (error) {
    console.error('提交医保地区表单失败:', error)
    ElMessage.error(editingMedicalRegion.value ? '更新失败' : '创建失败')
  } finally {
    submitting.value = false
  }
}

// 重置医保地区表单
const resetMedicalRegionForm = () => {
  editingMedicalRegion.value = null
  medicalRegionForm.value = {
    name: '',
    code: '',
    company: '',
    min_base_amount: null,
    max_base_amount: null,
    limit_effective_date: '',
    type_id: null,
    type_name: '默认配置',
    type_employee_ratio: 0,
    type_company_ratio: 0
  }
  if (medicalRegionFormRef.value) {
    medicalRegionFormRef.value.resetFields()
  }
}

// 编辑医保类型
const editMedicalType = (type) => {
  editingMedicalType.value = type
  medicalTypeForm.value = {
    name: type.name,
    employee_ratio: decimalToPercent(type.employee_ratio),
    company_ratio: decimalToPercent(type.company_ratio)
  }
  showAddMedicalTypeDialog.value = true
}

// 删除医保类型
const deleteMedicalType = async (type) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除医保类型"${type.name}"吗？`,
      '确认删除',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    await deleteMedicalInsuranceType(type.id, {
      account_set_id: currentAccountSetId.value
    })
    ElMessage.success('删除成功')
    loadMedicalRegions() // 重新加载以更新类型列表
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除医保类型失败:', error)
      ElMessage.error('删除失败')
    }
  }
}

// 提交医保类型表单
const handleSubmitMedicalType = async () => {
  if (!medicalTypeFormRef.value) return

  try {
    await medicalTypeFormRef.value.validate()

    if (!editingMedicalType.value && (currentMedicalRegion.value?.medical_insurance_types?.length || 0) > 0) {
      ElMessage.warning('该地区已存在配置，请直接编辑')
      return
    }

    submitting.value = true

    const data = {
      name: medicalTypeForm.value.name,
      employee_ratio: percentToDecimal(medicalTypeForm.value.employee_ratio),
      company_ratio: percentToDecimal(medicalTypeForm.value.company_ratio),
      account_set_id: currentAccountSetId.value
    }

    if (editingMedicalType.value) {
      await updateMedicalInsuranceType(editingMedicalType.value.id, data)
      ElMessage.success('更新成功')
    } else {
      await addMedicalInsuranceType(currentMedicalRegion.value.id, data)
      ElMessage.success('添加成功')
    }

    showAddMedicalTypeDialog.value = false
    showMedicalTypesDialog.value = false
    resetMedicalTypeForm()
    loadMedicalRegions() // 重新加载以更新类型列表
  } catch (error) {
    console.error('提交医保类型表单失败:', error)
    ElMessage.error(editingMedicalType.value ? '更新失败' : '添加失败')
  } finally {
    submitting.value = false
  }
}

// 重置医保类型表单
const resetMedicalTypeForm = () => {
  editingMedicalType.value = null
  medicalTypeForm.value = {
    name: '',
    min_base_amount: 0,
    max_base_amount: 0,
    employee_ratio: 0,
    company_ratio: 0,
    unit: '元'
  }
  if (medicalTypeFormRef.value) {
    medicalTypeFormRef.value.resetFields()
  }
}

// 医保模板相关函数
const handleMedicalSelectionChange = (selection) => {
  console.log('选中的医保地区:', selection)
  selectedMedicalRegions.value = selection
}

const batchCreateMedicalTemplate = () => {
  if (selectedMedicalRegions.value.length === 0) {
    ElMessage.warning('请先选择要创建模板的地区')
    return
  }
  
  const regionsWithoutTemplate = selectedMedicalRegions.value.filter(r => !r.has_template)
  
  if (regionsWithoutTemplate.length === 0) {
    ElMessage.warning('所选地区都已有模板，请使用编辑模板功能')
    return
  }
  
  currentTemplateRegion.value = regionsWithoutTemplate[0]
  templateForm.value = {
    name: `批量创建-医保缴纳明细`,
    description: `为 ${regionsWithoutTemplate.map(r => r.name).join('、')} 创建模板`
  }
  
  reportTitle.value = ''
  templateColumns.value = []
  selectedColumnIndex.value = null
  reportHeaderFields.value = []
  selectedHeaderFieldIndex.value = null
  reportFooterFields.value = []
  selectedFooterFieldIndex.value = null
  sidebarTab.value = 'header'
  showTemplateDesigner.value = true
  
  isBatchCreateMode.value = true
  batchCreateRegions.value = regionsWithoutTemplate
  isEditMode.value = false
  editingTemplateId.value = null
  currentTemplateType.value = 'medical_insurance'
}

const createMedicalTemplate = (region) => {
  currentTemplateRegion.value = region
  editingTemplate.value = null
  showTemplateDesigner.value = true
  
  isBatchCreateMode.value = false
  batchCreateRegions.value = []
  isEditMode.value = false
  editingTemplateId.value = null
  currentTemplateType.value = 'medical_insurance'
}

const editMedicalTemplate = async (region) => {
  try {
    const response = await request.get('/report-templates', {
      params: {
        region_id: region.id,
        region_type: 'medical_insurance',
        account_set_id: currentAccountSetId.value
      }
    })
    
    if (response.success && response.data && response.data.length > 0) {
      const template = response.data[0]
      
      currentTemplateRegion.value = region
      editingTemplate.value = template
      
      isEditMode.value = true
      editingTemplateId.value = template.id
      isBatchCreateMode.value = false
      batchCreateRegions.value = []
      currentTemplateType.value = 'medical_insurance'
      
      showTemplateDesigner.value = true
    } else {
      ElMessage.warning('未找到该地区的模板')
    }
  } catch (error) {
    console.error('加载模板失败:', error)
    ElMessage.error('加载模板失败')
  }
}

// 显示基数调整对话框
const showAdjustmentDialog = (region) => {
  if (!canAdjustBase.value) {
    ElMessage.warning('当前月份不允许调整基数，请在账套设置中配置允许调整的月份')
    return
  }
  
  currentAdjustmentRegion.value = {
    ...region,
    current_base: region.social_security_types?.[0]?.base_amount || 0
  }
  adjustmentForm.value = {
    adjustment_base: null,
    effective_date: null
  }
  showAdjustmentDialogFlag.value = true
}

// 提交基数调整
const submitAdjustment = async () => {
  if (!adjustmentFormRef.value) return

  try {
    await adjustmentFormRef.value.validate()
    submitting.value = true

    const data = {
      adjustment_base: adjustmentForm.value.adjustment_base,
      effective_date: adjustmentForm.value.effective_date,
      account_set_id: currentAccountSetId.value
    }

    await updateSocialSecurityRegion(currentAdjustmentRegion.value.id, data)
    ElMessage.success('基数调整设置成功')

    showAdjustmentDialogFlag.value = false
    loadRegions()
  } catch (error) {
    console.error('提交基数调整失败:', error)
    ElMessage.error('基数调整设置失败')
  } finally {
    submitting.value = false
  }
}

// 取消基数调整
const cancelAdjustment = async (region) => {
  try {
    await ElMessageBox.confirm('确定要取消基数调整吗？', '确认取消', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    const data = {
      adjustment_base: null,
      effective_date: null,
      account_set_id: currentAccountSetId.value
    }

    await updateSocialSecurityRegion(region.id, data)
    ElMessage.success('基数调整已取消')
    loadRegions()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('取消基数调整失败:', error)
      ElMessage.error('取消基数调整失败')
    }
  }
}

// 格式化日期
const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('zh-CN')
}

// ==================== 模板管理功能 ====================

// 处理表格多选
const handleSelectionChange = (selection) => {
  console.log('选中的地区:', selection)
  selectedRegions.value = selection
}

// 批量创建模板
const batchCreateTemplate = () => {
  if (selectedRegions.value.length === 0) {
    ElMessage.warning('请先选择要创建模板的地区')
    return
  }
  
  // 过滤出还没有模板的地区
  const regionsWithoutTemplate = selectedRegions.value.filter(r => !r.has_template)
  
  if (regionsWithoutTemplate.length === 0) {
    ElMessage.warning('所选地区都已有模板，请使用编辑模板功能')
    return
  }
  
  // 使用第一个地区打开模板设计器
  currentTemplateRegion.value = regionsWithoutTemplate[0]
  templateForm.value = {
    name: `批量创建-社保缴纳明细`,
    description: `为 ${regionsWithoutTemplate.map(r => r.name).join('、')} 创建模板`
  }
  
  // 重置配置
  reportTitle.value = ''
  templateColumns.value = []
  selectedColumnIndex.value = null
  reportHeaderFields.value = []
  selectedHeaderFieldIndex.value = null
  reportFooterFields.value = []
  selectedFooterFieldIndex.value = null
  sidebarTab.value = 'header'
  showTemplateDesigner.value = true
  
  // 标记为批量创建模式
  isBatchCreateMode.value = true
  batchCreateRegions.value = regionsWithoutTemplate
  currentTemplateType.value = 'social_security'
}

// 打开模板设计器
const createTemplate = (region) => {
  currentTemplateRegion.value = region
  templateForm.value = {
    name: `${region.name}-社保缴纳明细`,
    description: ''
  }
  // 重置配置
  editingTemplate.value = null
  showTemplateDesigner.value = true
  
  // 标记为单个创建模式
  isBatchCreateMode.value = false
  batchCreateRegions.value = []
  isEditMode.value = false
  editingTemplateId.value = null
  currentTemplateType.value = 'social_security'
}

// 编辑模板
const editTemplate = async (region) => {
  try {
    // 加载该地区的模板数据
    const response = await request.get('/report-templates', {
      params: {
        region_id: region.id,
        region_type: 'social_security',
        account_set_id: currentAccountSetId.value
      }
    })
    
    if (response.success && response.data && response.data.length > 0) {
      const template = response.data[0] // 取第一个模板
      
      // 设置当前地区
      currentTemplateRegion.value = region
      
      // 设置编辑的模板数据
      editingTemplate.value = template
      
      // 标记为编辑模式
      isEditMode.value = true
      editingTemplateId.value = template.id
      isBatchCreateMode.value = false
      batchCreateRegions.value = []
      currentTemplateType.value = 'social_security'
      
      // 打开设计器
      showTemplateDesigner.value = true
    } else {
      ElMessage.warning('未找到该地区的模板')
    }
  } catch (error) {
    console.error('加载模板失败:', error)
    ElMessage.error('加载模板失败')
  }
}

// 打开复制模板对话框
const openCopyTemplateDialog = async (region, type) => {
  try {
    // 先获取该地区的模板ID
    const response = await request.get('/report-templates', {
      params: {
        region_id: region.id,
        region_type: type,
        account_set_id: currentAccountSetId.value
      }
    })
    
    if (response.success && response.data && response.data.length > 0) {
      copyTemplateSource.value = { ...region, templateId: response.data[0].id }
      copyTemplateType.value = type
      copyTargetRegionIds.value = []
      showCopyTemplateDialog.value = true
    } else {
      ElMessage.warning('未找到该地区的模板')
    }
  } catch (error) {
    console.error('获取模板失败:', error)
    ElMessage.error('获取模板失败')
  }
}

// 执行复制模板
const handleCopyTemplate = async () => {
  if (copyTargetRegionIds.value.length === 0) {
    ElMessage.warning('请选择目标地区')
    return
  }
  
  copyingTemplate.value = true
  try {
    const response = await request.post(`/report-templates/${copyTemplateSource.value.templateId}/copy-to-regions`, {
      target_region_ids: copyTargetRegionIds.value
    })
    
    if (response.success) {
      ElMessage.success(response.message || '模板复制成功')
      showCopyTemplateDialog.value = false
      
      // 刷新地区列表以更新模板状态
      if (copyTemplateType.value === 'social_security') {
        await loadRegions()
      } else if (copyTemplateType.value === 'medical_insurance') {
        await loadMedicalRegions()
      }
    } else {
      ElMessage.error(response.message || '复制失败')
    }
  } catch (error) {
    console.error('复制模板失败:', error)
    ElMessage.error('复制模板失败')
  } finally {
    copyingTemplate.value = false
  }
}

// 编辑报表标题
const editReportTitle = () => {
  showEditTitleDialog.value = true
}

// 确认添加表头字段
const confirmAddHeaderField = () => {
  if (!newHeaderField.value.label) {
    ElMessage.warning('请输入字段标签')
    return
  }

  if (newHeaderField.value.type === 'system' && !newHeaderField.value.systemField) {
    ElMessage.warning('请选择系统字段')
    return
  }

  if (newHeaderField.value.type === 'text' && !newHeaderField.value.value) {
    ElMessage.warning('请输入字段值')
    return
  }

  const field = {
    label: newHeaderField.value.label,
    type: newHeaderField.value.type,
    systemField: newHeaderField.value.systemField,
    value: newHeaderField.value.value,
    dateFormat: newHeaderField.value.dateFormat,
    row: newHeaderField.value.row || 1
  }

  reportHeaderFields.value.push(field)
  selectedHeaderFieldIndex.value = reportHeaderFields.value.length - 1
  selectedColumnIndex.value = null
  
  // 重置表单
  newHeaderField.value = {
    label: '',
    type: 'system',
    systemField: '',
    value: '',
    dateFormat: 'YYYY-MM-DD',
    row: 1
  }
  
  showAddHeaderFieldDialog.value = false
  ElMessage.success('添加成功')
}

// 获取字段值预览
const getFieldValuePreview = (field) => {
  if (field.type === 'system') {
    const systemFieldLabels = {
      company_name: '{公司名称}',
      region_name: '{当前地区}',
      account_set_name: '{账套名称}',
      current_year: '{当前年份}',
      current_month: '{当前月份}'
    }
    return systemFieldLabels[field.systemField] || '{系统字段}'
  } else if (field.type === 'date') {
    return `{${field.dateFormat}}`
  } else {
    return field.value || '{文本}'
  }
}

// 选择表头字段
const selectHeaderField = (index) => {
  selectedHeaderFieldIndex.value = index
  selectedColumnIndex.value = null
}

// 删除表头字段
const removeHeaderField = (index) => {
  reportHeaderFields.value.splice(index, 1)
  selectedHeaderFieldIndex.value = null
}

// 确认添加表尾字段
const confirmAddFooterField = () => {
  if (!newFooterField.value.label) {
    ElMessage.warning('请输入字段标签')
    return
  }

  if (newFooterField.value.type === 'system' && !newFooterField.value.systemField) {
    ElMessage.warning('请选择系统字段')
    return
  }

  const field = {
    label: newFooterField.value.label,
    type: newFooterField.value.type,
    systemField: newFooterField.value.systemField,
    value: newFooterField.value.value,
    dateFormat: newFooterField.value.dateFormat,
    row: newFooterField.value.row || 1
  }

  reportFooterFields.value.push(field)
  selectedFooterFieldIndex.value = reportFooterFields.value.length - 1
  selectedColumnIndex.value = null
  selectedHeaderFieldIndex.value = null
  
  // 重置表单
  newFooterField.value = {
    label: '',
    type: 'text',
    systemField: '',
    value: '',
    dateFormat: 'YYYY-MM-DD',
    row: 1
  }
  
  showAddFooterFieldDialog.value = false
  ElMessage.success('添加成功')
}

// 选择表尾字段
const selectFooterField = (index) => {
  selectedFooterFieldIndex.value = index
  selectedColumnIndex.value = null
  selectedHeaderFieldIndex.value = null
}

// 删除表尾字段
const removeFooterField = (index) => {
  reportFooterFields.value.splice(index, 1)
  selectedFooterFieldIndex.value = null
}

// 拖拽开始
const handleDragStart = (field) => {
  draggedField.value = field
}

// 拖拽放置到表格区域（添加普通列）
const handleDrop = (e) => {
  e.preventDefault()
  if (draggedField.value) {
    addColumnWithField(draggedField.value)
    draggedField.value = null
  }
}

// 拖拽放置到父列（添加子列）
const handleDropToParent = (e, parentIndex) => {
  e.preventDefault()
  e.stopPropagation()
  
  if (!draggedField.value) return
  
  const parentColumn = templateColumns.value[parentIndex]
  if (!parentColumn || !parentColumn.isParent) return
  
  // 确保有 children 数组
  if (!parentColumn.children) {
    parentColumn.children = []
  }
  
  // 添加子列
  const newChild = {
    field: draggedField.value.key,
    title: draggedField.value.label,
    width: 120,
    align: draggedField.value.key.includes('amount') || draggedField.value.key.includes('base') || draggedField.value.key.includes('company') || draggedField.value.key.includes('employee') || draggedField.value.key.includes('total') ? 'right' : 'left',
    format: draggedField.value.key.includes('amount') || draggedField.value.key.includes('base') || draggedField.value.key.includes('company') || draggedField.value.key.includes('employee') || draggedField.value.key.includes('total') ? 'currency' : 'text'
  }
  
  parentColumn.children.push(newChild)
  draggedField.value = null
  ElMessage.success('子列添加成功')
}

// 添加父列
const addParentColumn = () => {
  const newColumn = {
    title: '父列标题',
    isParent: true,
    children: [],
    width: undefined,
    align: 'center'
  }
  templateColumns.value.push(newColumn)
  selectedColumnIndex.value = templateColumns.value.length - 1
  ElMessage.success('父列添加成功，可以拖拽字段到父列中')
}

// 添加普通列
const addColumn = () => {
  const newColumn = {
    field: 'name',
    title: '新列',
    width: 120,
    align: 'left',
    format: 'text'
  }
  templateColumns.value.push(newColumn)
  selectedColumnIndex.value = templateColumns.value.length - 1
}

// 使用字段添加列
const addColumnWithField = (field) => {
  // 判断对齐方式
  let align = 'left'
  if (field.key.includes('amount') || field.key.includes('base') || field.key.includes('company') || field.key.includes('employee') || field.key.includes('total') || field.key.includes('ratio') || field.key === 'serial_number') {
    align = 'center'
  }
  if (field.key.includes('amount') || field.key.includes('base') || field.key.includes('company') || field.key.includes('employee') || field.key.includes('total')) {
    align = 'right'
  }
  
  // 判断格式
  let format = 'text'
  if (field.key.includes('amount') || field.key.includes('base') || field.key.includes('company') || field.key.includes('employee') || field.key.includes('total') || field.key.includes('fund')) {
    format = 'currency'
  } else if (field.key.includes('ratio')) {
    format = 'percent'
  } else if (field.key.includes('date')) {
    format = 'date'
  } else if (field.key === 'serial_number') {
    format = 'number'
  }
  
  const newColumn = {
    field: field.key,
    title: field.label,
    width: field.key === 'serial_number' ? 80 : 120,
    align: align,
    format: format,
    isSerial: field.isSerial || false
  }
  templateColumns.value.push(newColumn)
  selectedColumnIndex.value = templateColumns.value.length - 1
}

// 删除列
const removeColumn = () => {
  if (selectedColumnIndex.value !== null) {
    templateColumns.value.splice(selectedColumnIndex.value, 1)
    selectedColumnIndex.value = null
  }
}

// 获取总列数（包括子列）
const getTotalColumnCount = () => {
  let count = 0
  templateColumns.value.forEach(col => {
    if (col.children && col.children.length > 0) {
      count += col.children.length
    } else if (!col.isParent) {
      count += 1
    }
  })
  return count
}

// 左移列
const moveColumnLeft = () => {
  if (canMoveLeft.value) {
    const index = selectedColumnIndex.value
    const temp = templateColumns.value[index]
    templateColumns.value[index] = templateColumns.value[index - 1]
    templateColumns.value[index - 1] = temp
    selectedColumnIndex.value = index - 1
  }
}

// 右移列
const moveColumnRight = () => {
  if (canMoveRight.value) {
    const index = selectedColumnIndex.value
    const temp = templateColumns.value[index]
    templateColumns.value[index] = templateColumns.value[index + 1]
    templateColumns.value[index + 1] = temp
    selectedColumnIndex.value = index + 1
  }
}

// 选择列
const selectColumn = (index) => {
  selectedColumnIndex.value = index
  selectedHeaderFieldIndex.value = null
  selectedFooterFieldIndex.value = null
}

// 选择子列
const selectChildColumn = (parentIndex, childIndex) => {
  // 暂时选择父列，后续可以扩展为选择子列
  selectedColumnIndex.value = parentIndex
  selectedHeaderFieldIndex.value = null
  selectedFooterFieldIndex.value = null
}

// 格式化单元格值
const formatCellValue = (value, format) => {
  if (value === null || value === undefined) return ''
  
  switch (format) {
    case 'currency':
      return `¥${Number(value).toFixed(2)}`
    case 'number':
      return Number(value).toLocaleString()
    case 'percent':
      return `${(Number(value) * 100).toFixed(2)}%`
    case 'date':
      return value ? new Date(value).toLocaleDateString('zh-CN') : ''
    default:
      return value
  }
}

// 检查是否有任何列需要显示合计
const hasAnyTotal = () => {
  return templateColumns.value.some(col => {
    if (col.children && col.children.length > 0) {
      return col.children.some(child => child.showTotal)
    }
    return col.showTotal
  })
}

// 计算合计行数据
const getTotalRow = () => {
  const totalRow = {}
  
  templateColumns.value.forEach(col => {
    if (col.children && col.children.length > 0) {
      // 处理父列的子列
      col.children.forEach(child => {
        const key = `${col.title}_${child.title}`
        if (child.showTotal) {
          const sum = previewData.value.reduce((acc, row) => {
            return acc + (Number(row[child.field]) || 0)
          }, 0)
          totalRow[key] = formatCellValue(sum, child.format)
        } else {
          totalRow[key] = ''
        }
      })
    } else if (!col.isParent) {
      // 处理普通列
      if (col.showTotal) {
        if (col.field === 'serial_number' || col.isSerial) {
          totalRow[col.field] = '合计'
        } else {
          const sum = previewData.value.reduce((acc, row) => {
            return acc + (Number(row[col.field]) || 0)
          }, 0)
          totalRow[col.field] = formatCellValue(sum, col.format)
        }
      } else {
        // 如果是第一列且没有其他列显示合计，显示"合计"文字
        const isFirstColumn = templateColumns.value.indexOf(col) === 0
        const hasOtherTotals = templateColumns.value.some(c => c.showTotal || (c.children && c.children.some(ch => ch.showTotal)))
        totalRow[col.field] = (isFirstColumn && hasOtherTotals) ? '合计' : ''
      }
    } else {
      totalRow[col.field] = ''
    }
  })
  
  return totalRow
}

// 模板设计器保存回调
const handleTemplateSaved = (result) => {
  if (result.success) {
    // 根据类型重新加载对应的地区列表
    if (currentTemplateType.value === 'social_security') {
      loadRegions()
    } else if (currentTemplateType.value === 'medical_insurance') {
      loadMedicalRegions()
    }
    // 重置状态
    isBatchCreateMode.value = false
    batchCreateRegions.value = []
    selectedRegions.value = []
    selectedMedicalRegions.value = []
    editingTemplate.value = null
    isEditMode.value = false
    editingTemplateId.value = null
  }
}

// 模板设计器关闭回调
const handleTemplateDesignerClose = () => {
  isBatchCreateMode.value = false
  batchCreateRegions.value = []
  editingTemplate.value = null
  isEditMode.value = false
  editingTemplateId.value = null
}

// 保存模板
const saveTemplate = async () => {
  if (!templateForm.value.name) {
    ElMessage.warning('请输入模板名称')
    return
  }

  if (!reportTitle.value) {
    ElMessage.warning('请设置报表标题')
    return
  }
  
  if (templateColumns.value.length === 0) {
    ElMessage.warning('请至少添加一列')
    return
  }
  
  try {
    submitting.value = true
    
    // 如果是批量创建模式
    if (isBatchCreateMode.value && batchCreateRegions.value.length > 0) {
      let successCount = 0
      let failCount = 0
      
      for (const region of batchCreateRegions.value) {
        try {
          // 构建模板数据
          const templateData = {
            name: `${region.name}-${templateForm.value.name}`,
            description: templateForm.value.description,
            report_title: reportTitle.value,
            region_id: region.id,
            region_type: currentTemplateType.value,
            fields: templateColumns.value.map((col, index) => ({
              key: col.field,
              label: col.title,
              order: index,
              width: col.width,
              align: col.align,
              format: col.format
            })),
            account_set_id: currentAccountSetId.value,
            header_fields: reportHeaderFields.value.map((field, index) => ({
              label: field.label,
              type: field.type,
              system_field: field.systemField,
              value: field.value,
              date_format: field.dateFormat,
              row: field.row || 1,
              order: index
            })),
            footer_fields: reportFooterFields.value.map((field, index) => ({
              label: field.label,
              type: field.type,
              system_field: field.systemField,
              value: field.value,
              date_format: field.dateFormat,
              row: field.row || 1,
              order: index
            }))
          }
          
          // 调用 API 保存模板
          const response = await request.post('/report-templates', templateData)
          
          if (response.success) {
            successCount++
          } else {
            failCount++
          }
        } catch (error) {
          console.error(`为地区 ${region.name} 创建模板失败:`, error)
          failCount++
        }
      }
      
      if (successCount > 0) {
        ElMessage.success(`成功为 ${successCount} 个地区创建模板${failCount > 0 ? `，${failCount} 个失败` : ''}`)
        showTemplateDesigner.value = false
        // 重置
        reportTitle.value = ''
        templateColumns.value = []
        selectedColumnIndex.value = null
        reportHeaderFields.value = []
        selectedHeaderFieldIndex.value = null
        reportFooterFields.value = []
        selectedFooterFieldIndex.value = null
        isBatchCreateMode.value = false
        batchCreateRegions.value = []
        selectedRegions.value = []
        selectedMedicalRegions.value = []
        // 根据类型重新加载对应的地区列表
        if (currentTemplateType.value === 'social_security') {
          loadRegions()
        } else if (currentTemplateType.value === 'medical_insurance') {
          loadMedicalRegions()
        }
      } else {
        ElMessage.error('批量创建模板失败')
      }
    } else {
      // 单个创建/编辑模式
      const templateData = {
        name: templateForm.value.name,
        description: templateForm.value.description,
        report_title: reportTitle.value,
        region_id: currentTemplateRegion.value.id,
        region_type: currentTemplateType.value,
        fields: templateColumns.value.map((col, index) => ({
          key: col.field,
          label: col.title,
          order: index,
          width: col.width,
          align: col.align,
          format: col.format
        })),
        account_set_id: currentAccountSetId.value,
        header_fields: reportHeaderFields.value.map((field, index) => ({
          label: field.label,
          type: field.type,
          system_field: field.systemField,
          value: field.value,
          date_format: field.dateFormat,
          row: field.row || 1,
          order: index
        })),
        footer_fields: reportFooterFields.value.map((field, index) => ({
          label: field.label,
          type: field.type,
          system_field: field.systemField,
          value: field.value,
          date_format: field.dateFormat,
          row: field.row || 1,
          order: index
        }))
      }
      
      // 调用 API 保存或更新模板
      let response
      if (isEditMode.value && editingTemplateId.value) {
        // 更新模板
        response = await request.put(`/report-templates/${editingTemplateId.value}`, templateData)
      } else {
        // 创建模板
        response = await request.post('/report-templates', templateData)
      }
      
      if (response.success) {
        ElMessage.success(isEditMode.value ? '模板更新成功' : '模板创建成功')
        showTemplateDesigner.value = false
        // 重置
        reportTitle.value = ''
        templateColumns.value = []
        selectedColumnIndex.value = null
        reportHeaderFields.value = []
        selectedHeaderFieldIndex.value = null
        reportFooterFields.value = []
        selectedFooterFieldIndex.value = null
        isEditMode.value = false
        editingTemplateId.value = null
        // 根据类型重新加载对应的地区列表
        if (currentTemplateType.value === 'social_security') {
          loadRegions()
        } else if (currentTemplateType.value === 'medical_insurance') {
          loadMedicalRegions()
        }
      } else {
        throw new Error(response.message || '保存失败')
      }
    }
  } catch (error) {
    console.error('保存模板失败:', error)
    ElMessage.error('保存模板失败')
  } finally {
    submitting.value = false
  }
}

</script>

<style scoped>
.social-security-container {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  color: #303133;
}

.tab-header {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
}

.region-list-card {
  margin-bottom: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.types-header {
  margin-bottom: 20px;
}

.types-table {
  margin-top: 20px;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
}

.tabs-container {
  margin-top: 20px;
}

.tab-header {
  margin-bottom: 20px;
  display: flex;
  justify-content: flex-end;
}

.adjustment-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.effective-date {
  font-size: 12px;
  color: #909399;
}

/* 模板设计器样式 */
.template-designer {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 200px);
}

.designer-header {
  padding: 16px;
  background: #f5f7fa;
  border-bottom: 1px solid #e4e7ed;
}

.designer-body {
  display: flex;
  flex: 1;
  overflow: hidden;
}

.designer-sidebar {
  width: 220px;
  border-right: 1px solid #e4e7ed;
  background: #fafafa;
  display: flex;
  flex-direction: column;
}

.sidebar-header {
  padding: 16px;
  border-bottom: 1px solid #e4e7ed;
}

.sidebar-header h4 {
  margin: 0 0 8px 0;
  font-size: 14px;
  color: #303133;
}

.field-item {
  padding: 10px 16px;
  margin: 4px 8px;
  background: white;
  border: 1px solid #dcdfe6;
  border-radius: 4px;
  cursor: move;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s;
}

.field-item:hover {
  background: #ecf5ff;
  border-color: #409eff;
  transform: translateX(4px);
}

.field-item .el-icon {
  color: #909399;
}

/* 表头字段列表样式 */
.header-fields-list {
  padding: 8px;
}

.header-field-preview {
  padding: 10px 12px;
  margin-bottom: 8px;
  background: white;
  border: 1px solid #dcdfe6;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
}

.header-field-preview:hover {
  border-color: #409eff;
  background: #ecf5ff;
}

.header-field-preview.selected {
  border-color: #409eff;
  background: #ecf5ff;
}

.field-preview-label {
  font-weight: 500;
  color: #303133;
  font-size: 13px;
  margin-bottom: 4px;
}

.field-preview-value {
  color: #909399;
  font-size: 12px;
}

.designer-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  padding: 16px;
  overflow: hidden;
}

.designer-toolbar {
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.table-preview {
  flex: 1;
  overflow: auto;
  border: 2px dashed #dcdfe6;
  border-radius: 4px;
  padding: 16px;
  background: white;
  position: relative;
}

.table-total-row {
  margin-top: -1px;
}

.table-total-row .el-table {
  border-top: 2px solid #409eff;
}

.table-total-row .total-cell {
  font-weight: bold;
  color: #303133;
}

.empty-table {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
}

/* 报表预览样式 */
.report-preview {
  flex: 1;
  overflow: auto;
  border: 2px solid #e4e7ed;
  border-radius: 4px;
  background: white;
  padding: 20px;
}

.report-header {
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 2px solid #303133;
}

.header-title {
  text-align: center;
  margin-bottom: 16px;
  cursor: pointer;
  padding: 8px;
  border-radius: 4px;
  transition: all 0.2s;
  position: relative;
  display: inline-block;
  width: 100%;
}

.header-title:hover {
  background: #f5f7fa;
}

.header-title h3 {
  margin: 0;
  font-size: 20px;
  font-weight: bold;
  color: #303133;
  display: inline-block;
}

.header-title .edit-icon {
  margin-left: 8px;
  color: #909399;
  font-size: 16px;
  vertical-align: middle;
}

.header-fields {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  background: #f5f7fa;
  border-radius: 4px;
  min-height: 60px;
}

.header-field-row {
  display: flex;
  gap: 12px;
  align-items: center;
}

.header-field-item {
  display: inline-flex;
  align-items: center;
  padding: 6px 12px;
  background: white;
  border: 1px solid #dcdfe6;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
}

.header-field-item:hover {
  border-color: #409eff;
  background: #ecf5ff;
}

.header-field-item.selected {
  border-color: #409eff;
  background: #ecf5ff;
}

.header-field-item .field-label {
  font-weight: 500;
  color: #606266;
  margin-right: 4px;
}

.header-field-item .field-value {
  color: #909399;
}

.header-field-item .remove-icon {
  margin-left: 8px;
  color: #f56c6c;
  cursor: pointer;
}

.header-field-item .remove-icon:hover {
  color: #f56c6c;
  transform: scale(1.2);
}

.report-footer {
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid #e4e7ed;
}

.footer-fields {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  background: #f5f7fa;
  border-radius: 4px;
  min-height: 50px;
}

.footer-field-row {
  display: flex;
  gap: 12px;
  align-items: center;
}

.footer-field-item {
  display: inline-flex;
  align-items: center;
  padding: 6px 12px;
  background: white;
  border: 1px solid #dcdfe6;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
  font-size: 14px;
  color: #606266;
}

.footer-field-item:hover {
  border-color: #409eff;
  background: #ecf5ff;
}

.footer-field-item.selected {
  border-color: #409eff;
  background: #ecf5ff;
}

.footer-field-item .field-label {
  font-weight: 500;
  color: #606266;
  margin-right: 4px;
}

.footer-field-item .field-value {
  color: #909399;
}

.footer-field-item .remove-icon {
  margin-left: 8px;
  color: #f56c6c;
  cursor: pointer;
}

.footer-field-item .remove-icon:hover {
  color: #f56c6c;
  transform: scale(1.2);
}

.column-header {
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  transition: all 0.2s;
}

.column-header:hover {
  background: #ecf5ff;
}

.column-header.selected {
  background: #409eff;
  color: white;
}

.column-header.parent-column {
  background: #f0f9ff;
  border: 2px dashed #409eff;
  padding: 8px;
  min-height: 50px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.column-header.parent-column.drop-zone {
  background: #e6f7ff;
}

.column-header.parent-column:hover {
  background: #d9f0ff;
}

.column-header.child-column {
  background: #fafafa;
}

.designer-properties {
  width: 280px;
  border-left: 1px solid #e4e7ed;
  background: #fafafa;
  display: flex;
  flex-direction: column;
}

.properties-header {
  padding: 16px;
  border-bottom: 1px solid #e4e7ed;
}

.properties-header h4 {
  margin: 0;
  font-size: 14px;
  color: #303133;
}

.designer-properties .el-form {
  padding: 16px;
}

.designer-properties .el-form-item {
  margin-bottom: 18px;
}

:deep(.selected-column) {
  background: #ecf5ff !important;
}
</style>

