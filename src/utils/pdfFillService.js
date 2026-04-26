import { PDFDocument } from 'pdf-lib'

/**
 * PDF数据填充服务
 * 使用pdf-lib和Canvas技术，类似签名拼接功能
 */
export class PdfFillService {
  /**
   * 填充PDF模板数据
   * @param {string} pdfUrl - PDF模板URL
   * @param {Object} employeeData - 员工数据
   * @param {Object} placeholderPositions - 占位符位置
   * @returns {Promise<Blob>} 填充后的PDF Blob
   */
  static async fillPdfTemplate(pdfUrl, employeeData, placeholderPositions) {
    try {
      console.log('📄 开始填充PDF模板...')
      console.log('PDF URL:', pdfUrl)
      console.log('员工数据:', employeeData)
      console.log('占位符位置:', placeholderPositions)
      
      // 1. 处理PDF URL，使用Vite代理避免CORS问题
      let processedUrl = pdfUrl
      if (pdfUrl.includes('localhost:8000')) {
        // 将完整URL转换为相对路径，使用Vite代理
        processedUrl = pdfUrl.replace('http://localhost:8000', '')
      }
      
      console.log('🔗 处理后的PDF URL:', processedUrl)
      
      // 2. 下载PDF模板
      const response = await fetch(processedUrl, {
        credentials: 'include',
        mode: 'cors'
      })
      
      if (!response.ok) {
        throw new Error(`下载PDF模板失败: HTTP ${response.status}`)
      }
      
      const pdfBytes = await response.arrayBuffer()
      console.log('✅ PDF模板下载成功')
      
      // 2. 使用pdf-lib加载PDF
      const pdfDoc = await PDFDocument.load(pdfBytes)
      const pages = pdfDoc.getPages()
      const firstPage = pages[0]
      const { width: pdfWidth, height: pdfHeight } = firstPage.getSize()
      
      console.log(`📏 PDF尺寸: ${pdfWidth} x ${pdfHeight}`)
      
      // 3. 填充每个字段（支持数组格式和对象格式）
      const positionsArray = Array.isArray(placeholderPositions) 
        ? placeholderPositions 
        : Object.entries(placeholderPositions).map(([type, pos]) => ({ type, ...pos }))
      
      // 字段映射（支持更多字段）
      const fieldMapping = {
        'name': employeeData.name,
        'id_number': employeeData.id_number,
        'id_card': employeeData.id_number,  // 兼容旧字段名
        'phone': employeeData.phone,
        'address': employeeData.address,
        'gender': employeeData.gender,
        'birth_date': employeeData.birth_date,
        'nationality': employeeData.nationality,
        'education': employeeData.education,
        'position': employeeData.position,
        'employee_number': employeeData.employee_number,
        'email': employeeData.email,
        'bank_name': employeeData.bank_name,
        'bank_account': employeeData.bank_account,
        'bank_account_holder': employeeData.bank_account_holder,
        'hire_date': employeeData.hire_date,
        'contract_sign_date': employeeData.contract_sign_date,
        'contract_start_date': employeeData.contract_start_date,
        'contract_end_date': employeeData.contract_end_date,
        'contract_start_year': employeeData.contract_start_year,
        'contract_start_month': employeeData.contract_start_month,
        'contract_start_day': employeeData.contract_start_day,
        'contract_end_year': employeeData.contract_end_year,
        'contract_end_month': employeeData.contract_end_month,
        'contract_end_day': employeeData.contract_end_day,
        'emergency_contact': employeeData.emergency_contact,
        'emergency_phone': employeeData.emergency_phone,
        'household_address': employeeData.household_address,
        'residence_address': employeeData.residence_address,
        'contact_address': employeeData.contact_address
      }
      
      // 前端渲染PDF时使用的缩放比例
      const renderScale = 1.5
      const namePosition = positionsArray.find((pos) => pos.type === 'name')
      const uniformFontSize = namePosition
        ? Math.max(Math.min(namePosition.height * 0.9, 20), 14)
        : 16
      const uniformFontFamily = 'SimSun'

      for (const position of positionsArray) {
        const fieldName = position.type
        const pageIndex = position.page || 0
        
        // 获取对应页面
        if (pageIndex >= pages.length) {
          console.warn(`⚠️ 页码 ${pageIndex} 超出范围，跳过`)
          continue
        }
        const targetPage = pages[pageIndex]
        const { height: pageHeight, width: pageWidth } = targetPage.getSize()
        
        // 获取字段值
        let fieldValue = employeeData[fieldName] || fieldMapping[fieldName]
        
        if (!fieldValue) {
          console.warn(`⚠️ 字段 ${fieldName} 没有数据，跳过`)
          console.log('可用的员工数据字段:', Object.keys(employeeData))
          continue
        }
        
        console.log(`📝 在第${pageIndex + 1}页填充字段 ${fieldName}: ${fieldValue}`)
        
        // 将前端像素坐标转换为PDF坐标（除以渲染缩放比例）
        const pdfX = position.x / renderScale
        const pdfY = position.y / renderScale

        // 根据实际值长度动态扩展绘制宽度，避免内容被占位框宽度截断
        const dynamicImageSize = this.calculateTextImageSize(fieldValue, {
          baseWidth: position.width,
          baseHeight: position.height,
          fontSize: uniformFontSize,
          fontFamily: uniformFontFamily
        })

        const pdfWidth = dynamicImageSize.width / renderScale
        const pdfHeight = dynamicImageSize.height / renderScale

        // 生成文字图片（优化字体大小和清晰度）
        const textImageBytes = await this.createTextImage(fieldValue, {
          width: dynamicImageSize.width,
          height: dynamicImageSize.height,
          fontSize: uniformFontSize,
          fontFamily: uniformFontFamily,
          color: '#000000'
        })
        
        // 嵌入图片到PDF
        const image = await pdfDoc.embedPng(textImageBytes)
        
        // 计算PDF坐标（PDF坐标系Y轴向上，原点在左下角）
        const x = pdfX
        const y = pageHeight - pdfY - pdfHeight
        
        console.log(`📍 页${pageIndex + 1} 原始位置: (${position.x}, ${position.y})`)
        console.log(`📍 页${pageIndex + 1} PDF位置: (${x}, ${y}), 尺寸: ${pdfWidth} x ${pdfHeight}`)
        
        targetPage.drawImage(image, {
          x: x,
          y: y,
          width: pdfWidth,
          height: pdfHeight
        })
      }
      
      // 4. 保存PDF
      const filledPdfBytes = await pdfDoc.save()
      const pdfBlob = new Blob([filledPdfBytes], { type: 'application/pdf' })
      
      console.log('✅ PDF数据填充完成')
      return pdfBlob
      
    } catch (error) {
      console.error('❌ PDF数据填充失败:', error)
      throw new Error(`PDF数据填充失败: ${error.message}`)
    }
  }
  
