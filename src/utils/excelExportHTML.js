/**
 * 统一的Excel表格样式
 */
const UNIFIED_EXCEL_STYLES = `
  body {
    font-family: "Microsoft YaHei", "微软雅黑", Arial, sans-serif;
    margin: 20px;
  }
  table {
    border-collapse: collapse;
    width: 100%;
    font-size: 14px;
  }
  th, td {
    border: 1px solid #000;
    padding: 12px;
    text-align: center;
    vertical-align: middle;
    min-width: 80px;
  }
  .title {
    text-align: center;
    font-size: 20px;
    font-weight: bold;
    color: #333;
    background-color: #E8F5E9;
    padding: 18px;
    border: 2px solid #000;
  }
  th {
    background-color: #E8F5E9;
    font-weight: bold;
    font-size: 14px;
    color: #333;
    height: 40px;
  }
  .header-row {
    background-color: #E8F5E9;
    font-weight: bold;
    font-size: 14px;
    color: #333;
    height: 40px;
  }
  .sub-header {
    background-color: #C8E6C9;
    font-weight: bold;
    font-size: 14px;
    color: #333;
    height: 40px;
  }
  .data-row {
    background-color: #FFFFFF;
    height: 35px;
  }
  .summary-row {
    background-color: #FFF9C4;
    font-weight: bold;
    font-size: 14px;
    height: 40px;
  }
  .total-row {
    background-color: #FFE082;
    font-weight: bold;
    font-size: 14px;
    height: 40px;
  }
  .total-cell {
    background-color: #F1F8E9;
    font-weight: bold;
  }
  .company-header {
    background-color: #C8E6C9;
    font-weight: bold;
    font-size: 14px;
    color: #333;
  }
  .employee-header {
    background-color: #C8E6C9;
    font-weight: bold;
    font-size: 14px;
    color: #333;
  }
`

/**
 * 使用HTML表格方式导出Excel（支持居中对齐）
 * @param {Array} data - 表格数据
 * @param {String} title - 标题
 * @param {Array} columns - 列配置
 * @param {String} filename - 文件名
 * @param {Array} dynamicColumns - 动态列配置
 */
export function exportSocialSecurityToExcelHTML(data, title, columns, filename, dynamicColumns = []) {
  // 构建HTML表格
  const htmlContent = buildHTMLTable(data, title, columns, dynamicColumns)
  
  // 创建Blob并下载
  const blob = new Blob([htmlContent], { 
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' 
  })
  
  // 创建下载链接
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  window.URL.revokeObjectURL(url)
}

/**
 * 构建HTML表格
 * @param {Array} data - 数据
 * @param {String} title - 标题
 * @param {Array} columns - 列配置
 * @param {Array} dynamicColumns - 动态列配置
 * @returns {String} HTML内容
 */
function buildHTMLTable(data, title, columns, dynamicColumns = []) {
  const html = `
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>${title}</title>
  <style>
    ${UNIFIED_EXCEL_STYLES}
  </style>
</head>
<body>
  <table style="width: 100%; margin-bottom: 20px;">
    <tr>
      <td class="title" colspan="${9 + dynamicColumns.length * 2 + 3}">${title}</td>
    </tr>
  </table>
  <table>
    ${buildTableHeaders(columns, dynamicColumns)}
    ${buildTableRowsFromDisplayData(data, columns, dynamicColumns)}
  </table>
</body>
</html>`
  
  return html
}

/**
 * 构建表头
 * @param {Array} columns - 列配置
 * @param {Array} dynamicColumns - 动态列配置
 * @returns {String} 表头HTML
 */
function buildTableHeaders(columns, dynamicColumns = []) {
  // 基础列（跨两行）
  const basicColumns = [
    '序号', '姓名', '身份证号', '项目', '参保日期', '类型', '费款所属期',
    '医保基数', '社保基数'
  ]

  let html = '<thead>'

  // 第一级表头
  html += '<tr class="header-row">'
  
  // 基础列（跨两行）
  basicColumns.forEach(col => {
    html += `<th rowspan="2">${col}</th>`
  })
  
  // 计算动态列数量
  const companyColumnsCount = dynamicColumns.length + 1 // +1 for 单位缴纳保险合计
  const employeeColumnsCount = dynamicColumns.length + 1 // +1 for 个人缴纳保险合计
  
  html += `<th colspan="${companyColumnsCount}" class="company-header">单位部分</th>`
  html += `<th colspan="${employeeColumnsCount}" class="employee-header">个人部分</th>`
  html += '<th rowspan="2">社保合计</th>'
  html += '<th rowspan="2">备注</th>'
  html += '</tr>'

  // 第二级表头
  html += '<tr class="header-row">'
  
  // 动态单位部分列
  dynamicColumns.forEach(col => {
    html += `<th>${col.name}</th>`
  })
  html += '<th>单位缴纳保险合计</th>'
  
  // 动态个人部分列
  dynamicColumns.forEach(col => {
    html += `<th>${col.name}</th>`
  })
  html += '<th>个人缴纳保险合计</th>'
  
  html += '</tr>'

  html += '</thead>'
  return html
}

