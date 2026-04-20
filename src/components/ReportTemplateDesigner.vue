<template>
  <el-dialog 
    v-model="dialogVisible" 
    title="设计报表模板" 
    width="95%"
    top="5vh"
    :close-on-click-modal="false"
    @close="handleClose"
  >
    <div class="template-designer">
      <!-- 顶部：模板基本信息 -->
      <div class="designer-header">
        <el-form :model="templateForm" inline size="small">
          <el-form-item label="模板名称">
            <el-input 
              v-model="templateForm.name" 
              placeholder="例如：缴纳明细表" 
              style="width: 300px"
            />
          </el-form-item>
          <el-form-item label="模板说明">
            <el-input 
              v-model="templateForm.description" 
              placeholder="简要说明模板用途"
              style="width: 300px"
            />
          </el-form-item>
        </el-form>
      </div>

      <div class="designer-body">
        <!-- 左侧：组件和字段列表 -->
        <div class="designer-sidebar">
          <el-tabs v-model="sidebarTab" type="border-card">
            <!-- 表头字段 -->
            <el-tab-pane label="表头字段" name="header">
              <div style="padding: 12px;">
                <el-button 
                  type="primary" 
                  size="small" 
                  @click="showAddHeaderFieldDialog = true"
                  style="width: 100%"
                >
                  <el-icon><Plus /></el-icon>
                  添加表头字段
                </el-button>
              </div>
              <el-divider style="margin: 0" />
              <el-scrollbar height="calc(100vh - 420px)">
                <div class="header-fields-list">
                  <div 
                    v-for="(field, index) in reportHeaderFields" 
                    :key="index"
                    class="header-field-preview"
                    @click="selectHeaderField(index)"
                    :class="{ 'selected': selectedHeaderFieldIndex === index }"
                  >
                    <div class="field-preview-label">{{ field.label }}：</div>
                    <div class="field-preview-value">{{ getFieldValuePreview(field) }}</div>
                  </div>
                  <el-empty 
                    v-if="reportHeaderFields.length === 0" 
                    description="暂无表头字段" 
                    :image-size="60"
                  />
                </div>
              </el-scrollbar>
            </el-tab-pane>
            
            <!-- 表格字段 -->
            <el-tab-pane label="表格字段" name="table">
              <el-scrollbar height="calc(100vh - 350px)">
                <div 
                  v-for="field in availableFields" 
                  :key="field.key"
                  class="field-item"
                  draggable="true"
                  @dragstart="handleDragStart(field)"
                >
                  <el-icon><Grid /></el-icon>
                  <span>{{ field.label }}</span>
                </div>
              </el-scrollbar>
            </el-tab-pane>

            <!-- 表尾字段 -->
            <el-tab-pane label="表尾字段" name="footer">
              <div style="padding: 12px;">
                <el-button 
                  type="primary" 
                  size="small" 
                  @click="showAddFooterFieldDialog = true"
                  style="width: 100%"
                >
                  <el-icon><Plus /></el-icon>
                  添加表尾字段
                </el-button>
              </div>
              <el-divider style="margin: 0" />
              <el-scrollbar height="calc(100vh - 420px)">
                <div class="header-fields-list">
                  <div 
                    v-for="(field, index) in reportFooterFields" 
                    :key="index"
                    class="header-field-preview"
                    @click="selectFooterField(index)"
                    :class="{ 'selected': selectedFooterFieldIndex === index }"
                  >
                    <div class="field-preview-label">{{ field.label }}：</div>
                    <div class="field-preview-value">{{ getFieldValuePreview(field) }}</div>
                  </div>
                  <el-empty 
                    v-if="reportFooterFields.length === 0" 
                    description="暂无表尾字段" 
                    :image-size="60"
                  />
                </div>
              </el-scrollbar>
            </el-tab-pane>
          </el-tabs>
        </div>

        <!-- 中间：报表预览区 -->
        <div class="designer-main">
          <div class="designer-toolbar">
            <el-button-group size="small">
              <el-button @click="addParentColumn" :icon="Plus">添加父列</el-button>
              <el-button @click="addColumn" :icon="Plus">添加普通列</el-button>
              <el-button @click="handleDeleteSelected" :icon="Delete" :disabled="!canDelete" type="danger">删除</el-button>
              <el-button @click="moveColumnLeft" :icon="Back" :disabled="!canMoveLeft">左移</el-button>
              <el-button @click="moveColumnRight" :icon="Right" :disabled="!canMoveRight">右移</el-button>
            </el-button-group>
            <el-button-group size="small" style="margin-left: 10px">
              <el-button @click="undoAction" :icon="RefreshLeft" :disabled="historyStack.length === 0">撤回</el-button>
              <el-button @click="resetDesigner" :icon="Refresh">重置</el-button>
            </el-button-group>
            <el-text size="small" type="info" style="margin-left: 10px">
              已添加 {{ getTotalColumnCount() }} 列
            </el-text>
          </div>

          <!-- 报表预览 -->
          <div class="report-preview">
            <!-- 表头区域 -->
            <div class="report-header">
              <div class="header-title" @click="editReportTitle">
                <h3>{{ reportTitle || '点击设置报表标题' }}</h3>
                <el-icon class="edit-icon"><Edit /></el-icon>
              </div>
              <div class="header-fields">
                <div 
                  v-for="row in Object.keys(groupedHeaderFields).sort((a, b) => a - b)" 
                  :key="row"
                  class="header-field-row"
                >
                  <div 
                    v-for="field in groupedHeaderFields[row]" 
                    :key="field.index"
                    class="header-field-item"
                    @click="selectHeaderField(field.index)"
                    :class="{ 'selected': selectedHeaderFieldIndex === field.index }"
                  >
                    <span class="field-label">{{ field.label }}：</span>
                    <span class="field-value">{{ getFieldValuePreview(field) }}</span>
                    <el-icon class="remove-icon" @click.stop="removeHeaderField(field.index)">
                      <Close />
                    </el-icon>
                  </div>
                </div>
                <el-button 
                  size="small" 
                  text 
                  @click="showAddHeaderFieldDialog = true"
                  v-if="reportHeaderFields.length === 0"
                >
                  + 点击添加表头字段
                </el-button>
              </div>
            </div>

            <!-- 表格区域 -->
            <div 
              class="table-preview"
              @drop="handleDrop"
              @dragover.prevent
            >
              <el-table
                :data="previewData"
                border
                style="width: 100%"
                :height="400"
              >
                <el-table-column
                  v-for="(col, index) in templateColumns"
                  :key="index"
                  :label="col.title"
                  :width="col.children && col.children.length > 0 ? undefined : col.width"
                  :align="col.align"
                  :class-name="selectedColumnIndex === index ? 'selected-column' : ''"
                >
                  <!-- 父列表头（支持拖拽） -->
                  <template #header v-if="col.isParent">
                    <div 
                      class="column-header parent-column"
                      :class="{ 'selected': selectedColumnIndex === index, 'drop-zone': col.children && col.children.length === 0 }"
                      @click.stop="selectColumn(index)"
                      @drop="handleDropToParent($event, index)"
                      @dragover.prevent
                    >
                      {{ col.title }}
                      <el-text v-if="col.children && col.children.length === 0" size="small" type="info" style="display: block; margin-top: 4px;">
                        拖拽字段到这里
                      </el-text>
                    </div>
                  </template>
                  
                  <!-- 普通列表头 -->
                  <template #header v-else-if="!col.children || col.children.length === 0">
                    <div 
                      class="column-header"
                      :class="{ 'selected': selectedColumnIndex === index }"
                      @click.stop="selectColumn(index)"
                    >
                      {{ col.title }}
                    </div>
                  </template>
                  
                  <!-- 如果有子列，渲染子列 -->
                  <el-table-column
                    v-if="col.children && col.children.length > 0"
                    v-for="(child, childIndex) in col.children"
                    :key="childIndex"
                    :prop="child.field"
                    :label="child.title"
                    :width="child.width"
                    :align="child.align"
                  >
                    <template #header>
                      <div 
                        class="column-header child-column"
                        :class="{ 'selected': selectedColumnIndex === index && selectedChildColumnIndex === childIndex }"
                        @click.stop="selectChildColumn(index, childIndex)"
                      >
                        {{ child.title }}
                      </div>
                    </template>
                    <template #default="{ row }">
                      {{ formatCellValue(row[child.field], child.format) }}
                    </template>
                  </el-table-column>
                  
                  <!-- 如果没有子列，渲染数据 -->
                  <template #default="{ row }" v-if="!col.isParent && (!col.children || col.children.length === 0)">
                    {{ formatCellValue(row[col.field], col.format) }}
                  </template>
                </el-table-column>
              </el-table>
              
              <!-- 合计行 -->
              <div v-if="hasAnyTotal()" class="table-total-row">
                <table class="total-table" border="1">
                  <tr>
                    <template v-for="(col, index) in totalRowColumns" :key="index">
                      <td 
                        v-if="col.visible"
                        :colspan="col.colspan"
                        :style="{ 
                          width: col.width ? col.width + 'px' : 'auto',
                          textAlign: col.isLabel ? 'center' : col.align
                        }"
                        class="total-cell"
                      >
                        <span :class="{ 'total-label': col.isLabel }">{{ col.value }}</span>
                      </td>
                    </template>
                  </tr>
                </table>
              </div>
              
              <!-- 空状态提示 -->
              <div v-if="templateColumns.length === 0" class="empty-table">
                <el-empty description="拖拽左侧字段到这里，或点击【添加父列/添加普通列】按钮" :image-size="100" />
              </div>
            </div>

            <!-- 表尾区域 -->
            <div class="report-footer">
              <div class="footer-fields">
                <div 
                  v-for="row in Object.keys(groupedFooterFields).sort((a, b) => a - b)" 
                  :key="row"
                  class="footer-field-row"
                >
                  <div 
                    v-for="field in groupedFooterFields[row]" 
                    :key="field.index"
                    class="footer-field-item"
                    @click="selectFooterField(field.index)"
                    :class="{ 'selected': selectedFooterFieldIndex === field.index }"
                  >
                    <span class="field-label">{{ field.label }}：</span>
                    <span class="field-value">{{ getFieldValuePreview(field) }}</span>
                    <el-icon class="remove-icon" @click.stop="removeFooterField(field.index)">
                      <Close />
                    </el-icon>
                  </div>
                </div>
                <el-button 
                  size="small" 
                  text 
                  @click="showAddFooterFieldDialog = true"
                  v-if="reportFooterFields.length === 0"
                >
                  + 点击添加表尾字段
                </el-button>
              </div>
            </div>
          </div>
        </div>

        <!-- 右侧：属性配置 -->
        <div class="designer-properties">
          <div class="properties-header">
            <h4>
              {{ selectedHeaderFieldIndex !== null ? '表头字段属性' : 
                 selectedFooterFieldIndex !== null ? '表尾字段属性' : 
                 selectedChildColumn ? '子列属性' : '列属性' }}
            </h4>
          </div>
          <el-scrollbar height="calc(100vh - 300px)">
            <!-- 表头字段属性 -->
            <el-form 
              v-if="selectedHeaderField" 
              :model="selectedHeaderField" 
              label-width="70px" 
              size="small"
              label-position="top"
            >
              <el-form-item label="字段标签">
                <el-input v-model="selectedHeaderField.label" />
              </el-form-item>

              <el-form-item label="所在行">
                <el-input-number 
                  v-model="selectedHeaderField.row" 
                  :min="1" 
                  :max="10"
                  style="width: 100%"
                />
              </el-form-item>

              <el-form-item label="字段类型">
                <el-radio-group v-model="selectedHeaderField.type">
                  <el-radio label="system">系统字段</el-radio>
                  <el-radio label="text">固定文本</el-radio>
                  <el-radio label="date">日期</el-radio>
                </el-radio-group>
              </el-form-item>

              <el-form-item label="系统字段" v-if="selectedHeaderField.type === 'system'">
                <el-select v-model="selectedHeaderField.systemField" style="width: 100%">
                  <el-option label="公司名称" value="company_name" />
                  <el-option label="当前地区" value="region_name" />
                  <el-option label="编号" value="region_code" />
                  <el-option label="账套名称" value="account_set_name" />
                  <el-option label="当前年份" value="current_year" />
                  <el-option label="当前月份" value="current_month" />
                </el-select>
              </el-form-item>

              <el-form-item label="字段值" v-if="selectedHeaderField.type === 'text'">
                <el-input v-model="selectedHeaderField.value" />
              </el-form-item>

              <el-form-item label="日期格式" v-if="selectedHeaderField.type === 'date'">
                <el-select v-model="selectedHeaderField.dateFormat" style="width: 100%">
                  <el-option label="2025-01-02" value="YYYY-MM-DD" />
                  <el-option label="2025年01月02日" value="YYYY年MM月DD日" />
                  <el-option label="2025/01/02" value="YYYY/MM/DD" />
                  <el-option label="2025-01" value="YYYY-MM" />
                </el-select>
              </el-form-item>

              <el-divider />

              <el-form-item>
                <el-button 
                  type="danger" 
                  size="small" 
                  @click="removeHeaderField(selectedHeaderFieldIndex)"
                  style="width: 100%"
                >
                  删除此字段
                </el-button>
              </el-form-item>
            </el-form>

            <!-- 表尾字段属性 -->
            <el-form 
              v-else-if="selectedFooterFieldIndex !== null && reportFooterFields[selectedFooterFieldIndex]" 
              :model="reportFooterFields[selectedFooterFieldIndex]" 
              label-width="70px" 
              size="small"
              label-position="top"
            >
              <el-form-item label="字段标签">
                <el-input v-model="reportFooterFields[selectedFooterFieldIndex].label" />
              </el-form-item>

              <el-form-item label="所在行">
                <el-input-number 
                  v-model="reportFooterFields[selectedFooterFieldIndex].row" 
                  :min="1" 
                  :max="10"
                  style="width: 100%"
                />
              </el-form-item>

              <el-form-item label="字段类型">
                <el-radio-group v-model="reportFooterFields[selectedFooterFieldIndex].type">
                  <el-radio label="system">系统字段</el-radio>
                  <el-radio label="text">固定文本</el-radio>
                  <el-radio label="date">日期</el-radio>
                </el-radio-group>
              </el-form-item>

              <el-form-item label="系统字段" v-if="reportFooterFields[selectedFooterFieldIndex].type === 'system'">
                <el-select v-model="reportFooterFields[selectedFooterFieldIndex].systemField" style="width: 100%">
                  <el-option label="制表人" value="creator_name" />
                  <el-option label="审核人" value="auditor_name" />
                  <el-option label="当前用户" value="current_user" />
                </el-select>
              </el-form-item>

              <el-form-item label="字段值" v-if="reportFooterFields[selectedFooterFieldIndex].type === 'text'">
                <el-input v-model="reportFooterFields[selectedFooterFieldIndex].value" />
              </el-form-item>

              <el-form-item label="日期格式" v-if="reportFooterFields[selectedFooterFieldIndex].type === 'date'">
                <el-select v-model="reportFooterFields[selectedFooterFieldIndex].dateFormat" style="width: 100%">
                  <el-option label="2025-01-02" value="YYYY-MM-DD" />
                  <el-option label="2025年01月02日" value="YYYY年MM月DD日" />
                  <el-option label="2025/01/02" value="YYYY/MM/DD" />
                  <el-option label="2025-01" value="YYYY-MM" />
                </el-select>
              </el-form-item>

              <el-divider />

              <el-form-item>
                <el-button 
                  type="danger" 
                  size="small" 
                  @click="removeFooterField(selectedFooterFieldIndex)"
                  style="width: 100%"
                >
                  删除此字段
                </el-button>
              </el-form-item>
            </el-form>

            <!-- 子列属性 -->
            <el-form 
              v-else-if="selectedChildColumn" 
              :model="selectedChildColumn" 
              label-width="70px" 
              size="small"
              label-position="top"
            >
              <el-form-item label="列标题">
                <el-input v-model="selectedChildColumn.title" />
              </el-form-item>

              <el-form-item label="数据字段">
                <el-select v-model="selectedChildColumn.field" style="width: 100%">
                  <el-option 
                    v-for="f in availableFields" 
                    :key="f.key" 
                    :label="f.label" 
                    :value="f.key"
                  />
                </el-select>
              </el-form-item>

              <el-form-item label="列宽">
                <el-input-number 
                  v-model="selectedChildColumn.width" 
                  :min="60" 
                  :max="500"
                  :step="10"
                  style="width: 100%"
                />
              </el-form-item>

              <el-form-item label="对齐方式">
                <el-radio-group v-model="selectedChildColumn.align">
                  <el-radio-button label="left">左对齐</el-radio-button>
                  <el-radio-button label="center">居中</el-radio-button>
                  <el-radio-button label="right">右对齐</el-radio-button>
                </el-radio-group>
              </el-form-item>

              <el-form-item label="数据格式">
                <el-select v-model="selectedChildColumn.format" style="width: 100%">
                  <el-option label="文本" value="text" />
                  <el-option label="数字" value="number" />
                  <el-option label="货币" value="currency" />
                  <el-option label="百分比" value="percent" />
                  <el-option label="日期" value="date" />
                </el-select>
              </el-form-item>

              <el-form-item label="显示合计">
                <el-switch 
                  v-model="selectedChildColumn.showTotal" 
                  active-text="是" 
                  inactive-text="否"
                />
              </el-form-item>

              <el-divider />

              <el-form-item>
                <el-button 
                  type="danger" 
                  size="small" 
                  @click="removeChildColumn"
                  style="width: 100%"
                >
                  删除此子列
                </el-button>
              </el-form-item>
            </el-form>

            <!-- 表格列属性 -->
            <el-form 
              v-else-if="selectedColumn" 
              :model="selectedColumn" 
              label-width="70px" 
              size="small"
              label-position="top"
            >
              <el-form-item label="列标题">
                <el-input v-model="selectedColumn.title" />
              </el-form-item>

              <el-form-item label="数据字段" v-if="!selectedColumn.isParent">
                <el-select v-model="selectedColumn.field" style="width: 100%">
                  <el-option 
                    v-for="f in availableFields" 
                    :key="f.key" 
                    :label="f.label" 
                    :value="f.key"
                  />
                </el-select>
              </el-form-item>

              <el-form-item label="列宽" v-if="!selectedColumn.isParent">
                <el-input-number 
                  v-model="selectedColumn.width" 
                  :min="60" 
                  :max="500"
                  :step="10"
                  style="width: 100%"
                />
              </el-form-item>

              <el-form-item label="对齐方式">
                <el-radio-group v-model="selectedColumn.align">
                  <el-radio-button label="left">左对齐</el-radio-button>
                  <el-radio-button label="center">居中</el-radio-button>
                  <el-radio-button label="right">右对齐</el-radio-button>
                </el-radio-group>
              </el-form-item>

              <el-form-item label="数据格式">
                <el-select v-model="selectedColumn.format" style="width: 100%">
                  <el-option label="文本" value="text" />
                  <el-option label="数字" value="number" />
                  <el-option label="货币" value="currency" />
                  <el-option label="百分比" value="percent" />
                  <el-option label="日期" value="date" />
                </el-select>
              </el-form-item>

              <el-form-item label="显示合计">
                <el-switch 
                  v-model="selectedColumn.showTotal" 
                  active-text="是" 
                  inactive-text="否"
                />
                <el-text size="small" type="info" style="display: block; margin-top: 4px;">
                  开启后会在表格底部显示该列的合计值
                </el-text>
              </el-form-item>

              <el-divider />

              <el-form-item>
                <el-button 
                  type="danger" 
                  size="small" 
                  @click="removeColumn"
                  style="width: 100%"
                >
                  删除此列
                </el-button>
              </el-form-item>
            </el-form>
            <el-empty 
              v-else 
              description="请选择一个字段或列进行配置" 
              :image-size="80"
            />
          </el-scrollbar>
        </div>
      </div>
    </div>
    
    <template #footer>
      <el-button @click="handleClose">取消</el-button>
      <el-button type="primary" @click="saveTemplate" :loading="submitting">
        保存模板
      </el-button>
    </template>
  </el-dialog>

  <!-- 添加表头字段对话框 -->
  <el-dialog
    v-model="showAddHeaderFieldDialog"
    title="添加表头字段"
    width="500px"
  >
    <el-form :model="newHeaderField" label-width="100px">
      <el-form-item label="字段标签">
        <el-input 
          v-model="newHeaderField.label" 
          placeholder="例如：公司、部门、日期等"
        />
        <el-text size="small" type="info">显示在报表左侧的标签名称</el-text>
      </el-form-item>

      <el-form-item label="所在行">
        <el-input-number 
          v-model="newHeaderField.row" 
          :min="1" 
          :max="10"
          style="width: 100%"
        />
        <el-text size="small" type="info">字段显示在第几行（1-10）</el-text>
      </el-form-item>

      <el-form-item label="字段类型">
        <el-radio-group v-model="newHeaderField.type">
          <el-radio label="system">系统字段</el-radio>
          <el-radio label="text">固定文本</el-radio>
          <el-radio label="date">日期</el-radio>
        </el-radio-group>
      </el-form-item>

      <el-form-item label="字段值" v-if="newHeaderField.type === 'system'">
        <el-select v-model="newHeaderField.systemField" placeholder="选择系统字段" style="width: 100%">
          <el-option label="公司名称" value="company_name" />
          <el-option label="当前地区" value="region_name" />
          <el-option label="编号" value="region_code" />
          <el-option label="账套名称" value="account_set_name" />
          <el-option label="当前年份" value="current_year" />
          <el-option label="当前月份" value="current_month" />
        </el-select>
        <el-text size="small" type="info">从系统中动态获取的值</el-text>
      </el-form-item>

      <el-form-item label="字段值" v-if="newHeaderField.type === 'text'">
        <el-input 
          v-model="newHeaderField.value" 
          placeholder="输入固定文本"
        />
        <el-text size="small" type="info">固定显示的文本内容</el-text>
      </el-form-item>

      <el-form-item label="日期格式" v-if="newHeaderField.type === 'date'">
        <el-select v-model="newHeaderField.dateFormat" style="width: 100%">
          <el-option label="2025-01-02" value="YYYY-MM-DD" />
          <el-option label="2025年01月02日" value="YYYY年MM月DD日" />
          <el-option label="2025/01/02" value="YYYY/MM/DD" />
          <el-option label="2025-01" value="YYYY-MM" />
        </el-select>
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="showAddHeaderFieldDialog = false">取消</el-button>
      <el-button type="primary" @click="confirmAddHeaderField">确定</el-button>
    </template>
  </el-dialog>

  <!-- 添加表尾字段对话框 -->
  <el-dialog
    v-model="showAddFooterFieldDialog"
    title="添加表尾字段"
    width="500px"
  >
    <el-form :model="newFooterField" label-width="100px">
      <el-form-item label="字段标签">
        <el-input 
          v-model="newFooterField.label" 
          placeholder="例如：制表人、审核人、日期等"
        />
        <el-text size="small" type="info">显示在报表左侧的标签名称</el-text>
      </el-form-item>

      <el-form-item label="所在行">
        <el-input-number 
          v-model="newFooterField.row" 
          :min="1" 
          :max="10"
          style="width: 100%"
        />
        <el-text size="small" type="info">字段显示在第几行（1-10）</el-text>
      </el-form-item>

      <el-form-item label="字段类型">
        <el-radio-group v-model="newFooterField.type">
          <el-radio label="system">系统字段</el-radio>
          <el-radio label="text">固定文本</el-radio>
          <el-radio label="date">日期</el-radio>
        </el-radio-group>
      </el-form-item>

      <el-form-item label="字段值" v-if="newFooterField.type === 'system'">
        <el-select v-model="newFooterField.systemField" placeholder="选择系统字段" style="width: 100%">
          <el-option label="制表人" value="creator_name" />
          <el-option label="审核人" value="auditor_name" />
          <el-option label="当前用户" value="current_user" />
        </el-select>
        <el-text size="small" type="info">从系统中动态获取的值</el-text>
      </el-form-item>

      <el-form-item label="字段值" v-if="newFooterField.type === 'text'">
        <el-input 
          v-model="newFooterField.value" 
          placeholder="输入固定文本或留空（如：_______）"
        />
        <el-text size="small" type="info">固定显示的文本内容</el-text>
      </el-form-item>

      <el-form-item label="日期格式" v-if="newFooterField.type === 'date'">
        <el-select v-model="newFooterField.dateFormat" style="width: 100%">
          <el-option label="2025-01-02" value="YYYY-MM-DD" />
          <el-option label="2025年01月02日" value="YYYY年MM月DD日" />
          <el-option label="2025/01/02" value="YYYY/MM/DD" />
          <el-option label="2025-01" value="YYYY-MM" />
        </el-select>
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="showAddFooterFieldDialog = false">取消</el-button>
      <el-button type="primary" @click="confirmAddFooterField">确定</el-button>
    </template>
  </el-dialog>

  <!-- 编辑报表标题对话框 -->
  <el-dialog
    v-model="showEditTitleDialog"
    title="设置报表标题"
    width="500px"
  >
    <el-input 
      v-model="reportTitle" 
      placeholder="请输入报表标题"
      size="large"
    />
    <template #footer>
      <el-button @click="showEditTitleDialog = false">取消</el-button>
      <el-button type="primary" @click="showEditTitleDialog = false">确定</el-button>
    </template>
  </el-dialog>
