import * as XLSX from 'xlsx'

/**
 * 导出社保明细到Excel
 * @param {Array} data - 表格数据
 * @param {String} title - 标题
 * @param {Array} columns - 列配置
 * @param {String} filename - 文件名
 */
export function exportSocialSecurityToExcel(data, title, columns, filename) {
  // 创建工作簿
  const wb = XLSX.utils.book_new()
  
  // 准备数据
  const exportData = []
  
  // 添加标题行
  exportData.push([title])
  
  // 添加空行
  exportData.push([])
  
  // 构建多级表头
  const headerData = buildMultiLevelHeaders(columns)
  exportData.push(...headerData)
  
  // 添加数据行
  let dataRowIndex = exportData.length
  data.forEach((row, index) => {
    // 跳过标题行
    if (row.isTitleRow) {
      return
    }
    
    const rowData = []
    columns.forEach(col => {
      let value = ''
      if (col.prop) {
        value = row[col.prop] || ''
      } else if (col.template) {
        // 处理特殊模板列
        value = getTemplateValue(row, col)
      }
      rowData.push(value)
    })
    exportData.push(rowData)
  })
  
  // 添加合计行
  const totalRow = calculateTotalRow(data, columns)
  if (totalRow.length > 0) {
    exportData.push(totalRow)
  }
  
  // 创建工作表
  const ws = XLSX.utils.aoa_to_sheet(exportData)
  
  // 设置列宽
  const colWidths = columns.map(col => ({
    wch: col.width ? Math.max(col.width / 8, 12) : 15
  }))
  ws['!cols'] = colWidths
  
  // 设置样式
  setExcelStyles(ws, exportData, columns, title)
  
  // 添加工作表到工作簿
  XLSX.utils.book_append_sheet(wb, ws, '社保明细')
  
  // 导出文件
  XLSX.writeFile(wb, filename)
}

/**
 * 构建多级表头
 * @param {Array} columns - 列配置
 * @returns {Array} 表头数据
 */
function buildMultiLevelHeaders(columns) {
  const headers = []
  
  // 第一级表头
  const firstLevelHeaders = []
  const secondLevelHeaders = []
  
  // 基础列
  const basicColumns = [
    '序号', '姓名', '身份证号', '项目', '参保日期', '类型', '费款所属期', 
    '医疗基数', '养老、失业、工伤基数'
  ]
  
  // 添加基础列的第一级表头
  basicColumns.forEach(col => {
    firstLevelHeaders.push(col)
    secondLevelHeaders.push('')
  })
  
  // 单位部分
  firstLevelHeaders.push('单位部分')
  secondLevelHeaders.push('医疗保险 6.5%')
  secondLevelHeaders.push('养老保险 16%')
  secondLevelHeaders.push('失业保险 0.5%')
  secondLevelHeaders.push('工伤保险 0.32%')
  secondLevelHeaders.push('大额医疗')
  secondLevelHeaders.push('单位缴纳保险合计')
  
  // 个人部分
  firstLevelHeaders.push('个人部分')
  secondLevelHeaders.push('医疗保险 2%')
  secondLevelHeaders.push('养老保险 8%')
  secondLevelHeaders.push('失业保险 0.5%')
  secondLevelHeaders.push('大额医疗')
  secondLevelHeaders.push('个人缴纳保险合计')
  
  // 其他列
  firstLevelHeaders.push('社保合计')
  secondLevelHeaders.push('')
  firstLevelHeaders.push('备注')
  secondLevelHeaders.push('')
  
  headers.push(firstLevelHeaders)
  headers.push(secondLevelHeaders)
  
  return headers
}

/**
 * 计算合计行
 * @param {Array} data - 数据
 * @param {Array} columns - 列配置
 * @returns {Array} 合计行数据
 */