/**
 * 构建数据行（直接使用页面显示的数据）
 * @param {Array} data - 数据
 * @param {Array} columns - 列配置
 * @param {Array} dynamicColumns - 动态列配置
 * @returns {String} 数据行HTML
 */
function buildTableRowsFromDisplayData(data, columns, dynamicColumns = []) {
  let html = '<tbody>'
  
  data.forEach((row, index) => {
    // 跳过标题行
    if (row.isTitleRow) {
      return
    }
    
    // 判断是否为小计行或合计行
    const isSummaryRow = row.isSummaryRow
    const isTotalRow = row.isTotalRow
    const rowClass = (isSummaryRow || isTotalRow) ? 'total-row' : 'data-row'
    
    html += `<tr class="${rowClass}">`
    
    // 如果是小计或合计行，合并前7列
    if (isSummaryRow || isTotalRow) {
      const displayText = isTotalRow ? '合计' : '小计'
      // 合并前7列（序号、姓名、身份证号、项目、参保日期、类型、费款所属期）
      html += `<td colspan="7" style="text-align: center; font-weight: bold;">${displayText}</td>`
      
      // 医保基数和社保基数
      html += `<td class="total-cell">${row.medical_base || '0.00'}</td>`
      html += `<td class="total-cell">${row.social_security_base || '0.00'}</td>`
      
      // 动态单位部分列
      dynamicColumns.forEach(col => {
        const prop = 'company_' + (col.fieldPrefix || '') + col.name
        let value = row[prop] || '0.00'
        html += `<td class="total-cell">${value}</td>`
      })
      
      // 单位缴纳保险合计
      html += `<td class="total-cell">${row.company_total || '0.00'}</td>`
      
      // 动态个人部分列
      dynamicColumns.forEach(col => {
        const prop = 'employee_' + (col.fieldPrefix || '') + col.name
        let value = row[prop] || '0.00'
        html += `<td class="total-cell">${value}</td>`
      })
      
      // 个人缴纳保险合计
      html += `<td class="total-cell">${row.employee_total || '0.00'}</td>`
      
      // 社保缴纳总计
      html += `<td class="total-cell">${row.social_security_total || '0.00'}</td>`
      
      // 备注
      html += `<td>${row.remarks || ''}</td>`
    } else {
      // 普通数据行
      // 基础列
      const basicColumns = [
        'serial_number', 'employee_name', 'id_number', 'project_name', 
        'enrollment_date', 'type', 'period', 'medical_base', 'social_security_base'
      ]
      
      basicColumns.forEach(prop => {
        let value = row[prop] || ''
        html += `<td>${value}</td>`
      })
      
      // 动态单位部分列
      dynamicColumns.forEach(col => {
        const prop = 'company_' + (col.fieldPrefix || '') + col.name
        let value = row[prop] || '0.00'
        html += `<td>${value}</td>`
      })
      
      // 单位缴纳保险合计
      let companyTotalValue = row.company_total || '0.00'
      html += `<td class="total-cell">${companyTotalValue}</td>`
      
      // 动态个人部分列
      dynamicColumns.forEach(col => {
        const prop = 'employee_' + (col.fieldPrefix || '') + col.name
        let value = row[prop] || '0.00'
        html += `<td>${value}</td>`
      })
      
      // 个人缴纳保险合计
      let employeeTotalValue = row.employee_total || '0.00'
      html += `<td class="total-cell">${employeeTotalValue}</td>`
      
      // 社保合计
      let socialTotalValue = row.social_security_total || '0.00'
      html += `<td class="total-cell">${socialTotalValue}</td>`
      
      // 备注
      let remarksValue = row.remarks || ''
      html += `<td>${remarksValue}</td>`
    }
    
    html += '</tr>'
  })
  
  html += '</tbody>'
  return html
}