</template>


<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Delete, Back, Right, Edit, Close, Grid, RefreshLeft, Refresh } from '@element-plus/icons-vue'
import request from '@/api/request'

// Props
const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  availableFields: {
    type: Array,
    default: () => []
  },
  templateType: {
    type: String,
    default: 'social_security' // social_security, medical_insurance, housing_fund
  },
  region: {
    type: Object,
    default: null
  },
  regionNameField: {
    type: String,
    default: 'name' // 地区名称字段名，公积金用 region_name
  },
  isBatchMode: {
    type: Boolean,
    default: false
  },
  batchRegions: {
    type: Array,
    default: () => []
  },
  editTemplate: {
    type: Object,
    default: null
  },
  accountSetId: {
    type: [Number, String],
    default: null
  }
})

// Emits
const emit = defineEmits(['update:visible', 'save', 'close'])

// 对话框可见性
const dialogVisible = computed({
  get: () => props.visible,
  set: (val) => emit('update:visible', val)
})

// 响应式数据
const submitting = ref(false)
const sidebarTab = ref('header')
const showAddHeaderFieldDialog = ref(false)
const showAddFooterFieldDialog = ref(false)
const showEditTitleDialog = ref(false)

// 模板表单
const templateForm = ref({
  name: '',
  description: ''
})

