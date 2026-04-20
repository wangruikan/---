const { createApp } = Vue
const { PDFDocument } = PDFLib

// pdf.js 1.x不需要配置worker
// PDFJS是1.x版本的全局对象
if (typeof PDFJS !== 'undefined') {
    PDFJS.disableWorker = true
    console.log('使用pdf.js 1.x版本')
} else if (typeof pdfjsLib !== 'undefined') {
    pdfjsLib.GlobalWorkerOptions.workerSrc = false
    console.log('使用pdf.js 2.x+版本')
}

createApp({
    data() {
        return {
            loading: true,
            loadingText: '初始化中...',
            error: null,
            contractId: null,
            token: null,
            contract: null,
            
            // PDF相关
            pdfDoc: null,
            pdfBytes: null,
            currentPage: 1,
            totalPages: 0,
            pdfCanvas: null,
            pdfCtx: null,
            scale: 2.0,  // 提高分辨率，让文字更清晰
            
            // 签名相关
            showSignPopup: false,
            signCanvas: null,
            signCtx: null,
            isDrawing: false,
            lastX: 0,
            lastY: 0,
            signPosition: null, // {x_percent, y_percent, pageIndex}
        }
    },
    
    async mounted() {
        // 获取URL参数
        const urlParams = new URLSearchParams(window.location.search)
        this.contractId = urlParams.get('contractId')
        this.token = urlParams.get('token')
        
        if (!this.contractId || !this.token) {
            this.error = '参数错误'
            this.loading = false
            return
        }
        
        await this.loadContract()
    },
    
    methods: {
        // 加载合同
        async loadContract() {
            try {
                this.loadingText = '正在获取合同信息...'
                console.log('开始加载合同, ID:', this.contractId)
                
                // 动态获取API基础URL
                const baseUrl = window.location.origin.includes('localhost') 
                    ? 'http://localhost:8000' 
                    : window.location.origin.replace(':3000', ':8000')
                
                const response = await fetch(`${baseUrl}/api/mini/contracts/${this.contractId}`, {
                    headers: {
                        'Authorization': `Bearer ${this.token}`,
                        'Accept': 'application/json'
                    }
                })
                
                console.log('API响应状态:', response.status)
                
                const result = await response.json()
                console.log('API响应数据:', result)
                
                if (!result.success) {
                    throw new Error(result.message || '加载合同失败')
                }
                
                this.contract = result.data.contract
                console.log('合同数据:', this.contract)
                console.log('PDF文件URL:', this.contract.file_url)
                
                // 加载PDF
                this.loadingText = '正在加载PDF文档...'
                await this.loadPDF(this.contract.file_url)
                
            } catch (err) {
                console.error('加载合同失败:', err)
                this.error = '加载合同失败: ' + err.message + '\n\n请检查：\n1. 网络连接\n2. token是否有效\n3. 合同ID是否正确'
                this.loading = false
            }
        },
        
        // 重试
        retry() {
            this.loading = true
            this.error = null
            this.loadContract()
        },
        
        // 加载PDF
        async loadPDF(pdfUrl) {
            try {
                this.loadingText = '正在下载PDF文件...'
                console.log('开始下载PDF:', pdfUrl)
                
                // 下载PDF文件
                const response = await fetch(pdfUrl)
                console.log('PDF下载响应:', response.status, response.ok)
                
                if (!response.ok) {
                    throw new Error(`PDF下载失败: HTTP ${response.status}`)
                }
                
                this.pdfBytes = await response.arrayBuffer()
                console.log('PDF文件大小:', this.pdfBytes.byteLength, 'bytes')
                
                this.loadingText = '正在解析PDF文档...'
                
                // 兼容pdf.js 1.x和2.x版本
                const PDFJS_LIB = typeof PDFJS !== 'undefined' ? PDFJS : pdfjsLib
                console.log('使用的PDF库:', typeof PDFJS !== 'undefined' ? 'PDFJS 1.x' : 'pdfjsLib 2.x+')
                
                // 使用pdf.js加载PDF
                const loadingTask = PDFJS_LIB.getDocument({ data: this.pdfBytes })
                this.pdfDoc = await loadingTask.promise
                this.totalPages = this.pdfDoc.numPages
                
                console.log('PDF解析成功，共', this.totalPages, '页')
                
                this.loadingText = '正在渲染PDF页面...'
                
                // 先隐藏loading，让Vue渲染Canvas元素
                this.loading = false
                
                // 等待DOM更新完成
                await this.$nextTick()
                await new Promise(resolve => setTimeout(resolve, 100))
                
                // 初始化canvas
                this.pdfCanvas = document.getElementById('pdf-canvas')
                if (!this.pdfCanvas) {
                    throw new Error('Canvas元素未找到，请刷新重试')
                }
                
                console.log('Canvas元素找到，开始渲染')
                this.pdfCtx = this.pdfCanvas.getContext('2d')
                await this.renderPage(1)
                
                console.log('PDF渲染完成')
                
            } catch (err) {
                console.error('加载PDF失败:', err)
                console.error('错误详情:', err.stack)
                this.error = 'PDF加载失败: ' + err.message + '\n\n' + (err.stack || '')
                this.loading = false
            }
        },
        
        // 渲染PDF页面
        async renderPage(pageNum) {
            try {
                console.log('开始渲染第', pageNum, '页')
                
                const page = await this.pdfDoc.getPage(pageNum)
                console.log('页面对象获取成功')
                console.log('页面view:', page.view)
                
                // 从page.view直接计算尺寸（最可靠）
                // page.view格式: [x1, y1, x2, y2]
                const pageWidth = page.view[2] - page.view[0]  // 595
                const pageHeight = page.view[3] - page.view[1]  // 842
                
                console.log('PDF页面原始尺寸:', pageWidth, 'x', pageHeight)
                
                // 计算适应屏幕的缩放比例
                const screenWidth = window.innerWidth
                const scale = screenWidth / pageWidth
                
                // 获取设备像素比（视网膜屏会是2或3）
                const dpr = window.devicePixelRatio || 1
                console.log('设备像素比:', dpr)
                console.log('计算缩放比例:', scale)
                
                // 设置Canvas物理尺寸（考虑设备像素比，提高清晰度）
                this.pdfCanvas.width = pageWidth * scale * dpr
                this.pdfCanvas.height = pageHeight * scale * dpr
                
                // 设置Canvas显示尺寸（CSS）
                this.pdfCanvas.style.width = (pageWidth * scale) + 'px'
                this.pdfCanvas.style.height = (pageHeight * scale) + 'px'
                
                // 缩放绘图上下文以匹配设备像素比
                this.pdfCtx.scale(dpr, dpr)
                
                console.log('Canvas物理尺寸:', this.pdfCanvas.width, 'x', this.pdfCanvas.height)
                console.log('Canvas显示尺寸:', this.pdfCanvas.style.width, 'x', this.pdfCanvas.style.height)
                
                // 手动创建viewport对象
                // PDF坐标系原点在左下角，Y轴向上
                // Canvas坐标系原点在左上角，Y轴向下
                // transform: [scaleX, skewY, skewX, scaleY, translateX, translateY]
                const viewport = {
                    width: pageWidth * scale,
                    height: pageHeight * scale,
                    scale: scale,
                    rotation: 0,
                    offsetX: 0,
                    offsetY: 0,
                    transform: [scale, 0, 0, -scale, 0, pageHeight * scale],  // Y轴翻转
                    viewBox: page.view
                }
                
                console.log('最终Canvas尺寸:', this.pdfCanvas.width, 'x', this.pdfCanvas.height)
                
                // 清空canvas（使用白色背景）
                this.pdfCtx.fillStyle = '#ffffff'
                this.pdfCtx.fillRect(0, 0, this.pdfCanvas.width, this.pdfCanvas.height)
                
                // 渲染PDF到canvas
                const renderContext = {
                    canvasContext: this.pdfCtx,
                    viewport: viewport
                }
                
                console.log('开始调用page.render，viewport transform:', viewport.transform)
                const renderTask = page.render(renderContext)
                await renderTask.promise
                
                console.log('页面渲染完成:', pageNum)
                
            } catch (err) {
                console.error('渲染页面失败:', err)
                console.error('错误堆栈:', err.stack)
                throw err
            }
        },
        
        // 上一页
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--
                this.renderPage(this.currentPage)
            }
        },
        
        // 下一页
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++
                this.renderPage(this.currentPage)
            }
        },
        
        // 点击Canvas选择签名位置
        handleCanvasClick(event) {
            const rect = this.pdfCanvas.getBoundingClientRect()
            const clickX = event.clientX - rect.left
            const clickY = event.clientY - rect.top
            
            console.log('点击坐标:', { clickX, clickY })
            console.log('Canvas显示尺寸:', rect.width, 'x', rect.height)
            console.log('Canvas实际尺寸:', this.pdfCanvas.width, 'x', this.pdfCanvas.height)
            
            // 重要：使用Canvas的显示尺寸（rect），而不是实际尺寸
            // 因为Canvas物理尺寸可能是显示尺寸的2-3倍（设备像素比）
            const xPercent = (clickX / rect.width) * 100
            const yPercent = (clickY / rect.height) * 100
            
            console.log('百分比坐标:', { xPercent, yPercent })
            
            this.signPosition = {
                x_percent: xPercent.toFixed(4),
                y_percent: yPercent.toFixed(4),
                pageIndex: this.currentPage - 1
            }
            
            console.log('保存的签名位置:', this.signPosition)
            
            // 打开签名弹窗
            this.showSignPopup = true
            
            // 初始化签名canvas
            this.$nextTick(() => {
                this.initSignCanvas()
            })
        },
        
        // 初始化签名Canvas
        initSignCanvas() {
            this.signCanvas = document.getElementById('sign-canvas')
            this.signCtx = this.signCanvas.getContext('2d')
            
            // 设置canvas尺寸
            const rect = this.signCanvas.getBoundingClientRect()
            this.signCanvas.width = rect.width
            this.signCanvas.height = rect.height
            
            // 设置绘图样式
            this.signCtx.strokeStyle = '#000'
            this.signCtx.lineWidth = 3
            this.signCtx.lineCap = 'round'
            this.signCtx.lineJoin = 'round'
            
            // 使用透明背景（不遮挡PDF内容）
            this.signCtx.clearRect(0, 0, this.signCanvas.width, this.signCanvas.height)
            console.log('签名Canvas初始化完成（透明背景）')
            
            // 绑定事件
            this.signCanvas.addEventListener('mousedown', this.startDrawing.bind(this))
            this.signCanvas.addEventListener('mousemove', this.draw.bind(this))
            this.signCanvas.addEventListener('mouseup', this.stopDrawing.bind(this))
            this.signCanvas.addEventListener('mouseleave', this.stopDrawing.bind(this))
            
            // 触摸事件（移动端）
            this.signCanvas.addEventListener('touchstart', this.handleTouchStart.bind(this))
            this.signCanvas.addEventListener('touchmove', this.handleTouchMove.bind(this))
            this.signCanvas.addEventListener('touchend', this.stopDrawing.bind(this))
        },
        
        // 开始绘制
        startDrawing(e) {
            this.isDrawing = true
            const pos = this.getMousePos(e)
            this.lastX = pos.x
            this.lastY = pos.y
        },
        
        // 绘制
        draw(e) {
            if (!this.isDrawing) return
            
            e.preventDefault()
            const pos = this.getMousePos(e)
            
            this.signCtx.beginPath()
            this.signCtx.moveTo(this.lastX, this.lastY)
            this.signCtx.lineTo(pos.x, pos.y)
            this.signCtx.stroke()
            
            this.lastX = pos.x
            this.lastY = pos.y
        },
        
        // 停止绘制
        stopDrawing() {
            this.isDrawing = false
        },
        
        // 触摸开始
        handleTouchStart(e) {
            e.preventDefault()
            const touch = e.touches[0]
            this.isDrawing = true
            const pos = this.getTouchPos(touch)
            this.lastX = pos.x
            this.lastY = pos.y
        },
        
        // 触摸移动
        handleTouchMove(e) {
            if (!this.isDrawing) return
            
            e.preventDefault()
            const touch = e.touches[0]
            const pos = this.getTouchPos(touch)
            
            this.signCtx.beginPath()
            this.signCtx.moveTo(this.lastX, this.lastY)
            this.signCtx.lineTo(pos.x, pos.y)
            this.signCtx.stroke()
            
            this.lastX = pos.x
            this.lastY = pos.y
        },
        
        // 获取鼠标位置
        getMousePos(e) {
            const rect = this.signCanvas.getBoundingClientRect()
            return {
                x: e.clientX - rect.left,
                y: e.clientY - rect.top
            }
        },
        
        // 获取触摸位置
        getTouchPos(touch) {
            const rect = this.signCanvas.getBoundingClientRect()
            return {
                x: touch.clientX - rect.left,
                y: touch.clientY - rect.top
            }
        },
        
        // 清空签名
        clearSignature() {
            this.signCtx.clearRect(0, 0, this.signCanvas.width, this.signCanvas.height)
            console.log('签名已清空')
        },
        
        // 确认签名
        async confirmSignature() {
            // 获取签名图片base64
            const signatureDataUrl = this.signCanvas.toDataURL('image/png')
            
            // 弹出身份验证
            const idLast4 = prompt('请输入您的身份证后4位以确认签署:')
            
            if (!idLast4 || idLast4.length !== 4) {
                alert('请输入正确的身份证后4位')
                return
            }
            
            // 提交签署
            await this.submitSignature(idLast4, signatureDataUrl)
        },
        
        // 提交签署（合成PDF并上传）
        async submitSignature(idLast4, signatureDataUrl) {
            try {
                // 显示处理中
                if (confirm('正在合成签名到PDF，需要10-30秒，请耐心等待...')) {
                    
                    // 1. 使用pdf-lib合成PDF
                    const signedPdfBytes = await this.mergePDFWithSignature(signatureDataUrl)
                    
                    // 2. 上传已签署的PDF
                    await this.uploadSignedPDF(idLast4, signedPdfBytes, signatureDataUrl)
                }
                
            } catch (err) {
                console.error('签署处理失败:', err)
                alert('签署失败: ' + err.message)
            }
        },
        
        // 合成PDF和签名
        async mergePDFWithSignature(signatureDataUrl) {
            try {
                console.log('开始合成PDF...')
                console.log('原始PDF大小:', this.pdfBytes.byteLength, 'bytes')
                console.log('签名图片:', signatureDataUrl.substring(0, 50) + '...')
                
                // 加载原PDF
                console.log('步骤1: 加载原PDF')
                const pdfDoc = await PDFDocument.load(this.pdfBytes)
                console.log('PDF加载成功')
                
                const pages = pdfDoc.getPages()
                console.log('页面总数:', pages.length)
                
                const page = pages[this.signPosition.pageIndex]
                console.log('选择页面:', this.signPosition.pageIndex)
                
                // 嵌入签名图片
                console.log('步骤2: 嵌入签名图片')
                const signatureImage = await pdfDoc.embedPng(signatureDataUrl)
                console.log('签名图片嵌入成功')
                
                const signatureDims = signatureImage.scale(0.25) // 缩小签名（调整为0.25更合适）
                console.log('签名尺寸:', signatureDims)
                
                // 获取页面尺寸
                const { width, height } = page.getSize()
                console.log('页面尺寸:', width, 'x', height)
                
                // 根据百分比计算实际坐标（用户点击的位置）
                const clickX = (parseFloat(this.signPosition.x_percent) / 100) * width
                const clickY = height - (parseFloat(this.signPosition.y_percent) / 100) * height
                
                // 将签名图片居中在点击位置（点击位置作为签名中心）
                const actualX = clickX - (signatureDims.width / 2)
                const actualY = clickY - (signatureDims.height / 2)
                
                console.log('签名位置计算:', {
                    percent: this.signPosition,
                    click: { x: clickX, y: clickY },
                    centered: { x: actualX, y: actualY }
                })
                
                // 在指定位置添加签名（居中对齐）
                console.log('步骤3: 添加签名到PDF（居中对齐）')
                page.drawImage(signatureImage, {
                    x: actualX,
                    y: actualY,
                    width: signatureDims.width,
                    height: signatureDims.height,
                })
                console.log('签名图片添加成功')
                
                // 添加签署时间（只用数字，不用中文）
                console.log('步骤4: 添加签署时间')
                const now = new Date()
                const signTime = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')} ${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}`
                
                page.drawText(signTime, {
                    x: actualX,
                    y: actualY - signatureDims.height - 12,
                    size: 8,
                    color: PDFLib.rgb(0, 0, 0),
                })
                console.log('时间添加成功:', signTime)
                
                // 保存PDF
                console.log('步骤5: 保存PDF')
                const pdfBytes = await pdfDoc.save()
                console.log('PDF合成完成，大小:', pdfBytes.length, 'bytes')
                
                return pdfBytes
                
            } catch (err) {
                console.error('PDF合成失败:', err)
                console.error('错误名称:', err.name)
                console.error('错误消息:', err.message)
                console.error('错误堆栈:', err.stack)
                throw new Error('PDF合成失败: ' + err.message)
            }
        },
        
        // 上传已签署的PDF
        async uploadSignedPDF(idLast4, pdfBytes, signatureDataUrl) {
            try {
                // 创建FormData
                const formData = new FormData()
                
                // 将PDF bytes转为Blob
                const pdfBlob = new Blob([pdfBytes], { type: 'application/pdf' })
                formData.append('signed_pdf', pdfBlob, 'signed_contract.pdf')
                formData.append('id_last_4', idLast4)
                formData.append('signature_image', signatureDataUrl)
                formData.append('sign_x_percent', this.signPosition.x_percent)
                formData.append('sign_y_percent', this.signPosition.y_percent)
                formData.append('page_index', this.signPosition.pageIndex)
                
                console.log('开始上传已签署的PDF...')
                
                // 动态获取API基础URL
                const baseUrl = window.location.origin.includes('localhost') 
                    ? 'http://localhost:8000' 
                    : window.location.origin.replace(':3000', ':8000')
                
                const response = await fetch(`${baseUrl}/api/mini/contracts/${this.contractId}/sign`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${this.token}`,
                    },
                    body: formData
                })
                
                const result = await response.json()
                
                if (result.success) {
                    alert('签署成功！')
                    
                    // 返回小程序
                    if (typeof wx !== 'undefined' && wx.miniProgram) {
                        wx.miniProgram.navigateBack()
                    } else {
                        window.history.back()
                    }
                } else {
                    throw new Error(result.message || '提交失败')
                }
                
            } catch (err) {
                console.error('上传失败:', err)
                throw err
            }
        },
        
        // 关闭签名弹窗
        closeSignPopup() {
            this.showSignPopup = false
        }
    }
}).mount('#app')