function calculateTotalRow(data, columns) {
  if (!data || data.length === 0) {
    return []
  }
  
  const totalRow = []
  const numericColumns = [
    'medical_base', 'social_security_base', 'company_medical_insurance',
    'company_pension_insurance', 'company_unemployment_insurance', 
    'company_work_injury_insurance', 'company_large_medical',
    'company_total', 'employee_medical_insurance', 'employee_pension_insurance',
    'employee_unemployment_insurance', 'employee_large_medical',
    'employee_total', 'social_security_total'
  ]
  
  columns.forEach((col, index) => {
    if (index === 0) {
      totalRow.push('小计')
    } else if (index === 3) { // 项目列
      totalRow.push('')
    } else if (numericColumns.includes(col.prop)) {
      // 计算数值列的总和
      let sum = 0
      data.forEach(row => {
        if (!row.isTitleRow) {
          const value = parseFloat(row[col.prop]) || 0
          sum += value
        }
      })
      totalRow.push(sum.toFixed(2))
    } else {
      totalRow.push('')
    }
  })
  
  return totalRow
}

/**
 * 设置Excel样式
 * @param {Object} ws - 工作表
 * @param {Array} exportData - 导出数据
 * @param {Array} columns - 列配置
 * @param {String} title - 标题
 */
function setExcelStyles(ws, exportData, columns, title) {
  const merges = []
  
  // 合并标题行
  merges.push({
    s: { r: 0, c: 0 },
    e: { r: 0, c: columns.length - 1 }
  })
  
  // 合并多级表头
  const basicColumnsCount = 9 // 基础列数
  const companyColumnsCount = 6 // 单位部分列数
  const employeeColumnsCount = 5 // 个人部分列数
  const otherColumnsCount = 2 // 其他列数
  
  let currentCol = 0
  
  // 基础列（每个都是单列）
  for (let i = 0; i < basicColumnsCount; i++) {
    merges.push({
      s: { r: 2, c: currentCol },
      e: { r: 3, c: currentCol }
    })
    currentCol++
  }
  
  // 单位部分（合并6列）
  merges.push({
    s: { r: 2, c: currentCol },
    e: { r: 2, c: currentCol + companyColumnsCount - 1 }
  })
  currentCol += companyColumnsCount
  
  // 个人部分（合并5列）
  merges.push({
    s: { r: 2, c: currentCol },
    e: { r: 2, c: currentCol + employeeColumnsCount - 1 }
  })
  currentCol += employeeColumnsCount
  
  // 其他列
  for (let i = 0; i < otherColumnsCount; i++) {
    merges.push({
      s: { r: 2, c: currentCol },
      e: { r: 3, c: currentCol }
    })
    currentCol++
  }
  
  ws['!merges'] = merges
  
  // 设置标题行样式
  const titleCellRef = XLSX.utils.encode_cell({ r: 0, c: 0 })
  if (!ws[titleCellRef]) {
    ws[titleCellRef] = { v: title }
  }
  ws[titleCellRef].s = {
    font: { bold: true, size: 16, color: { rgb: '1976D2' } },
    alignment: { horizontal: 'center', vertical: 'center' },
    fill: { fgColor: { rgb: 'C8E6C9' } },
    border: {
      top: { style: 'thin', color: { rgb: '000000' } },
      bottom: { style: 'thin', color: { rgb: '000000' } },
      left: { style: 'thin', color: { rgb: '000000' } },
      right: { style: 'thin', color: { rgb: '000000' } }
    }
  }
  
  // 设置表头样式
  for (let row = 2; row <= 3; row++) {
    for (let col = 0; col < columns.length; col++) {
      const cellRef = XLSX.utils.encode_cell({ r: row, c: col })
      if (ws[cellRef]) {
        ws[cellRef].s = {
          font: { bold: true, size: 11, color: { rgb: '1976D2' } },
          alignment: { horizontal: 'center', vertical: 'center' },
          fill: { fgColor: { rgb: 'F5F5F5' } },
          border: {
            top: { style: 'thin', color: { rgb: '000000' } },
            bottom: { style: 'thin', color: { rgb: '000000' } },
            left: { style: 'thin', color: { rgb: '000000' } },
            right: { style: 'thin', color: { rgb: '000000' } }
          }
        }
      }
    }
  }
  
  // 设置数据行样式
  const dataStartRow = 4
  const dataEndRow = exportData.length - 2 // 排除合计行
  
  for (let row = dataStartRow; row <= dataEndRow; row++) {
    for (let col = 0; col < columns.length; col++) {
      const cellRef = XLSX.utils.encode_cell({ r: row, c: col })
      if (ws[cellRef]) {
        const isTotal = ['company_total', 'employee_total', 'social_security_total'].includes(columns[col]?.prop)
        
        // 确保单元格存在
        if (!ws[cellRef]) {
          ws[cellRef] = { v: '' }
        }
        
        // 设置样式
        ws[cellRef].s = {
          font: { size: 10, color: { rgb: '000000' }, bold: false },
          alignment: { horizontal: 'center', vertical: 'center' },
          fill: isTotal ? { fgColor: { rgb: 'E3F2FD' } } : { fgColor: { rgb: 'FFFFFF' } },
          border: {
            top: { style: 'thin', color: { rgb: '000000' } },
            bottom: { style: 'thin', color: { rgb: '000000' } },
            left: { style: 'thin', color: { rgb: '000000' } },
            right: { style: 'thin', color: { rgb: '000000' } }
          }
        }
      }
    }
  }
  
  // 设置合计行样式
  const totalRow = exportData.length - 1
  for (let col = 0; col < columns.length; col++) {
    const cellRef = XLSX.utils.encode_cell({ r: totalRow, c: col })
    if (ws[cellRef]) {
      const isTotal = ['company_total', 'employee_total', 'social_security_total'].includes(columns[col]?.prop)
      
      ws[cellRef].s = {
        font: { bold: true, size: 10, color: { rgb: '000000' } },
        alignment: { horizontal: 'center', vertical: 'center' },
        fill: isTotal ? { fgColor: { rgb: 'E3F2FD' } } : { fgColor: { rgb: 'FFFFFF' } },
        border: {
          top: { style: 'thin', color: { rgb: '000000' } },
          bottom: { style: 'thin', color: { rgb: '000000' } },
          left: { style: 'thin', color: { rgb: '000000' } },
          right: { style: 'thin', color: { rgb: '000000' } }
        }
      }
    }
  }
}