// 报表标题
const reportTitle = ref('')

// 模板列配置
const templateColumns = ref([])
const selectedColumnIndex = ref(null)
const selectedChildColumnIndex = ref(null)  // 新增：选中的子列索引

// 操作历史（用于撤回）
const historyStack = ref([])
const maxHistoryLength = 20

// 表头字段
const reportHeaderFields = ref([])
const selectedHeaderFieldIndex = ref(null)

// 表尾字段
const reportFooterFields = ref([])
const selectedFooterFieldIndex = ref(null)

// 新建表头字段表单
const newHeaderField = ref({
  label: '',
  type: 'system',
  systemField: 'company_name',
  value: '',
  dateFormat: 'YYYY-MM-DD',
  row: 1
})

// 新建表尾字段表单
const newFooterField = ref({
  label: '',
  type: 'system',
  systemField: 'creator_name',
  value: '',
  dateFormat: 'YYYY-MM-DD',
  row: 1
})

// 拖拽相关
const draggedField = ref(null)

// 预览数据
const previewData = ref([
  { serial_number: 1, employee_name: '张三', id_number: '110101199001011234', project_name: '项目A' },
  { serial_number: 2, employee_name: '李四', id_number: '110101199002022345', project_name: '项目B' }
])

// 计算属性
const selectedColumn = computed(() => {
  // 如果选中了子列，不返回父列
  if (selectedChildColumnIndex.value !== null) {
    return null
  }
  if (selectedColumnIndex.value !== null && templateColumns.value[selectedColumnIndex.value]) {
    return templateColumns.value[selectedColumnIndex.value]
  }
  return null
})