/**
 * 构建数据行（旧方法，保留兼容性）
 * @param {Array} data - 数据
 * @param {Array} columns - 列配置
 * @returns {String} 数据行HTML
 */
function buildTableRows(data, columns) {
  return buildTableRowsFromDisplayData(data, columns)
}

/**
 * 构建合计行
 * @param {Array} data - 数据
 * @param {Array} columns - 列配置
 * @returns {String} 合计行HTML
 */
function buildTotalRow(data, columns) {
  const numericColumns = [
    'medical_base', 'social_security_base', 'company_medical_insurance',
    'company_pension_insurance', 'company_unemployment_insurance', 
    'company_work_injury_insurance', 'company_large_medical',
    'company_total', 'employee_medical_insurance', 'employee_pension_insurance',
    'employee_unemployment_insurance', 'employee_large_medical',
    'employee_total', 'social_security_total'
  ]
  
  let html = '<tfoot>'
  html += '<tr class="total-row">'
  
  columns.forEach((col, index) => {
    let value = ''
    
    if (index === 0) {
      value = '小计'
    } else if (index === 3) { // 项目列
      value = ''
    } else if (numericColumns.includes(col.prop)) {
      // 计算数值列的总和
      let sum = 0
      data.forEach(row => {
        if (!row.isTitleRow) {
          const val = parseFloat(row[col.prop]) || 0
          sum += val
        }
      })
      value = sum.toFixed(2)
    } else {
      value = ''
    }
    
    const isTotal = ['company_total', 'employee_total', 'social_security_total'].includes(col.prop)
    const cellClass = isTotal ? 'total-cell' : ''
    
    html += `<td class="${cellClass}">${value}</td>`
  })
  
  html += '</tr>'
  html += '</tfoot>'
  return html
}

/**
 * 通用Excel导出函数（简单表格，无动态列）
 * @param {Array} data - 表格数据
 * @param {String} title - 标题
 * @param {Array} columns - 列配置
 * @param {String} filename - 文件名
 */
export function exportToExcelHTML(data, title, columns, filename) {
  // 构建HTML表格
  const htmlContent = buildSimpleHTMLTable(data, title, columns)
  
  // 创建Blob并下载
  const blob = new Blob([htmlContent], { 
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' 
  })
  
  // 创建下载链接
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  window.URL.revokeObjectURL(url)
}

/**
 * 构建简单HTML表格（用于公积金等简单表格）
 * @param {Array} data - 数据
 * @param {String} title - 标题
 * @param {Array} columns - 列配置
 * @returns {String} HTML内容
 */
function buildSimpleHTMLTable(data, title, columns) {
  const html = `
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>${title}</title>
  <style>
    ${UNIFIED_EXCEL_STYLES}
  </style>
</head>
<body>
  <table style="width: 100%; margin-bottom: 20px;">
    <tr>
      <td class="title" colspan="${columns.length}">${title}</td>
    </tr>
  </table>
  <table>
    <thead>
      <tr>
        ${columns.map(col => `<th>${col.label}</th>`).join('')}
      </tr>
    </thead>
    <tbody>
      ${buildSimpleTableRows(data, columns)}
    </tbody>
  </table>
</body>
</html>`
  
  return html
}

/**
 * 构建简单表格的数据行
 * @param {Array} data - 数据
 * @param {Array} columns - 列配置
 * @returns {String} 数据行HTML
 */
function buildSimpleTableRows(data, columns) {
  let html = ''
  
  data.forEach((row, index) => {
    // 跳过标题行
    if (row.isTitleRow) {
      return
    }
    
    // 判断行类型
    const isSummaryRow = row.isSummaryRow
    const isTotalRow = row.isTotalRow
    const rowClass = isTotalRow ? 'total-row' : (isSummaryRow ? 'summary-row' : 'data-row')
    
    html += `<tr class="${rowClass}">`
    
    // 如果是小计或合计行，需要合并前面的空白列
    if (isSummaryRow || isTotalRow) {
      const displayText = isTotalRow ? '合计' : '小计'
      // 合并前7列（序号、姓名、身份证号、项目、参保日期、类型、费款所属期）
      html += `<td colspan="7" style="text-align: center; font-weight: bold;">${displayText}</td>`
      
      // 跳过前7列，从第8列开始输出
      columns.slice(7).forEach(col => {
        let value = row[col.prop] || ''
        html += `<td>${value}</td>`
      })
    } else {
      // 普通数据行，正常输出所有列
      columns.forEach(col => {
        let value = row[col.prop] || ''
        html += `<td>${value}</td>`
      })
    }
    
    html += '</tr>'
  })
  
  return html
}

