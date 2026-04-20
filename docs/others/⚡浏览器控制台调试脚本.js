// ========================================
// 浏览器控制台调试脚本
// ========================================
// 使用方法：
// 1. 打开工资汇总页面
// 2. 按F12打开浏览器开发者工具
// 3. 切换到"Console"（控制台）标签
// 4. 复制下面的代码粘贴到控制台并回车
// 5. 查看输出结果，把结果发给我
// ========================================

console.log('========== 开始调试工资汇总NaN问题 ==========');

// 方法1：从Vue实例中获取数据
const vueApp = document.querySelector('#app').__vueParentComponent;
if (vueApp) {
  console.log('✅ 找到Vue实例');
  
  // 尝试获取tableData
  const findTableData = (component) => {
    if (component?.ctx?.tableData) {
      return component.ctx.tableData;
    }
    if (component?.subTree?.component) {
      return findTableData(component.subTree.component);
    }
    return null;
  };
  
  const tableData = findTableData(vueApp);
  if (tableData && tableData.length > 0) {
    console.log('✅ 找到表格数据，共', tableData.length, '条记录');
    console.log('📋 第一条记录完整数据：', tableData[0]);
    
    const firstRow = tableData[0];
    console.log('');
    console.log('========== 关键字段检查 ==========');
    console.log('total_pension_personal:', firstRow.total_pension_personal, '类型:', typeof firstRow.total_pension_personal);
    console.log('total_medical_personal:', firstRow.total_medical_personal, '类型:', typeof firstRow.total_medical_personal);
    console.log('total_unemployment_personal:', firstRow.total_unemployment_personal, '类型:', typeof firstRow.total_unemployment_personal);
    console.log('total_large_medical_personal:', firstRow.total_large_medical_personal, '类型:', typeof firstRow.total_large_medical_personal);
    
    console.log('');
    console.log('========== 计算测试 ==========');
    const pension = firstRow.total_pension_personal || 0;
    const medical = firstRow.total_medical_personal || 0;
    const unemployment = firstRow.total_unemployment_personal || 0;
    console.log('养老 || 0 =', pension, '类型:', typeof pension);
    console.log('医疗 || 0 =', medical, '类型:', typeof medical);
    console.log('失业 || 0 =', unemployment, '类型:', typeof unemployment);
    
    const sum = pension + medical + unemployment;
    console.log('三者相加 =', sum, '类型:', typeof sum);
    console.log('Number(sum) =', Number(sum));
    console.log('Number(sum).toLocaleString(...) =', Number(sum).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
  } else {
    console.log('❌ 未找到表格数据');
  }
} else {
  console.log('❌ 未找到Vue实例');
}

console.log('');
console.log('========================================');
console.log('如果上面的方法没有输出数据，请尝试下面的方法：');
console.log('手动获取：打开Network标签，刷新页面，找到salary-summaries的请求');
console.log('点击该请求，查看Response，把返回的JSON数据复制给我');
console.log('========================================');