const selectedHeaderField = computed(() => {
  if (selectedHeaderFieldIndex.value !== null && reportHeaderFields.value[selectedHeaderFieldIndex.value]) {
    return reportHeaderFields.value[selectedHeaderFieldIndex.value]
  }
  return null
})

const canMoveLeft = computed(() => {
  return selectedColumnIndex.value !== null && selectedColumnIndex.value > 0
})

const canMoveRight = computed(() => {
  return selectedColumnIndex.value !== null && selectedColumnIndex.value < templateColumns.value.length - 1
})

// 按行分组的表头字段
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

// 按行分组的表尾字段
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

// 监听编辑模板变化，加载模板数据
watch(() => props.editTemplate, (template) => {
  if (template) {
    loadTemplateData(template)
  }
}, { immediate: true })

// 监听对话框打开，重置状态
watch(() => props.visible, (val) => {
  if (val && !props.editTemplate) {
    resetForm()
  }
})

// 加载模板数据
const loadTemplateData = (template) => {
  templateForm.value.name = template.name || ''
  templateForm.value.description = template.description || ''
  reportTitle.value = template.report_title || ''
  
  // 加载列配置
  if (template.fields) {
    templateColumns.value = template.fields.map(f => ({
      field: f.key,
      title: f.label,
      width: f.width || 120,
      align: f.align || 'left',
      format: f.format || 'text',
      showTotal: f.show_total || false,
      isParent: f.is_parent || false,
      children: f.children || []
    }))
  }
  
  // 加载表头字段
  if (template.header_fields) {
    reportHeaderFields.value = template.header_fields.map(f => ({
      label: f.label,
      type: f.type,
      systemField: f.system_field,
      value: f.value,
      dateFormat: f.date_format,
      row: f.row || 1
    }))
  }
  
  // 加载表尾字段
  if (template.footer_fields) {
    reportFooterFields.value = template.footer_fields.map(f => ({
      label: f.label,
      type: f.type,
      systemField: f.system_field,
      value: f.value,
      dateFormat: f.date_format,
      row: f.row || 1
    }))
  }
}