  /**
   * 根据文本内容计算绘制图片尺寸
   */
  static calculateTextImageSize(text, options = {}) {
    const {
      baseWidth = 100,
      baseHeight = 20,
      fontSize = 14,
      fontFamily = 'SimSun'
    } = options

    const canvas = document.createElement('canvas')
    const ctx = canvas.getContext('2d')
    const actualFontSize = Math.max(fontSize, 14)
    ctx.font = `${actualFontSize}px ${fontFamily}, "Microsoft YaHei", "SimSun", sans-serif`

    const textWidth = Math.ceil(ctx.measureText(String(text || '')).width)
    const horizontalPadding = 16
    const minWidth = Math.max(baseWidth, 32)
    const width = Math.max(minWidth, textWidth + horizontalPadding)

    return {
      width,
      height: Math.max(baseHeight, 16)
    }
  }

  /**
   * 创建透明背景的文字图片
   * @param {string} text - 文字内容
   * @param {Object} options - 选项
   * @returns {Promise<Uint8Array>} PNG图片字节数组
   */
  static async createTextImage(text, options = {}) {
    const {
      width = 200,
      height = 30,
      fontSize = 14,
      fontFamily = 'SimSun',
      color = '#000000',
      backgroundColor = 'transparent'
    } = options
    
    // 创建高分辨率Canvas（提高清晰度）
    const scale = 4 // 4倍分辨率，提高清晰度
    const canvas = document.createElement('canvas')
    const ctx = canvas.getContext('2d')

    // 设置Canvas尺寸（高分辨率）
    canvas.width = width * scale
    canvas.height = height * scale

    // 缩放上下文以保持清晰度
    ctx.scale(scale, scale)

    // 启用文字渲染优化
    ctx.textRenderingOptimization = 'optimizeQuality'
    ctx.imageSmoothingEnabled = true
    ctx.imageSmoothingQuality = 'high'

    // 设置背景（透明）
    if (backgroundColor !== 'transparent') {
      ctx.fillStyle = backgroundColor
      ctx.fillRect(0, 0, width, height)
    }

    // 设置文字样式（加粗以提高清晰度）
    const actualFontSize = Math.max(fontSize, 14)
    ctx.font = `bold ${actualFontSize}px ${fontFamily}, "Microsoft YaHei", "SimSun", sans-serif`
    ctx.fillStyle = color
    ctx.textAlign = 'left'

    // 启用文字抗锯齿
    ctx.fontKerning = 'normal'
    ctx.fontVariantCaps = 'normal'

    const textX = 8 // 增加左边距
    const useBottomLineAsBaseline = height > 16
    ctx.textBaseline = useBottomLineAsBaseline ? 'bottom' : 'middle'
    const textY = useBottomLineAsBaseline ? (height - 2) : (height / 2)

    // 绘制文字（清晰无阴影）
    ctx.fillText(text, textX, textY)
    
    // 转换为PNG字节数组
    return new Promise((resolve) => {
      canvas.toBlob((blob) => {
        const reader = new FileReader()
        reader.onload = () => {
          resolve(new Uint8Array(reader.result))
        }
        reader.readAsArrayBuffer(blob)
      }, 'image/png', 1.0) // 最高质量
    })
  }
  
  /**
   * 获取员工数据映射
   * @param {Object} employee - 员工对象
   * @returns {Object} 字段映射
   */
  static getEmployeeDataMapping(employee) {
    return {
      name: employee.name || '',
      id_card: employee.id_number || '',
      phone: employee.phone || '',
      address: employee.address || ''
    }
  }
}