/**
 * 导出公积金汇总表到Excel
 * @param {Array} data - 公积金明细数据
 * @param {String} title - 标题
 * @param {String} filename - 文件名
 */
export function exportHousingFundSummaryToExcel(data, title, filename) {
  const htmlContent = buildHousingFundSummaryHTML(data, title)
  
  const blob = new Blob([htmlContent], {
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
  })
  
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  window.URL.revokeObjectURL(url)
}

/**
 * 构建公积金汇总表HTML
 * @param {Array} data - 公积金明细数据
 * @param {String} title - 标题
 * @returns {String} HTML内容
 */
function buildHousingFundSummaryHTML(data, title) {
  // 按项目分组并计算汇总
  const projectSummary = {}
  
  data.forEach(item => {
    // 跳过标题行、小计行、合计行
    if (item.isTitleRow || item.isSummaryRow || item.isTotalRow) {
      return
    }
    
    const projectName = item.project_name || '未知项目'
    const employeeType = item.type || '正常' // 使用type字段判断是否为补交
    
    if (!projectSummary[projectName]) {
      projectSummary[projectName] = {
        normal: {
          count: 0,
          period: '',
          companyAmount: 0,
          employeeAmount: 0,
          companyTotal: 0,
          employeeTotal: 0,
          total: 0
        },
        supplementary: {
          count: 0,
          period: '',
          companyAmount: 0,
          employeeAmount: 0,
          companyTotal: 0,
          employeeTotal: 0,
          total: 0
        }
      }
    }
    
    const isSupplementary = employeeType === '补交'
    const category = isSupplementary ? 'supplementary' : 'normal'
    const summary = projectSummary[projectName][category]
    
    summary.count += 1
    summary.period = item.period || ''
    summary.companyAmount += parseFloat(item.company_portion || 0)
    summary.employeeAmount += parseFloat(item.employee_portion || 0)
    summary.companyTotal += parseFloat(item.company_portion || 0)
    summary.employeeTotal += parseFloat(item.employee_portion || 0)
    summary.total += parseFloat(item.housing_fund_total || 0)
  })
  
  // 构建HTML
  const html = `
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>${title}</title>
  <style>
    ${UNIFIED_EXCEL_STYLES}
  </style>
</head>
<body>
  <table>
    <tr>
      <td colspan="8" class="title">${title}</td>
    </tr>
    <tr class="header-row">
      <th rowspan="2">项目名称</th>
      <th rowspan="2">参保人数</th>
      <th rowspan="2">所属期</th>
      <th rowspan="2">基层部分</th>
      <th rowspan="2">个人部分</th>
      <th colspan="2">安家金额</th>
      <th rowspan="2">合计</th>
    </tr>
    <tr class="sub-header">
      <th>基层工资</th>
      <th>个人工资</th>
    </tr>
    ${buildHousingFundSummaryRows(projectSummary)}
  </table>
</body>
</html>`
  
  return html
}

/**
 * 构建公积金汇总表的数据行
 * @param {Object} projectSummary - 按项目分组的汇总数据
 * @returns {String} 数据行HTML
 */