// 重置表单
const resetForm = () => {
  templateForm.value = { name: '', description: '' }
  reportTitle.value = ''
  templateColumns.value = []
  selectedColumnIndex.value = null
  selectedChildColumnIndex.value = null
  reportHeaderFields.value = []
  selectedHeaderFieldIndex.value = null
  reportFooterFields.value = []
  selectedFooterFieldIndex.value = null
  sidebarTab.value = 'header'
  historyStack.value = []
}

// 获取字段值预览
const getFieldValuePreview = (field) => {
  if (field.type === 'system') {
    const systemLabels = {
      company_name: '【公司名称】',
      region_name: '【当前地区】',
      region_code: '【编号】',
      account_set_name: '【账套名称】',
      current_year: '【当前年份】',
      current_month: '【当前月份】',
      creator_name: '【制表人】',
      auditor_name: '【审核人】',
      current_user: '【当前用户】'
    }
    return systemLabels[field.systemField] || '【系统字段】'
  } else if (field.type === 'text') {
    return field.value || '（空）'
  } else if (field.type === 'date') {
    return `【日期: ${field.dateFormat}】`
  }
  return ''
}

// 选择表头字段
const selectHeaderField = (index) => {
  selectedHeaderFieldIndex.value = index
  selectedFooterFieldIndex.value = null
  selectedColumnIndex.value = null
}