/**
 * 获取模板列的值
 * @param {Object} row - 行数据
 * @param {Object} col - 列配置
 * @returns {String} 值
 */
function getTemplateValue(row, col) {
  if (col.prop === 'serial_number') {
    return row.serial_number || ''
  }
  
  if (col.prop === 'type') {
    return row.type || ''
  }
  
  if (col.prop === 'medical_base' || col.prop === 'social_security_base') {
    return row[col.prop] || '0.00'
  }
  
  // 处理动态列
  if (col.prop && col.prop.startsWith('company_')) {
    return row[col.prop] || '0.00'
  }
  
  if (col.prop && col.prop.startsWith('employee_')) {
    return row[col.prop] || '0.00'
  }
  
  return row[col.prop] || ''
}

/**
 * 导出数据到Excel（通用方法）
 * @param {Array} data - 数据
 * @param {String} title - 标题
 * @param {String} filename - 文件名
 * @param {Object} options - 选项
 */
export function exportToExcel(data, title, filename, options = {}) {
  const {
    sheetName = 'Sheet1',
    headers = [],
    skipTitleRow = false
  } = options
  
  const wb = XLSX.utils.book_new()
  const exportData = []
  
  // 添加标题行
  if (!skipTitleRow && title) {
    exportData.push([title])
    exportData.push([])
  }
  
  // 添加表头
  if (headers.length > 0) {
    exportData.push(headers)
  }
  
  // 添加数据行
  data.forEach(row => {
    if (row.isTitleRow) {
      return
    }
    
    const rowData = headers.map(header => {
      if (typeof header === 'string') {
        return row[header] || ''
      } else if (typeof header === 'object') {
        return row[header.key] || ''
      }
      return ''
    })
    exportData.push(rowData)
  })
  
  const ws = XLSX.utils.aoa_to_sheet(exportData)
  
  // 设置列宽
  if (headers.length > 0) {
    const colWidths = headers.map(() => ({ wch: 15 }))
    ws['!cols'] = colWidths
  }
  
  // 合并标题行
  if (!skipTitleRow && title && exportData.length > 0) {
    const titleRow = 0
    const titleCol = 0
    const endCol = headers.length - 1
    
    if (!ws['!merges']) {
      ws['!merges'] = []
    }
    
    ws['!merges'].push({
      s: { r: titleRow, c: titleCol },
      e: { r: titleRow, c: endCol }
    })
  }
  
  XLSX.utils.book_append_sheet(wb, ws, sheetName)
  XLSX.writeFile(wb, filename)
}