function buildHousingFundSummaryRows(projectSummary) {
  let html = ''
  
  // 正常小计
  let normalSubtotal = {
    count: 0,
    period: '',
    companyAmount: 0,
    employeeAmount: 0,
    companyTotal: 0,
    employeeTotal: 0,
    total: 0
  }
  
  // 补交小计
  let supplementarySubtotal = {
    count: 0,
    period: '',
    companyAmount: 0,
    employeeAmount: 0,
    companyTotal: 0,
    employeeTotal: 0,
    total: 0
  }
  
  // 输出正常数据
  Object.keys(projectSummary).forEach(projectName => {
    const normal = projectSummary[projectName].normal
    if (normal.count > 0) {
      html += `
    <tr class="data-row">
      <td>${projectName}</td>
      <td>${normal.count}</td>
      <td>${normal.period}</td>
      <td>${normal.companyAmount.toFixed(2)}</td>
      <td>${normal.employeeAmount.toFixed(2)}</td>
      <td>${normal.companyTotal.toFixed(2)}</td>
      <td>${normal.employeeTotal.toFixed(2)}</td>
      <td>${normal.total.toFixed(2)}</td>
    </tr>`
      
      normalSubtotal.count += normal.count
      normalSubtotal.period = normal.period
      normalSubtotal.companyAmount += normal.companyAmount
      normalSubtotal.employeeAmount += normal.employeeAmount
      normalSubtotal.companyTotal += normal.companyTotal
      normalSubtotal.employeeTotal += normal.employeeTotal
      normalSubtotal.total += normal.total
    }
  })
  
  // 输出正常小计
  if (normalSubtotal.count > 0) {
    html += `
    <tr class="summary-row">
      <td>小计</td>
      <td>${normalSubtotal.count}</td>
      <td>${normalSubtotal.period}</td>
      <td>${normalSubtotal.companyAmount.toFixed(2)}</td>
      <td>${normalSubtotal.employeeAmount.toFixed(2)}</td>
      <td>${normalSubtotal.companyTotal.toFixed(2)}</td>
      <td>${normalSubtotal.employeeTotal.toFixed(2)}</td>
      <td>${normalSubtotal.total.toFixed(2)}</td>
    </tr>`
  }
  
  // 输出补交数据
  Object.keys(projectSummary).forEach(projectName => {
    const supplementary = projectSummary[projectName].supplementary
    if (supplementary.count > 0) {
      html += `
    <tr class="data-row">
      <td>${projectName}</td>
      <td>${supplementary.count}</td>
      <td>${supplementary.period}</td>
      <td>${supplementary.companyAmount.toFixed(2)}</td>
      <td>${supplementary.employeeAmount.toFixed(2)}</td>
      <td>${supplementary.companyTotal.toFixed(2)}</td>
      <td>${supplementary.employeeTotal.toFixed(2)}</td>
      <td>${supplementary.total.toFixed(2)}</td>
    </tr>`
      
      supplementarySubtotal.count += supplementary.count
      supplementarySubtotal.period = supplementary.period
      supplementarySubtotal.companyAmount += supplementary.companyAmount
      supplementarySubtotal.employeeAmount += supplementary.employeeAmount
      supplementarySubtotal.companyTotal += supplementary.companyTotal
      supplementarySubtotal.employeeTotal += supplementary.employeeTotal
      supplementarySubtotal.total += supplementary.total
    }
  })
  
  // 输出补交小计
  if (supplementarySubtotal.count > 0) {
    html += `
    <tr class="summary-row">
      <td>小计</td>
      <td>${supplementarySubtotal.count}</td>
      <td>${supplementarySubtotal.period}</td>
      <td>${supplementarySubtotal.companyAmount.toFixed(2)}</td>
      <td>${supplementarySubtotal.employeeAmount.toFixed(2)}</td>
      <td>${supplementarySubtotal.companyTotal.toFixed(2)}</td>
      <td>${supplementarySubtotal.employeeTotal.toFixed(2)}</td>
      <td>${supplementarySubtotal.total.toFixed(2)}</td>
    </tr>`
  }
  
  // 输出合计
  const grandTotal = {
    count: normalSubtotal.count + supplementarySubtotal.count,
    period: normalSubtotal.period || supplementarySubtotal.period,
    companyAmount: normalSubtotal.companyAmount + supplementarySubtotal.companyAmount,
    employeeAmount: normalSubtotal.employeeAmount + supplementarySubtotal.employeeAmount,
    companyTotal: normalSubtotal.companyTotal + supplementarySubtotal.companyTotal,
    employeeTotal: normalSubtotal.employeeTotal + supplementarySubtotal.employeeTotal,
    total: normalSubtotal.total + supplementarySubtotal.total
  }
  
  html += `
    <tr class="total-row">
      <td>合计</td>
      <td>${grandTotal.count}</td>
      <td>${grandTotal.period}</td>
      <td>${grandTotal.companyAmount.toFixed(2)}</td>
      <td>${grandTotal.employeeAmount.toFixed(2)}</td>
      <td>${grandTotal.companyTotal.toFixed(2)}</td>
      <td>${grandTotal.employeeTotal.toFixed(2)}</td>
      <td>${grandTotal.total.toFixed(2)}</td>
    </tr>`
  
  return html
}