// 选择表尾字段
const selectFooterField = (index) => {
  selectedFooterFieldIndex.value = index
  selectedHeaderFieldIndex.value = null
  selectedColumnIndex.value = null
}

// 添加表头字段
const confirmAddHeaderField = () => {
  if (!newHeaderField.value.label) {
    ElMessage.warning('请输入字段标签')
    return
  }
  
  reportHeaderFields.value.push({
    label: newHeaderField.value.label,
    type: newHeaderField.value.type,
    systemField: newHeaderField.value.systemField,
    value: newHeaderField.value.value,
    dateFormat: newHeaderField.value.dateFormat,
    row: newHeaderField.value.row || 1
  })
  
  showAddHeaderFieldDialog.value = false
  newHeaderField.value = {
    label: '',
    type: 'system',
    systemField: 'company_name',
    value: '',
    dateFormat: 'YYYY-MM-DD',
    row: 1
  }
}

// 删除表头字段
const removeHeaderField = (index) => {
  saveHistory()
  reportHeaderFields.value.splice(index, 1)
  if (selectedHeaderFieldIndex.value === index) {
    selectedHeaderFieldIndex.value = null
  }
  ElMessage.success('表头字段已删除')
}

// 添加表尾字段
const confirmAddFooterField = () => {
  if (!newFooterField.value.label) {
    ElMessage.warning('请输入字段标签')
    return
  }
  
  reportFooterFields.value.push({
    label: newFooterField.value.label,
    type: newFooterField.value.type,
    systemField: newFooterField.value.systemField,
    value: newFooterField.value.value,
    dateFormat: newFooterField.value.dateFormat,
    row: newFooterField.value.row || 1
  })
  
  showAddFooterFieldDialog.value = false
  newFooterField.value = {
    label: '',
    type: 'system',
    systemField: 'creator_name',
    value: '',
    dateFormat: 'YYYY-MM-DD',
    row: 1
  }
}

// 删除表尾字段
const removeFooterField = (index) => {
  saveHistory()
  reportFooterFields.value.splice(index, 1)
  if (selectedFooterFieldIndex.value === index) {
    selectedFooterFieldIndex.value = null
  }
  ElMessage.success('表尾字段已删除')
}

// 编辑报表标题
const editReportTitle = () => {
  showEditTitleDialog.value = true
}

// 拖拽开始
const handleDragStart = (field) => {
  draggedField.value = field
}

// 拖拽放置
const handleDrop = (event) => {
  event.preventDefault()
  if (draggedField.value) {
    addColumnWithField(draggedField.value)
    draggedField.value = null
  }
}

