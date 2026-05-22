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
            noticeFiles: [],
            
            // PDF相关
            pdfDoc: null,
            pdfBytes: null,
            currentPage: 1,
            totalPages: 0,
            pdfCanvas: null,
            pdfCtx: null,
            renderTask: null, // 记录渲染任务
            isRendering: false,
            scale: 2.0,

            // 签名相关
            showSignPopup: false,
            showForceConfirm: true,
            signCanvas: null,
            signCtx: null,
            isDrawing: false,
            lastX: 0,
            lastY: 0,
            signPosition: null, // {x_percent, y_percent, pageIndex}
            previousCompany: '',
            idLast4Input: '',
        }
    },
    
    async mounted() {
        // 获取URL参数
        const urlParams = new URLSearchParams(window.location.search)
        this.contractId = urlParams.get('contractId')
        this.token = urlParams.get('token')
        const previousCompanyParam = urlParams.get('previous_company')
        if (previousCompanyParam) {
            this.previousCompany = String(previousCompanyParam).trim()
        }
        
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
                console.log('寮€濮嬪姞杞藉悎鍚? ID:', this.contractId)
                
                // 鍔ㄦ€佽幏鍙朅PI鍩虹URL锛堢洿鎺ヤ娇鐢ㄥ綋鍓嶅煙鍚嶏級
                const baseUrl = window.location.origin
                
                const apiUrl = `${baseUrl}/api/mini/contracts/${this.contractId}?token=${encodeURIComponent(this.token)}`
                
                const response = await fetch(apiUrl, {
                    headers: {
                        'Authorization': `Bearer ${this.token}`,
                        'X-Auth-Token': this.token,
                        'Accept': 'application/json'
                    }
                })
                
                console.log('API鍝嶅簲鐘舵€?', response.status)
                
                const result = await response.json()
                console.log('API响应数据:', result)
                
                if (!result.success) {
                    throw new Error(result.message || '加载合同失败')
                }
                
                this.contract = result.data.contract
                const noticeFiles = Array.isArray(result.data.notice_files) ? result.data.notice_files : []
                this.noticeFiles = noticeFiles.length > 0
                    ? noticeFiles
                    : (result.data.notice_file ? [result.data.notice_file] : [])
                console.log('合同数据:', this.contract)
                console.log('须知文件:', this.noticeFiles)
                console.log('PDF文件URL:', this.contract.file_url)
                
                // 杈撳嚭绛惧悕鍗犱綅绗︿綅缃?                console.log('========== 绛惧悕鍗犱綅绗︿綅缃?==========')
                const positions = this.contract.signature_positions || []
                console.log('signature_positions:', positions)
                console.log('位置数量:', positions.length)
                if (positions.length > 0) {
                    positions.forEach((pos, i) => {
                        console.log(`浣嶇疆${i+1}: 椤?${pos.page}, x=${pos.x}, y=${pos.y}, 瀹?${pos.width}, 楂?${pos.height}`)
                    })
                } else {
                    console.warn('鈿狅笍 娌℃湁棰勮绛惧悕浣嶇疆!')
                }
                console.log('=====================================')
                
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
        
        getSignaturePlaceholderPositions() {
            const positions = Array.isArray(this.contract?.signature_positions)
                ? this.contract.signature_positions
                : []

            // 鍏煎鍘嗗彶鏁版嵁锛氳€佹暟鎹病鏈?type 瀛楁锛岄粯璁よ涓虹鍚嶅崰浣嶇
            return positions.filter(pos => !pos?.type || pos.type === 'employee_signature')
        },

        getPreviousCompanyPlaceholderPositions() {
            const positions = Array.isArray(this.contract?.signature_positions)
                ? this.contract.signature_positions
                : []

            return positions.filter(pos => pos?.type === 'previous_company')
        },

        requiresPreviousCompany() {
            return this.getPreviousCompanyPlaceholderPositions().length > 0
        },

        normalizeIdLast4(value) {
            return String(value || '')
                .replace(/\u3000/g, ' ')
                .replace(/\s+/g, '')
                .toUpperCase()
        },

        // 与PC端一致的字体安全字号算法
        calculateSafeFontSize(boxHeight, preferredSize = 14) {
            const safeHeight = Math.max(Number(boxHeight) || 20, 14)
            const maxByHeight = Math.max(12, Math.floor(safeHeight * 0.92))
            const basePreferred = Math.max(Number(preferredSize) || 14, 12)
            const normalizedPreferred = Math.ceil(basePreferred * 1.35)
            return Math.min(normalizedPreferred, maxByHeight)
        },

        async createTextImageDataUrl(text, options = {}) {
            const width = options.width || 320
            const height = options.height || 42
            const preferredFontSize = options.fontSize || 20
            const fontSize = this.calculateSafeFontSize(height, preferredFontSize)
            const fontFamily = options.fontFamily || 'SimSun'

            const canvas = document.createElement('canvas')
            canvas.width = width
            canvas.height = height
            const ctx = canvas.getContext('2d')

            ctx.clearRect(0, 0, width, height)
            ctx.fillStyle = '#000000'
            ctx.font = `700 ${fontSize}px ${fontFamily}, "Microsoft YaHei", "SimSun", sans-serif`
            ctx.textAlign = 'left'
            ctx.textBaseline = 'middle'
            ctx.fillText(String(text || ''), 4, height / 2)

            return canvas.toDataURL('image/png')
        },

        async mergeTextValueToPDF(pdfDoc, text, placeholderPositions = []) {
            if (!text || !String(text).trim() || placeholderPositions.length === 0) {
                return
            }

            const pages = pdfDoc.getPages()
            const pcScale = 1.5

            for (const pos of placeholderPositions) {
                const pageIndex = Number.isInteger(Number(pos.page)) ? Number(pos.page) : 0
                if (pageIndex < 0 || pageIndex >= pages.length) {
                    continue
                }

                const page = pages[pageIndex]
                const { width: pageWidth, height: pageHeight } = page.getSize()
                const hasPercentX = pos.x_percent !== undefined && pos.x_percent !== null && pos.x_percent !== ''
                const hasPercentY = pos.y_percent !== undefined && pos.y_percent !== null && pos.y_percent !== ''
                const usePercent = hasPercentX && hasPercentY
                const rawWidth = Number(pos.width || 180)
                const rawHeight = Number(pos.height || 24)
                const placeholderWidth = rawWidth / (usePercent ? 1 : pcScale)
                const placeholderHeight = rawHeight / (usePercent ? 1 : pcScale)
                const x = usePercent
                    ? (parseFloat(pos.x_percent || 0) / 100) * pageWidth
                    : (Number(pos.x || 0) / pcScale)
                const yFromTop = usePercent
                    ? (parseFloat(pos.y_percent || 0) / 100) * pageHeight
                    : (Number(pos.y || 0) / pcScale)
                const y = pageHeight - yFromTop - placeholderHeight

                const textImageDataUrl = await this.createTextImageDataUrl(String(text).trim(), {
                    width: Math.max(rawWidth, 80),
                    height: Math.max(rawHeight, 20),
                    fontSize: this.calculateSafeFontSize(rawHeight, 20),
                    fontFamily: 'SimSun'
                })
                const textImage = await pdfDoc.embedPng(textImageDataUrl)
                const scale = Math.min(
                    placeholderWidth / textImage.width,
                    placeholderHeight / textImage.height
                )

                page.drawImage(textImage, {
                    x,
                    y,
                    width: textImage.width * scale,
                    height: textImage.height * scale
                })
            }
        },

        // 加载PDF
        async loadPDF(pdfUrl) {
            try {
                this.loadingText = '正在下载PDF文件...'
                console.log('========== PDF 加载信息 ==========')
                console.log('📄 PDF文件URL:', pdfUrl)
                console.log('🌐 当前页面域名:', window.location.origin)
                console.log('==================================')
                
                // 下载PDF文件
                const response = await fetch(pdfUrl)
                console.log('PDF涓嬭浇鍝嶅簲鐘舵€?', response.status, response.ok)
                console.log('PDF响应Content-Type:', response.headers.get('content-type'))
                
                if (!response.ok) {
                    // 灏濊瘯璇诲彇閿欒鍐呭
                    const errorText = await response.text()
                    console.error('PDF下载失败，响应内容:', errorText.substring(0, 500))
                    throw new Error(`PDF下载失败: HTTP ${response.status}`)
                }
                
                this.pdfBytes = await response.arrayBuffer()
                console.log('PDF文件大小:', this.pdfBytes.byteLength, 'bytes')
                
                this.loadingText = '正在解析PDF文档...'
                
                // 鍏煎pdf.js 1.x鍜?.x鐗堟湰
                const PDFJS_LIB = typeof PDFJS !== 'undefined' ? PDFJS : pdfjsLib
                console.log('浣跨敤鐨凱DF搴?', typeof PDFJS !== 'undefined' ? 'PDFJS 1.x' : 'pdfjsLib 2.x+')
                
                // 使用pdf.js加载PDF
                const loadingTask = PDFJS_LIB.getDocument({ data: this.pdfBytes })
                this.pdfDoc = await loadingTask.promise
                this.totalPages = this.pdfDoc.numPages
                
                console.log('PDF parsed successfully, total pages:', this.totalPages)
                
                this.loadingText = '正在渲染PDF页面...'
                
                // 鍏堥殣钘弆oading锛岃Vue娓叉煋Canvas鍏冪礌
                this.loading = false

                // 等待DOM更新完成
                await this.$nextTick()

                // 棣栨鍔犺浇澧炲姞杈冮暱寤惰繜锛岀‘淇濈Щ鍔ㄧ甯冨眬褰诲簳绋冲畾
                setTimeout(async () => {
                    this.pdfCanvas = document.getElementById('pdf-canvas')
                    if (this.pdfCanvas) {
                        console.log('Initial PDF render start')
                        await this.renderPage(1)

                        // 寮€鍚鍣ㄥ昂瀵哥洃鍚紝濡傛灉瀹藉害鍙樹簡璇存槑甯冨眬绋充簡锛岃嚜鍔ㄩ噸缁?                        this.initResizeObserver()
                    }
                }, 400)

                console.log('PDF娓叉煋鍒濆鍖栨寚浠ゅ凡鍙戝嚭')
                
            } catch (err) {
                console.error('加载PDF失败:', err)
                console.error('错误详情:', err.stack)
                this.error = 'PDF加载失败: ' + err.message + '\n\n' + (err.stack || '')
                this.loading = false
            }
        },
        
        // 渲染PDF页面
        async renderPage(pageNum) {
            if (this.isRendering) return // 闃叉骞跺彂娓叉煋
            this.isRendering = true

            try {
                console.log('Start rendering page', pageNum)

                const page = await this.pdfDoc.getPage(pageNum)
                // ... 涔嬪墠鐨勬覆鏌撻€昏緫 ...
                const viewport10 = page.getViewport(1.0)
                const pageWidth = viewport10.width
                const pageHeight = viewport10.height

                const container = this.pdfCanvas.parentElement
                const screenWidth = container ? container.clientWidth : (document.documentElement.clientWidth || window.innerWidth)
                const scale = screenWidth / pageWidth

                const dpr = Math.min(window.devicePixelRatio || 1, 2.5)
                const viewport = page.getViewport(scale)

                this.pdfCanvas.width = viewport.width * dpr
                this.pdfCanvas.height = viewport.height * dpr
                this.pdfCanvas.style.width = viewport.width + 'px'
                this.pdfCanvas.style.height = viewport.height + 'px'

                this.pdfCtx = this.pdfCanvas.getContext('2d')
                this.pdfCtx.setTransform(1, 0, 0, 1, 0, 0)
                this.pdfCtx.scale(dpr, dpr)

                this.pdfCtx.fillStyle = '#ffffff'
                this.pdfCtx.fillRect(0, 0, viewport.width, viewport.height)

                const renderContext = {
                    canvasContext: this.pdfCtx,
                    viewport: viewport
                }

                this.renderTask = page.render(renderContext)
                await this.renderTask.promise

                console.log('页面渲染完成:', pageNum)
                this.drawSignaturePlaceholders(pageNum, pageWidth, pageHeight, scale)

            } catch (err) {
                console.error('渲染页面失败:', err)
            } finally {
                this.isRendering = false
            }
        },

        initResizeObserver() {
            if (typeof ResizeObserver === 'undefined') return
            // ...
        },

        async confirmAndRender() {
            this.showForceConfirm = false
            await this.manualRefresh()
        },

        async manualRefresh() {
            if (this.isRendering || this.totalPages <= 1) {
                await this.renderPage(this.currentPage)
                return
            }

            console.log('鎵ц妯℃嫙缈婚〉鍒锋柊...')

            try {
                // 1. 缈诲埌涓嬩竴椤碉紙濡傛灉鏄渶鍚庝竴椤靛垯缈诲埌涓婁竴椤碉級
                const isLastPage = this.currentPage === this.totalPages
                if (!isLastPage) {
                    this.currentPage++
                } else {
                    this.currentPage--
                }
                await this.renderPage(this.currentPage)

                // 2. 极短延迟后翻回来
                await new Promise(resolve => setTimeout(resolve, 100))

                if (!isLastPage) {
                    this.currentPage--
                } else {
                    this.currentPage++
                }
                await this.renderPage(this.currentPage)

                console.log('模拟翻页刷新完成')
            } catch (err) {
                console.error('模拟翻页失败:', err)
                await this.renderPage(this.currentPage)
            }
        },
        
        drawSignaturePlaceholders(pageNum, pageWidth, pageHeight, scale) {
            const positions = this.getSignaturePlaceholderPositions()
            if (positions.length === 0) {
                console.log('No signature placeholder on this page')
                return
            }
            
            // 绛涢€夊綋鍓嶉〉鐨勭鍚嶄綅缃紙椤电爜浠?寮€濮嬶級
            const pagePositions = positions.filter(pos => (pos.page || 0) === pageNum - 1)
            console.log(`第${pageNum}页的签名位置:`, pagePositions)
            
            if (pagePositions.length === 0) {
                console.log(`第${pageNum}页没有签名位置`)
                return
            }
            
            // 淇濆瓨褰撳墠缁樺浘鐘舵€?            this.pdfCtx.save()
            
            const dpr = window.devicePixelRatio || 1
            
            pagePositions.forEach((pos, index) => {
                // PC绔娇鐢╯cale=1.5娓叉煋棰勮鍥撅紝淇濆瓨鐨勫潗鏍囨槸鏀惧ぇ鍚庣殑
                // 闇€瑕佸厛杞崲鍥濸DF鍘熷鍧愭爣锛屽啀鏍规嵁褰撳墠scale缁樺埗
                const pcScale = 1.5  // PC绔瑙堝浘鐨勭缉鏀炬瘮渚?                
                // 杞崲鍥濸DF鍘熷鍧愭爣
                const pdfX = pos.x / pcScale
                const pdfY = pos.y / pcScale
                const pdfWidth = (pos.width || 150) / pcScale
                const pdfHeight = (pos.height || 50) / pcScale
                
                // 鍐嶆牴鎹綋鍓嶉〉闈㈢殑缂╂斁姣斾緥璁＄畻Canvas鍧愭爣
                const x = pdfX * scale
                const y = pdfY * scale
                const width = pdfWidth * scale
                const height = pdfHeight * scale
                
                // 璋冭瘯锛氳緭鍑鸿缁嗕俊鎭?                console.log(`鍘熷浣嶇疆${index + 1}: x=${pos.x}, y=${pos.y}, page=${pos.page}`)
                console.log(`PDF鍘熷鍧愭爣: (${pdfX.toFixed(0)}, ${pdfY.toFixed(0)})`)
                console.log(`PDF椤甸潰灏哄: ${pageWidth} x ${pageHeight}`)
                console.log(`缁樺埗浣嶇疆${index + 1}: Canvas(${x.toFixed(0)}, ${y.toFixed(0)}), 灏哄: ${width.toFixed(0)}x${height.toFixed(0)}`)
                
                // 缁樺埗绾㈣壊铏氱嚎杈规
                this.pdfCtx.strokeStyle = '#ff0000'
                this.pdfCtx.lineWidth = 3
                this.pdfCtx.setLineDash([8, 4])
                this.pdfCtx.strokeRect(x, y, width, height)
                
                // 缁樺埗鍗婇€忔槑绾㈣壊鑳屾櫙
                this.pdfCtx.fillStyle = 'rgba(255, 0, 0, 0.15)'
                this.pdfCtx.fillRect(x, y, width, height)
                
                // 缁樺埗浣嶇疆鏍囩
                this.pdfCtx.setLineDash([])
                this.pdfCtx.fillStyle = '#ff0000'
                this.pdfCtx.font = `bold ${14 * scale}px Arial`
                this.pdfCtx.fillText(`签名${index + 1}`, x + 5, y + 20 * scale)
            })
            
            // 鎭㈠缁樺浘鐘舵€?            this.pdfCtx.restore()
            
            console.log(`已绘制 ${pagePositions.length} 个签名占位框`)
        },
        
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--
                this.renderPage(this.currentPage)
            }
        },
        
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++
                this.renderPage(this.currentPage)
            }
        },
        
        handleCanvasClick(event) {
            // 不再用于选择签名位置，点击无效果
            console.log('鐐瑰嚮PDF棰勮')
        },
        
        openSignPopup() {
            const positions = this.getSignaturePlaceholderPositions()
            if (positions.length === 0) {
                alert('错误：没有预设签名位置，请在PC端先配置签名占位符。')
                return
            }
            
            console.log('鎵撳紑绛惧悕寮圭獥锛岄璁句綅缃暟閲?', positions.length)
            this.showSignPopup = true
            
            // 鍒濆鍖栫鍚峜anvas
            this.$nextTick(() => {
                this.initSignCanvas()
            })
        },
        
        // 鍒濆鍖栫鍚岰anvas
        initSignCanvas() {
            this.signCanvas = document.getElementById('sign-canvas')
            this.signCtx = this.signCanvas.getContext('2d')
            
            // 璁剧疆canvas灏哄
            const rect = this.signCanvas.getBoundingClientRect()
            this.signCanvas.width = rect.width
            this.signCanvas.height = rect.height
            
            // 设置绘图样式
            this.signCtx.strokeStyle = '#000'
            this.signCtx.lineWidth = 5
            this.signCtx.lineCap = 'round'
            this.signCtx.lineJoin = 'round'
            
            // 使用透明背景（不遮挡PDF内容）
            this.signCtx.clearRect(0, 0, this.signCanvas.width, this.signCanvas.height)
            console.log('Signature canvas initialized')
            
            // 绑定事件
            this.signCanvas.addEventListener('mousedown', this.startDrawing.bind(this))
            this.signCanvas.addEventListener('mousemove', this.draw.bind(this))
            this.signCanvas.addEventListener('mouseup', this.stopDrawing.bind(this))
            this.signCanvas.addEventListener('mouseleave', this.stopDrawing.bind(this))
            
            // 触摸事件（移动端）
            this.signCanvas.addEventListener('touchstart', this.handleTouchStart.bind(this))
            this.signCanvas.addEventListener('touchmove', this.handleTouchMove.bind(this))
            this.signCanvas.addEventListener('touchend', this.stopDrawing.bind(this))
            this.signCanvas.addEventListener('touchcancel', this.stopDrawing.bind(this))
        },
        
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
        
        // 鍋滄缁樺埗
        stopDrawing() {
            this.isDrawing = false
        },
        
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
            console.log('Signature canvas cleared')
        },
        
        // 纭绛惧悕
        async confirmSignature() {
            const idLast4 = this.normalizeIdLast4(this.idLast4Input)
            if (!/^[0-9X]{4}$/.test(idLast4)) {
                alert('请输入正确的身份证后4位（支持数字和X）')
                return
            }

            // 获取签名图片base64
            const signatureDataUrl = this.signCanvas.toDataURL('image/png')

            // 提交签署
            await this.submitSignature(idLast4, signatureDataUrl)
        },
        
        // 提交签署（合成PDF并上传）
        async submitSignature(idLast4, signatureDataUrl) {
            try {
                if (confirm('正在合成签名到PDF，可能需要10-30秒，请耐心等待...')) {
                    
                    // 1. 使用pdf-lib合成PDF
                    const signedPdfBytes = await this.mergePDFWithSignature(signatureDataUrl)
                    
                    // 2. 鍚堟垚椤荤煡鏂囦欢绛惧悕鍓湰
                    const noticeSignedPdfs = await this.mergeNoticePDFsWithSignature(signatureDataUrl)

                    // 3. 涓婁紶宸茬缃茬殑PDF
                    await this.uploadSignedPDF(idLast4, signedPdfBytes, signatureDataUrl, noticeSignedPdfs)
                }
                
            } catch (err) {
                console.error('签署处理失败:', err)
                alert('签署失败: ' + err.message)
            }
        },
        
        // 鍚堟垚PDF鍜岀鍚嶏紙鑷姩鍚堟垚鍒版墍鏈夐璁句綅缃級
        async mergePDFWithSignature(signatureDataUrl) {
            try {
                console.log('寮€濮嬪悎鎴怭DF...')
                console.log('鍘熷PDF澶у皬:', this.pdfBytes.byteLength, 'bytes')
                
                const positions = this.getSignaturePlaceholderPositions()
                console.log('棰勮绛惧悕浣嶇疆鏁伴噺:', positions.length)
                
                if (positions.length === 0) {
                    throw new Error('没有预设签名位置')
                }
                
                // 加载原PDF
                console.log('姝ラ1: 鍔犺浇鍘烶DF')
                const pdfDoc = await PDFDocument.load(this.pdfBytes)
                console.log('PDF加载成功')
                
                const pages = pdfDoc.getPages()
                console.log('页面总数:', pages.length)
                
                // 嵌入签名图片
                console.log('姝ラ2: 宓屽叆绛惧悕鍥剧墖')
                const signatureImage = await pdfDoc.embedPng(signatureDataUrl)
                console.log('签名图片嵌入成功')
                
                const pcScale = 1.5
                
                // 閬嶅巻鎵€鏈夐璁句綅缃紝娣诲姞绛惧悕
                console.log('Step 3: merge signature into all preset positions')
                for (let i = 0; i < positions.length; i++) {
                    const pos = positions[i]
                    const pageIndex = pos.page || 0
                    
                    if (pageIndex >= pages.length) {
                        console.warn(`位置${i + 1}页码 ${pageIndex} 超出范围，已跳过`)
                        continue
                    }
                    
                    const page = pages[pageIndex]
                    const { width: pageWidth, height: pageHeight } = page.getSize()
                    
                    // 灏哖C绔繚瀛樼殑鍧愭爣杞崲涓篜DF鍘熷鍧愭爣
                    const pdfX = pos.x / pcScale
                    const pdfY = pos.y / pcScale
                    const placeholderWidth = (pos.width || 150) / pcScale
                    const placeholderHeight = (pos.height || 50) / pcScale
                    
                    // 璁＄畻绛惧悕鍦≒DF涓殑浣嶇疆锛圥DF鍧愭爣绯诲師鐐瑰湪宸︿笅瑙掞級
                    const actualX = pdfX
                    const actualY = pageHeight - pdfY - placeholderHeight
                    
                    const signScale = Math.min(
                        placeholderWidth / signatureImage.width,
                        placeholderHeight / signatureImage.height
                    ) * 1.8  // 直接放大到原始基线的2倍（0.9 * 2 = 1.8）
                    const signWidth = signatureImage.width * signScale
                    const signHeight = signatureImage.height * signScale
                    
                    // 绛惧悕灞呬腑浜庡崰浣嶇
                    const centeredX = actualX + (placeholderWidth - signWidth) / 2
                    const centeredY = actualY + (placeholderHeight - signHeight) / 2
                    
                    console.log(`浣嶇疆${i + 1}: 椤?${pageIndex}, PDF鍧愭爣=(${actualX.toFixed(0)}, ${actualY.toFixed(0)}), 绛惧悕灏哄=${signWidth.toFixed(0)}x${signHeight.toFixed(0)}`)
                    
                    // 添加签名到PDF
                    page.drawImage(signatureImage, {
                        x: centeredX,
                        y: centeredY,
                        width: signWidth,
                        height: signHeight,
                    })
                    
                    console.log(`位置${i + 1}签名添加成功`)
                }
                
                const previousCompanyPositions = this.getPreviousCompanyPlaceholderPositions()
                if (this.requiresPreviousCompany() && previousCompanyPositions.length > 0) {
                    console.log('姝ラ4: 鍚堟垚涓婁釜鍏徃瀛楁')
                    await this.mergeTextValueToPDF(pdfDoc, this.previousCompany, previousCompanyPositions)
                }

                // 保存PDF
                console.log('姝ラ5: 淇濆瓨PDF')
                const pdfBytes = await pdfDoc.save()
                console.log('PDF鍚堟垚瀹屾垚锛屽ぇ灏?', pdfBytes.length, 'bytes')
                
                return pdfBytes
                
            } catch (err) {
                console.error('PDF合成失败:', err)
                console.error('错误堆栈:', err.stack)
                throw new Error('PDF合成失败: ' + err.message)
            }
        },
        
        async mergeNoticePDFsWithSignature(signatureDataUrl) {
            const noticeFiles = this.noticeFiles || []
            if (noticeFiles.length === 0) {
                console.log('No notice files need merge')
                return []
            }

            const signedNotices = []
            for (let i = 0; i < noticeFiles.length; i++) {
                const noticeFile = noticeFiles[i] || {}
                if (!noticeFile.view_url) {
                    console.warn(`须知文件${i + 1}缺少访问地址，跳过`, noticeFile)
                    continue
                }

                try {
                    console.log(`开始合成须知文件${i + 1}:`, noticeFile.name || noticeFile.view_url)
                    const response = await fetch(noticeFile.view_url)
                    if (!response.ok) {
                        throw new Error(`下载失败: HTTP ${response.status}`)
                    }

                    const noticePdfBytes = await response.arrayBuffer()
                    const signedBytes = await this.mergePDFBytesWithSignature(
                        noticePdfBytes,
                        signatureDataUrl,
                        noticeFile.signature_positions || [],
                        true
                    )

                    signedNotices.push({
                        id: noticeFile.id || null,
                        name: noticeFile.name || `须知签名副本_${i + 1}.pdf`,
                        bytes: signedBytes
                    })
                    console.log(`须知文件${i + 1}合成完成`)
                } catch (err) {
                    console.error(`须知文件${i + 1}合成失败:`, err)
                    throw new Error(`须知文件合成失败: ${noticeFile.name || ''} ${err.message}`)
                }
            }

            return signedNotices
        },

        // 鎸夊崰浣嶇鎶婄鍚嶅悎鎴愬埌鎸囧畾PDF瀛楄妭
        async mergePDFBytesWithSignature(pdfBytes, signatureDataUrl, positions, useDefaultPosition = false) {
            const pdfDoc = await PDFDocument.load(pdfBytes)
            const pages = pdfDoc.getPages()
            const signatureImage = await pdfDoc.embedPng(signatureDataUrl)
            const pcScale = 1.5
            let signaturePositions = Array.isArray(positions)
                ? positions.filter(pos => !pos.type || pos.type === 'employee_signature')
                : []

            if (signaturePositions.length === 0 && useDefaultPosition && pages.length > 0) {
                signaturePositions = [{
                    page: pages.length - 1,
                    x_percent: 70,
                    y_percent: 85,
                    width: 150,
                    height: 50
                }]
            }

            if (signaturePositions.length === 0) {
                    throw new Error('没有预设签名位置')
            }

            for (let i = 0; i < signaturePositions.length; i++) {
                const pos = signaturePositions[i]
                const pageIndex = Number.isInteger(Number(pos.page)) ? Number(pos.page) : 0
                if (pageIndex >= pages.length) {
                    console.warn(`签名位置${i + 1}页码超出范围，跳过`, pos)
                    continue
                }

                const page = pages[pageIndex]
                const { width: pageWidth, height: pageHeight } = page.getSize()
                const placeholderWidth = (pos.width || 150) / (pos.x_percent !== undefined ? 1 : pcScale)
                const placeholderHeight = (pos.height || 50) / (pos.y_percent !== undefined ? 1 : pcScale)
                const pdfX = pos.x_percent !== undefined
                    ? (parseFloat(pos.x_percent) / 100) * pageWidth
                    : (Number(pos.x || 0) / pcScale)
                const pdfYFromTop = pos.y_percent !== undefined
                    ? (parseFloat(pos.y_percent) / 100) * pageHeight
                    : (Number(pos.y || 0) / pcScale)
                const actualY = pageHeight - pdfYFromTop - placeholderHeight
                const signScale = Math.min(
                    placeholderWidth / signatureImage.width,
                    placeholderHeight / signatureImage.height
                ) * 1.8
                const signWidth = signatureImage.width * signScale
                const signHeight = signatureImage.height * signScale
                const centeredX = pdfX + (placeholderWidth - signWidth) / 2
                const centeredY = actualY + (placeholderHeight - signHeight) / 2

                page.drawImage(signatureImage, {
                    x: centeredX,
                    y: centeredY,
                    width: signWidth,
                    height: signHeight,
                })
            }

            return await pdfDoc.save()
        },

        // 涓婁紶宸茬缃茬殑PDF
        async uploadSignedPDF(idLast4, pdfBytes, signatureDataUrl, noticeSignedPdfs = []) {
            try {
                // 创建FormData
                const formData = new FormData()
                
                // 灏哖DF bytes杞负Blob
                const pdfBlob = new Blob([pdfBytes], { type: 'application/pdf' })
                formData.append('signed_pdf', pdfBlob, 'signed_contract.pdf')
                formData.append('id_last_4', idLast4)
                formData.append('signature_image', signatureDataUrl)
                if (this.requiresPreviousCompany()) {
                    formData.append('previous_company', String(this.previousCompany || '').trim())
                }
                noticeSignedPdfs.forEach((notice, index) => {
                    const noticeBlob = new Blob([notice.bytes], { type: 'application/pdf' })
                    formData.append('notice_signed_pdfs[]', noticeBlob, `signed_notice_${index + 1}.pdf`)
                    formData.append('notice_signed_file_ids[]', notice.id || '')
                    formData.append('notice_signed_file_names[]', notice.name || `须知签名副本_${index + 1}.pdf`)
                })
                
                console.log('寮€濮嬩笂浼犲凡绛剧讲鐨凱DF...', {
                    noticeSignedCount: noticeSignedPdfs.length
                })
                
                // 鍔ㄦ€佽幏鍙朅PI鍩虹URL锛堢洿鎺ヤ娇鐢ㄥ綋鍓嶅煙鍚嶏級
                const baseUrl = window.location.origin
                const requestId = `${Date.now()}_${Math.random().toString(36).slice(2, 8)}`
                
                const apiUrl = `${baseUrl}/api/mini/contracts/${this.contractId}/sign?token=${encodeURIComponent(this.token)}&_rid=${encodeURIComponent(requestId)}`
                console.log('[SIGN_API_REQUEST]', {
                    requestId,
                    apiUrl,
                    contractId: this.contractId,
                    idLast4
                })
                
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${this.token}`,
                        'X-Auth-Token': this.token,
                    },
                    body: formData
                })

                const rawText = await response.text()
                let result = null
                try {
                    result = rawText ? JSON.parse(rawText) : null
                } catch (parseError) {
                    console.error('[SIGN_API_PARSE_ERROR]', parseError, rawText)
                }

                console.log('[SIGN_API_RESPONSE]', {
                    requestId,
                    status: response.status,
                    ok: response.ok,
                    result
                })

                if (response.ok && result && result.success) {
                    alert('签署成功！')
                    
                    if (typeof wx !== 'undefined' && wx.miniProgram) {
                        wx.miniProgram.navigateBack()
                    } else {
                        window.history.back()
                    }
                } else {
                    const serverMessage = (result && result.message)
                        ? result.message
                        : (rawText || `HTTP ${response.status}`)
                    throw new Error(`后端已返回(${response.status})：${serverMessage}`)
                }
                
            } catch (err) {
                console.error('上传失败:', err)
                throw err
            }
        },
        
        // 关闭签名弹窗
        closeSignPopup() {
            this.showSignPopup = false
            this.idLast4Input = ''
        }
    }
}).mount('#app')