// 拖拽到父列
const handleDropToParent = (event, parentIndex) => {
  event.preventDefault()
  event.stopPropagation()
  
  if (draggedField.value && templateColumns.value[parentIndex]) {
    const parent = templateColumns.value[parentIndex]
    if (parent.isParent) {
      saveHistory()
      if (!parent.children) {
        parent.children = []
      }
      
      // 默认居中对齐
      let align = 'center'
      if (draggedField.value.key.includes('amount') || draggedField.value.key.includes('base') || 
          draggedField.value.key.includes('company') || draggedField.value.key.includes('employee') || 
          draggedField.value.key.includes('total')) {
        align = 'right'
      }
      
      parent.children.push({
        field: draggedField.value.key,
        title: draggedField.value.label,
        width: 100,
        align: align,
        format: draggedField.value.key.includes('ratio') ? 'percent' : 'currency'
      })
    }
    draggedField.value = null
  }
}

// 添加父列
const addParentColumn = () => {
  saveHistory()
  const newColumn = {
    title: '父列标题',
    isParent: true,
    children: [],
    width: undefined,
    align: 'center'
  }
  templateColumns.value.push(newColumn)
  selectedColumnIndex.value = templateColumns.value.length - 1
  selectedChildColumnIndex.value = null
  ElMessage.success('父列添加成功，可以拖拽字段到父列中')
}

// 添加普通列
const addColumn = () => {
  saveHistory()
  const newColumn = {
    field: 'employee_name',
    title: '新列',
    width: 120,
    align: 'center',  // 默认居中
    format: 'text'
  }
  templateColumns.value.push(newColumn)
  selectedColumnIndex.value = templateColumns.value.length - 1
  selectedChildColumnIndex.value = null
}

// 使用字段添加列
const addColumnWithField = (field) => {
  saveHistory()
  // 默认居中对齐
  let align = 'center'
  // 金额类字段右对齐
  if (field.key.includes('amount') || field.key.includes('base') || field.key.includes('company') || 
      field.key.includes('employee') || field.key.includes('total')) {
    align = 'right'
  }
  
  let format = 'text'
  if (field.key.includes('amount') || field.key.includes('base') || field.key.includes('company') || 
      field.key.includes('employee') || field.key.includes('total') || field.key.includes('fund')) {
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
  selectedChildColumnIndex.value = null
}

// 删除列
const removeColumn = () => {
  if (selectedColumnIndex.value !== null) {
    saveHistory()
    templateColumns.value.splice(selectedColumnIndex.value, 1)
    selectedColumnIndex.value = null
    selectedChildColumnIndex.value = null
    ElMessage.success('列已删除')
  }
}

// 获取总列数
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
  selectedChildColumnIndex.value = null  // 清空子列选择
  selectedHeaderFieldIndex.value = null
  selectedFooterFieldIndex.value = null
}

// 选择子列
const selectChildColumn = (parentIndex, childIndex) => {
  selectedColumnIndex.value = parentIndex
  selectedChildColumnIndex.value = childIndex
  selectedHeaderFieldIndex.value = null
  selectedFooterFieldIndex.value = null
}

// 获取选中的子列
const selectedChildColumn = computed(() => {
  if (selectedColumnIndex.value !== null && 
      selectedChildColumnIndex.value !== null && 
      templateColumns.value[selectedColumnIndex.value]?.children?.[selectedChildColumnIndex.value]) {
    return templateColumns.value[selectedColumnIndex.value].children[selectedChildColumnIndex.value]
  }
  return null
})

// 删除子列
const removeChildColumn = () => {
  if (selectedColumnIndex.value !== null && selectedChildColumnIndex.value !== null) {
    saveHistory()
    const parent = templateColumns.value[selectedColumnIndex.value]
    if (parent && parent.children) {
      parent.children.splice(selectedChildColumnIndex.value, 1)
      selectedChildColumnIndex.value = null
      ElMessage.success('子列已删除')
    }
  }
}

// 是否可以删除
const canDelete = computed(() => {
  return selectedColumnIndex.value !== null || 
         selectedHeaderFieldIndex.value !== null || 
         selectedFooterFieldIndex.value !== null
})

// 统一删除处理
const handleDeleteSelected = () => {
  if (selectedChildColumnIndex.value !== null && selectedColumnIndex.value !== null) {
    removeChildColumn()
  } else if (selectedColumnIndex.value !== null) {
    removeColumn()
  } else if (selectedHeaderFieldIndex.value !== null) {
    removeHeaderField(selectedHeaderFieldIndex.value)
  } else if (selectedFooterFieldIndex.value !== null) {
    removeFooterField(selectedFooterFieldIndex.value)
  }
}

// 保存历史记录（用于撤回）
const saveHistory = () => {
  const state = {
    templateColumns: JSON.parse(JSON.stringify(templateColumns.value)),
    reportHeaderFields: JSON.parse(JSON.stringify(reportHeaderFields.value)),
    reportFooterFields: JSON.parse(JSON.stringify(reportFooterFields.value)),
    reportTitle: reportTitle.value,
    templateForm: JSON.parse(JSON.stringify(templateForm.value))
  }
  historyStack.value.push(state)
  if (historyStack.value.length > maxHistoryLength) {
    historyStack.value.shift()
  }
}

// 撤回操作
const undoAction = () => {
  if (historyStack.value.length > 0) {
    const state = historyStack.value.pop()
    templateColumns.value = state.templateColumns
    reportHeaderFields.value = state.reportHeaderFields
    reportFooterFields.value = state.reportFooterFields
    reportTitle.value = state.reportTitle
    templateForm.value = state.templateForm
    selectedColumnIndex.value = null
    selectedChildColumnIndex.value = null
    selectedHeaderFieldIndex.value = null
    selectedFooterFieldIndex.value = null
    ElMessage.success('已撤回上一步操作')
  }
}

// 重置设计器
const resetDesigner = () => {
  ElMessageBox.confirm('确定要重置所有设计内容吗？此操作不可撤回。', '确认重置', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    resetForm()
    historyStack.value = []
    ElMessage.success('已重置')
  }).catch(() => {})
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

// 计算合计行的列配置（支持合并单元格）
const totalRowColumns = computed(() => {
  const columns = []
  let mergeCount = 0
  let firstTotalFound = false
  
  // 展开所有列（包括子列）
  const flatColumns = []
  templateColumns.value.forEach(col => {
    if (col.children && col.children.length > 0) {
      col.children.forEach(child => {
        flatColumns.push({
          ...child,
          parentTitle: col.title,
          isChild: true
        })
      })
    } else if (!col.isParent) {
      flatColumns.push({
        ...col,
        isChild: false
      })
    }
  })
  
  // 找到第一个开启合计的列的索引
  const firstTotalIndex = flatColumns.findIndex(col => col.showTotal)
  
  flatColumns.forEach((col, index) => {
    if (!firstTotalFound && !col.showTotal) {
      // 还没找到第一个合计列，累计合并数
      mergeCount++
    } else if (!firstTotalFound && col.showTotal) {
      // 找到第一个合计列
      firstTotalFound = true
      
      // 添加合并的"合计"单元格
      if (mergeCount > 0) {
        columns.push({
          visible: true,
          colspan: mergeCount,
          value: '合计',
          isLabel: true,
          align: 'center',
          width: null
        })
      }
      
      // 添加第一个合计列
      const sum = previewData.value.reduce((acc, row) => {
        const field = col.isChild ? col.field : col.field
        return acc + (Number(row[field]) || 0)
      }, 0)
      columns.push({
        visible: true,
        colspan: 1,
        value: formatCellValue(sum, col.format),
        isLabel: false,
        align: col.align || 'right',
        width: col.width
      })
    } else {
      // 后续列
      if (col.showTotal) {
        const sum = previewData.value.reduce((acc, row) => {
          return acc + (Number(row[col.field]) || 0)
        }, 0)
        columns.push({
          visible: true,
          colspan: 1,
          value: formatCellValue(sum, col.format),
          isLabel: false,
          align: col.align || 'right',
          width: col.width
        })
      } else {
        // 没开合计的列显示空
        columns.push({
          visible: true,
          colspan: 1,
          value: '',
          isLabel: false,
          align: col.align || 'center',
          width: col.width
        })
      }
    }
  })
  
  // 如果没有找到任何合计列但有列存在，显示空行
  if (!firstTotalFound && flatColumns.length > 0) {
    return flatColumns.map(col => ({
      visible: true,
      colspan: 1,
      value: '',
      isLabel: false,
      align: col.align || 'center',
      width: col.width
    }))
  }
  
  return columns
})

// 关闭对话框
const handleClose = () => {
  resetForm()
  emit('update:visible', false)
  emit('close')
}

// 获取地区名称
const getRegionName = (region) => {
  return region[props.regionNameField] || region.name || region.region_name || ''
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

  // 验证账套ID
  if (!props.accountSetId) {
    ElMessage.warning('请先选择账套')
    return
  }
  
  try {
    submitting.value = true
    
    // 构建模板数据
    const buildTemplateData = (region, namePrefix = '') => {
      return {
        name: namePrefix ? `${namePrefix}-${templateForm.value.name}` : templateForm.value.name,
        description: templateForm.value.description,
        report_title: reportTitle.value,
        region_id: region?.id || null,
        region_type: props.templateType,
        fields: templateColumns.value.map((col, index) => ({
          key: col.field,
          label: col.title,
          order: index,
          width: col.width,
          align: col.align,
          format: col.format,
          show_total: col.showTotal,
          is_parent: col.isParent,
          children: col.children
        })),
        account_set_id: props.accountSetId,
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
    }

    // 批量创建模式
    if (props.isBatchMode && props.batchRegions.length > 0) {
      let successCount = 0
      let failCount = 0
      
      for (const region of props.batchRegions) {
        try {
          const regionName = getRegionName(region)
          const templateData = buildTemplateData(region, regionName)
          const response = await request.post('/report-templates', templateData)
          
          if (response.success) {
            successCount++
          } else {
            failCount++
          }
        } catch (error) {
          console.error(`为地区创建模板失败:`, error)
          failCount++
        }
      }
      
      if (successCount > 0) {
        ElMessage.success(`成功为 ${successCount} 个地区创建模板${failCount > 0 ? `，${failCount} 个失败` : ''}`)
        emit('save', { success: true, mode: 'batch', successCount, failCount })
        handleClose()
      } else {
        ElMessage.error('批量创建模板失败')
      }
    } else {
      // 单个创建/编辑模式
      const templateData = buildTemplateData(props.region)
      
      console.log('保存模板数据:', templateData) // 调试日志
      
      let response
      if (props.editTemplate?.id) {
        response = await request.put(`/report-templates/${props.editTemplate.id}`, templateData)
      } else {
        response = await request.post('/report-templates', templateData)
      }
      
      if (response.success) {
        console.log('模板保存成功，准备关闭对话框')
        ElMessage.success(props.editTemplate?.id ? '模板更新成功' : '模板创建成功')
        emit('save', { success: true, mode: 'single', data: response.data })
        handleClose()
        console.log('对话框已关闭')
      } else {
        throw new Error(response.message || '保存失败')
      }
    }
  } catch (error) {
    console.error('保存模板失败:', error)
    // 显示更详细的错误信息
    const errorMsg = error.response?.data?.message || error.message || '保存模板失败'
    ElMessage.error(errorMsg)
  } finally {
    submitting.value = false
  }
}
</script>


<style scoped>
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

.total-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
}

.total-table td {
  padding: 12px 8px;
  border: 1px solid #ebeef5;
  font-size: 14px;
}

.total-table .total-cell {
  font-weight: bold;
  color: #303133;
}

.total-table .total-label {
  font-weight: bold;
  color: #409eff;
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
  flex-wrap: wrap;
  gap: 12px;
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
  flex-wrap: wrap;
  gap: 12px;
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

.column-header.child-column:hover {
  background: #ecf5ff;
}

.column-header.child-column.selected {
  background: #409eff;
  color: white;
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
