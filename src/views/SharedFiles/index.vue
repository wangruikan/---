<template>
  <div class="shared-files-page">
    <div class="page-header">
      <h1>共享中心</h1>
      <div class="header-actions">
        <el-button v-if="viewMode === 'tree'" type="success" @click="handleShowCreateFolder">
          <el-icon><FolderAdd /></el-icon>
          新建文件夹
        </el-button>
        <el-button type="primary" @click="showUploadDialog = true">
          <el-icon><Upload /></el-icon>
          上传文件
        </el-button>
      </div>
    </div>
    
    <!-- 标签页切换 -->
    <el-tabs v-model="activeTab" @tab-click="handleTabClick" class="file-tabs">
      <el-tab-pane label="共享文件" name="shared" />
      <el-tab-pane label="须知文件" name="notice" />
    </el-tabs>
    
    <!-- 搜索和筛选 -->
    <div class="search-section">
      <el-card>
        <el-form :model="searchForm" inline>
          <el-form-item label="文件名">
            <el-input
              v-model="searchForm.name"
              placeholder="请输入文件名"
              clearable
              @keyup.enter="handleSearch"
            />
          </el-form-item>
          
          <el-form-item v-if="activeTab === 'shared'" label="文件类型">
            <el-select
              v-model="searchForm.type"
              placeholder="请选择文件类型"
              clearable
            >
              <el-option label="文档" value="document" />
              <el-option label="图片" value="image" />
              <el-option label="视频" value="video" />
              <el-option label="其他" value="other" />
            </el-select>
          </el-form-item>
          
          <el-form-item>
            <el-button type="primary" @click="handleSearch">
              <el-icon><Search /></el-icon>
              搜索
            </el-button>
            <el-button @click="handleReset">
              <el-icon><Refresh /></el-icon>
              重置
            </el-button>
          </el-form-item>
        </el-form>
      </el-card>
    </div>
    
    <!-- 文件列表 -->
    <div class="table-section">
      <el-card>
        <!-- 视图切换按钮 -->
        <div class="view-toggle" style="margin-bottom: 15px;">
          <el-radio-group v-model="viewMode" size="small">
            <el-radio-button label="tree">
              <el-icon><FolderOpened /></el-icon> 文件夹视图
            </el-radio-button>
            <el-radio-button label="list">
              <el-icon><List /></el-icon> 列表视图
            </el-radio-button>
          </el-radio-group>
        </div>

        <!-- 树形视图 -->
        <div v-if="viewMode === 'tree'" class="tree-view">
          <el-tree
            :data="treeData"
            :props="treeProps"
            node-key="id"
            default-expand-all
            :expand-on-click-node="false"
            v-loading="loading"
          >
            <template #default="{ node, data }">
              <div class="tree-node">
                <div class="node-content">
                  <el-icon v-if="data.isFolder" class="folder-icon">
                    <Folder v-if="!node.expanded" />
                    <FolderOpened v-else />
                  </el-icon>
                  <el-icon v-else class="file-icon">
                    <Document />
                  </el-icon>
                  <span class="node-label">{{ data.label }}</span>
                  <span v-if="!data.isFolder" class="file-info">
                    <el-tag v-if="activeTab === 'shared'" size="small" :type="getTypeTagType(data.type)">
                      {{ getTypeText(data.type) }}
                    </el-tag>
                    <span class="file-size">{{ formatFileSize(data.size) }}</span>
                    <span class="file-uploader">{{ data.uploader_name }}</span>
                    <span class="file-time">{{ formatDateTime(data.created_at) }}</span>
                  </span>
                </div>
                <div class="node-actions">
                  <template v-if="data.isFolder">
                    <el-button type="primary" size="small" @click.stop="handleUploadToFolder(data)">
                      <el-icon><Upload /></el-icon>
                      上传
                    </el-button>
                    <el-button type="danger" size="small" @click.stop="handleDeleteFolder(data)">
                      删除文件夹
                    </el-button>
                  </template>
                  <template v-else>
                    <el-button type="success" size="small" @click.stop="handleView(data)">
                      查看
                    </el-button>
                    <el-button type="primary" size="small" @click.stop="handleDownload(data)">
                      下载
                    </el-button>
                    <el-button type="warning" size="small" @click.stop="handleEdit(data)">
                      编辑
                    </el-button>
                    <el-button type="danger" size="small" @click.stop="handleDelete(data)">
                      删除
                    </el-button>
                  </template>
                </div>
              </div>
            </template>
          </el-tree>
        </div>

        <!-- 列表视图 -->
        <el-table
          v-else
          :data="files"
          v-loading="loading"
          stripe
          border
        >
          <el-table-column prop="name" label="文件名" min-width="200" />
          <el-table-column v-if="activeTab === 'shared'" prop="type" label="类型" width="100">
            <template #default="{ row }">
              <el-tag :type="getTypeTagType(row.type)">
                {{ getTypeText(row.type) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="size" label="大小" width="120">
            <template #default="{ row }">
              {{ formatFileSize(row.size) }}
            </template>
          </el-table-column>
          <el-table-column prop="uploader_name" label="上传者" width="120" />
          <el-table-column prop="created_at" label="上传时间" width="160">
            <template #default="{ row }">
              {{ formatDateTime(row.created_at) }}
            </template>
          </el-table-column>
          <el-table-column label="操作" width="260" fixed="right">
            <template #default="{ row }">
              <el-button type="success" size="small" @click="handleView(row)">
                查看
              </el-button>
              <el-button type="primary" size="small" @click="handleDownload(row)">
                下载
              </el-button>
              <el-button type="warning" size="small" @click="handleEdit(row)">
                编辑
              </el-button>
              <el-button type="danger" size="small" @click="handleDelete(row)">
                删除
              </el-button>
            </template>
          </el-table-column>
        </el-table>
        
        <!-- 分页 -->
        <div v-if="viewMode === 'list'" class="pagination">
          <el-pagination
            v-model:current-page="pagination.currentPage"
            v-model:page-size="pagination.pageSize"
            :page-sizes="[10, 20, 50, 100]"
            :total="pagination.total"
            layout="total, sizes, prev, pager, next, jumper"
            @size-change="handleSizeChange"
            @current-change="handleCurrentChange"
          />
        </div>
      </el-card>
    </div>
    
    <!-- 上传对话框 -->
    <el-dialog
      v-model="showUploadDialog"
      title="上传文件"
      width="600px"
      @close="handleUploadDialogClose"
    >
      <el-form :model="uploadForm" label-width="100px">
        <el-form-item label="文件分类">
          <el-radio-group v-model="uploadForm.file_category">
            <el-radio label="shared">共享文件</el-radio>
            <el-radio label="notice">须知文件</el-radio>
          </el-radio-group>
          <div style="margin-top: 10px; color: #909399; font-size: 13px;">
            💡 须知文件：用于员工签订劳动合同前阅读的文件
          </div>
        </el-form-item>
        <el-form-item v-if="uploadForm.folderPath" label="上传到">
          <el-tag type="info">{{ uploadForm.folderPath }}</el-tag>
        </el-form-item>
      </el-form>
      
      <el-upload
        ref="uploadRef"
        :file-list="fileList"
        :auto-upload="false"
        :on-change="handleFileChange"
        :on-remove="handleFileRemove"
        multiple
        drag
      >
        <el-icon class="el-icon--upload"><upload-filled /></el-icon>
        <div class="el-upload__text">
          将文件拖到此处，或<em>点击上传</em>
        </div>
        <template #tip>
          <div class="el-upload__tip">
            <span v-if="uploadForm.file_category === 'notice'" style="color: #E6A23C;">
              ⚠️ 须知文件只能上传PDF格式，且不超过10MB
            </span>
            <span v-else>
              只能上传jpg/png/pdf/doc/docx文件，且不超过10MB
            </span>
          </div>
        </template>
      </el-upload>
      
      <template #footer>
        <el-button @click="showUploadDialog = false">取消</el-button>
        <el-button type="primary" @click="handleUpload" :loading="uploading">
          上传
        </el-button>
      </template>
    </el-dialog>

    <!-- 编辑对话框 -->
    <el-dialog
      v-model="showEditDialog"
      title="编辑文件信息"
      width="500px"
    >
      <el-form :model="editForm" label-width="100px">
        <el-form-item label="文件名">
          <el-input v-model="editForm.name" placeholder="请输入文件名" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input
            v-model="editForm.description"
            type="textarea"
            :rows="3"
            placeholder="请输入文件描述（可选）"
          />
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showEditDialog = false">取消</el-button>
        <el-button type="primary" @click="handleEditSubmit">
          保存
        </el-button>
      </template>
    </el-dialog>

    <!-- 新建文件夹对话框 -->
    <el-dialog
      v-model="showCreateFolderDialog"
      title="新建文件夹"
      width="500px"
    >
      <el-form :model="folderForm" label-width="100px">
        <el-form-item label="文件夹名称">
          <el-input 
            v-model="folderForm.name" 
            placeholder="请输入文件夹名称"
            @keyup.enter="handleCreateFolder"
          />
        </el-form-item>
        <el-form-item label="父级文件夹">
          <el-select 
            v-model="folderForm.parentPath" 
            placeholder="请选择父级文件夹"
            clearable
            filterable
            style="width: 100%"
            @change="handleFolderChange"
          >
            <el-option
              v-for="folder in folderOptions"
              :key="folder.value"
              :label="folder.label"
              :value="folder.value"
            >
              <span style="float: left">
                <el-icon v-if="folder.value === '__ROOT__'"><HomeFilled /></el-icon>
                <el-icon v-else><Folder /></el-icon>
              </span>
              <span style="margin-left: 8px">{{ folder.label }}</span>
            </el-option>
          </el-select>
          <div style="margin-top: 8px; color: #909399; font-size: 13px;">
            💡 提示：选择"根目录"将在最顶层创建文件夹
          </div>
          <div v-if="folderForm.parentPath !== undefined" style="margin-top: 8px; color: #67C23A; font-size: 13px;">
            ✓ 已选择: {{ folderForm.parentPath === '__ROOT__' ? '根目录' : folderForm.parentPath }}
          </div>
        </el-form-item>
      </el-form>
      
      <template #footer>
        <el-button @click="showCreateFolderDialog = false">取消</el-button>
        <el-button type="primary" @click="handleCreateFolder">
          创建
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getSharedFiles,
  uploadFile,
  updateSharedFile,
  deleteSharedFile,
  downloadFile
} from '@/api/sharedFiles'

const loading = ref(false)
const uploading = ref(false)
const showUploadDialog = ref(false)
const showEditDialog = ref(false)
const showCreateFolderDialog = ref(false)
const uploadRef = ref()
const fileList = ref([])
const activeTab = ref('shared') // 当前标签页
const viewMode = ref('tree') // 视图模式：tree 或 list
const treeData = ref([]) // 树形数据
const currentFolder = ref('') // 当前选中的文件夹路径
const folderOptions = ref([]) // 文件夹选项列表

// 树形组件配置
const treeProps = {
  children: 'children',
  label: 'label'
}

const folderForm = reactive({
  name: '',
  parentPath: '' // 默认为根目录
})

const editForm = reactive({
  id: null,
  name: '',
  description: ''
})

const uploadForm = reactive({
  file_category: 'shared', // shared or notice
  folderPath: '' // 上传到指定文件夹
})

const files = ref([])

const searchForm = reactive({
  name: '',
  type: ''
})

const pagination = reactive({
  currentPage: 1,
  pageSize: 20,
  total: 0
})

const loadFiles = async () => {
  loading.value = true
  try {
    const params = {
      page: viewMode.value === 'list' ? pagination.currentPage : 1,
      per_page: viewMode.value === 'list' ? pagination.pageSize : 9999, // 树形视图加载所有数据
      file_category: activeTab.value, // 传递当前标签页
      ...searchForm
    }
    
    // 如果是须知文件，清除 type 搜索条件
    if (activeTab.value === 'notice') {
      delete params.type
    }
    
    console.log('加载文件，参数:', params)
    
    const response = await getSharedFiles(params)
    
    if (response.success) {
      files.value = response.data
      pagination.total = response.total
      
      console.log('加载的文件数量:', files.value.length)
      console.log('文件列表:', files.value)
      
      // 如果是树形视图，构建树形数据
      if (viewMode.value === 'tree') {
        buildTreeData(response.data)
      }
    }
  } catch (error) {
    console.error('Load files error:', error)
    ElMessage.error('加载文件列表失败')
  } finally {
    loading.value = false
  }
}

// 构建树形数据结构
const buildTreeData = (fileList) => {
  const tree = {}
  const result = []
  
  // 不过滤 .folder 文件，而是用它们来构建文件夹结构
  fileList.forEach(file => {
    // 从文件名中提取路径
    const parts = file.name.split('/')
    let currentLevel = tree
    
    // 构建文件夹层级
    for (let i = 0; i < parts.length - 1; i++) {
      const folderName = parts[i]
      if (!currentLevel[folderName]) {
        currentLevel[folderName] = {
          folders: {},
          files: []
        }
      }
      currentLevel = currentLevel[folderName].folders
    }
    
    // 添加文件到最后一级
    const fileName = parts[parts.length - 1]
    
    // 如果是 .folder 占位文件，跳过不添加到文件列表
    if (fileName === '.folder') {
      return
    }
    
    if (parts.length === 1) {
      // 根目录文件
      if (!tree['根目录']) {
        tree['根目录'] = {
          folders: {},
          files: []
        }
      }
      tree['根目录'].files.push({
        ...file,
        label: fileName
      })
    } else {
      // 子文件夹文件
      let temp = tree
      for (let i = 0; i < parts.length - 1; i++) {
        if (!temp[parts[i]]) {
          temp[parts[i]] = {
            folders: {},
            files: []
          }
        }
        if (i === parts.length - 2) {
          temp[parts[i]].files.push({
            ...file,
            label: fileName
          })
        }
        temp = temp[parts[i]].folders
      }
    }
  })
  
  // 转换为树形结构
  const convertToTree = (obj, path = '') => {
    const nodes = []
    
    Object.keys(obj).forEach(key => {
      const node = {
        id: `folder-${path}${key}`,
        label: key,
        isFolder: true,
        children: []
      }
      
      // 添加子文件夹
      if (obj[key].folders && Object.keys(obj[key].folders).length > 0) {
        node.children.push(...convertToTree(obj[key].folders, `${path}${key}/`))
      }
      
      // 添加文件
      if (obj[key].files && obj[key].files.length > 0) {
        obj[key].files.forEach(file => {
          node.children.push({
            ...file,
            id: `file-${file.id}`,
            isFolder: false
          })
        })
      }
      
      nodes.push(node)
    })
    
    return nodes
  }
  
  treeData.value = convertToTree(tree)
  
  // 构建文件夹选项列表
  buildFolderOptions()
}

// 构建文件夹选项列表（用于下拉选择）
const buildFolderOptions = () => {
  const options = [
    { value: '__ROOT__', label: '根目录' } // 使用特殊值代替空字符串
  ]
  
  // 从所有文件中提取文件夹路径
  const folderSet = new Set()
  
  files.value.forEach(file => {
    const parts = file.name.split('/')
    // 如果文件在子文件夹中
    if (parts.length > 1) {
      // 提取所有层级的文件夹路径
      for (let i = 1; i < parts.length; i++) {
        const folderPath = parts.slice(0, i).join('/')
        folderSet.add(folderPath)
      }
    }
  })
  
  // 转换为选项数组并排序
  const folders = Array.from(folderSet).sort()
  folders.forEach(folder => {
    options.push({
      value: folder,
      label: folder
    })
  })
  
  folderOptions.value = options
  console.log('Folder options:', folderOptions.value)
}

const handleTabClick = async () => {
  pagination.currentPage = 1
  // 切换标签页时，自动设置上传表单的分类
  uploadForm.file_category = activeTab.value
  // 等待 Vue 更新
  await nextTick()
  loadFiles()
}

const handleSearch = () => {
  pagination.currentPage = 1
  loadFiles()
}

const handleReset = () => {
  searchForm.name = ''
  searchForm.type = ''
  handleSearch()
}

const handleSizeChange = (size) => {
  pagination.pageSize = size
  loadFiles()
}

const handleCurrentChange = (page) => {
  pagination.currentPage = page
  loadFiles()
}

const handleView = (row) => {
  try {
    // 自动识别环境：如果是服务器就用服务器地址，否则用本地地址
    const isServer = window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1'
    const baseUrl = isServer ? 'http://renli.cyygg.cn' : 'http://localhost:8000'
    
    // 去掉 id 中的 'file-' 前缀
    const fileId = String(row.id).replace('file-', '')
    
    // 使用查看路由
    const viewUrl = `${baseUrl}/view/shared-files/${fileId}`
    
    // 在新窗口打开文件
    // 对于图片、PDF等，浏览器会自动预览
    // 对于其他文件，浏览器会提示下载或用默认程序打开
    window.open(viewUrl, '_blank')
    
    ElMessage.success('正在打开文件')
  } catch (error) {
    console.error('View error:', error)
    ElMessage.error('打开失败: ' + (error.message || '未知错误'))
  }
}

const handleDownload = (row) => {
  try {
    // 自动识别环境：如果是服务器就用服务器地址，否则用本地地址
    const isServer = window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1'
    const baseUrl = isServer ? 'http://renli.cyygg.cn' : 'http://localhost:8000'
    
    // 去掉 id 中的 'file-' 前缀
    const fileId = String(row.id).replace('file-', '')
    const downloadUrl = `${baseUrl}/download/shared-files/${fileId}`
    
    // 创建隐藏的a标签进行下载
    const link = document.createElement('a')
    link.href = downloadUrl
    link.download = row.original_name || row.name
    link.style.display = 'none'
    
    // 添加到页面并触发点击
    document.body.appendChild(link)
    link.click()
    
    // 下载完成后移除链接
    document.body.removeChild(link)
    
    ElMessage.success('开始下载')
  } catch (error) {
    console.error('Download error:', error)
    ElMessage.error('下载失败: ' + (error.message || '未知错误'))
  }
}

const handleEdit = (row) => {
  // 编辑文件信息
  // 去掉 id 中的 'file-' 前缀
  editForm.id = String(row.id).replace('file-', '')
  editForm.name = row.name
  editForm.description = row.description || ''
  showEditDialog.value = true
}

const handleEditSubmit = async () => {
  try {
    await updateSharedFile(editForm.id, {
      name: editForm.name,
      description: editForm.description
    })
    
    ElMessage.success('更新成功')
    showEditDialog.value = false
    loadFiles()
  } catch (error) {
    console.error('Update error:', error)
    ElMessage.error('更新失败')
  }
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除该文件吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    
    // 去掉 id 中的 'file-' 前缀
    const fileId = String(row.id).replace('file-', '')
    await deleteSharedFile(fileId)
    ElMessage.success('删除成功')
    loadFiles()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('Delete error:', error)
      ElMessage.error('删除失败')
    }
  }
}

const handleFileChange = (file, uploadFileList) => {
  // 如果是须知文件，只允许PDF格式
  if (uploadForm.file_category === 'notice') {
    const fileName = file.name.toLowerCase()
    if (!fileName.endsWith('.pdf')) {
      ElMessage.warning('须知文件只能上传PDF格式')
      // 移除非PDF文件
      const index = uploadFileList.findIndex(f => f.uid === file.uid)
      if (index > -1) {
        uploadFileList.splice(index, 1)
      }
      fileList.value = uploadFileList
      return
    }
  }
  fileList.value = uploadFileList
  console.log('File changed:', file, uploadFileList)
}

const handleFileRemove = (file, uploadFileList) => {
  // 文件移除
  fileList.value = uploadFileList
  console.log('File removed:', file, uploadFileList)
}

const handleUpload = async () => {
  if (!uploadRef.value || fileList.value.length === 0) {
    ElMessage.warning('请选择要上传的文件')
    return
  }

  // 须知文件上传前再次验证格式
  if (uploadForm.file_category === 'notice') {
    const invalidFiles = fileList.value.filter(f => !f.name.toLowerCase().endsWith('.pdf'))
    if (invalidFiles.length > 0) {
      ElMessage.warning('须知文件只能上传PDF格式')
      return
    }
  }

  uploading.value = true
  try {
    // 上传每个文件
    for (const file of fileList.value) {
      const formData = new FormData()
      
      // 如果有文件夹路径，添加到文件名前
      const fileName = uploadForm.folderPath && uploadForm.folderPath !== '__ROOT__'
        ? `${uploadForm.folderPath}/${file.name}`
        : file.name
      
      console.log('上传文件:', fileName)
      
      // 直接使用原始文件，但通过 FormData 传递路径信息
      formData.append('file', file.raw)
      formData.append('file_category', uploadForm.file_category)
      formData.append('folder_path', fileName) // 添加完整路径
      
      await uploadFile(formData)
    }
    
    ElMessage.success('上传成功')
    showUploadDialog.value = false
    loadFiles()
  } catch (error) {
    console.error('Upload error:', error)
    ElMessage.error('上传失败')
  } finally {
    uploading.value = false
  }
}

const handleUploadDialogClose = () => {
  fileList.value = []
  uploadRef.value?.clearFiles()
  // 重置上传表单
  uploadForm.file_category = activeTab.value
  uploadForm.folderPath = ''
}

// 上传到指定文件夹
const handleUploadToFolder = (folderData) => {
  // 获取文件夹路径
  const folderPath = getFolderPath(folderData)
  uploadForm.folderPath = folderPath
  showUploadDialog.value = true
}

// 获取文件夹完整路径
const getFolderPath = (folderData) => {
  if (folderData.label === '根目录') return ''
  // 从 id 中提取路径
  const path = folderData.id.replace('folder-', '')
  return path
}

// 显示创建文件夹对话框
const handleShowCreateFolder = () => {
  // 重置表单
  folderForm.name = ''
  folderForm.parentPath = '__ROOT__' // 默认选中根目录
  // 构建文件夹选项
  buildFolderOptions()
  console.log('打开对话框，文件夹选项:', folderOptions.value)
  console.log('当前 parentPath:', folderForm.parentPath)
  showCreateFolderDialog.value = true
}

// 文件夹选择变化
const handleFolderChange = (value) => {
  console.log('文件夹选择变化:', value)
  console.log('folderForm.parentPath:', folderForm.parentPath)
}

// 新建文件夹
const handleCreateFolder = async () => {
  if (!folderForm.name.trim()) {
    ElMessage.warning('请输入文件夹名称')
    return
  }

  console.log('创建文件夹:', {
    name: folderForm.name,
    parentPath: folderForm.parentPath,
    activeTab: activeTab.value
  })

  try {
    // 处理根目录的特殊值
    const actualParentPath = folderForm.parentPath === '__ROOT__' ? '' : folderForm.parentPath
    
    // 构建完整路径
    const fullPath = actualParentPath 
      ? `${actualParentPath}/${folderForm.name}`
      : folderForm.name

    console.log('完整路径:', fullPath)

    // 创建一个占位文件来表示文件夹
    const formData = new FormData()
    const blob = new Blob(['folder'], { type: 'text/plain' })
    const file = new File([blob], '.folder', { type: 'text/plain' })
    formData.append('file', file)
    formData.append('file_category', activeTab.value)
    formData.append('folder_path', `${fullPath}/.folder`) // 添加完整路径参数
    
    console.log('开始上传文件夹占位文件...')
    const response = await uploadFile(formData)
    console.log('上传响应:', response)
    
    ElMessage.success('文件夹创建成功')
    showCreateFolderDialog.value = false
    folderForm.name = ''
    folderForm.parentPath = '__ROOT__'
    
    console.log('重新加载文件列表...')
    await loadFiles()
  } catch (error) {
    console.error('Create folder error:', error)
    console.error('Error details:', error.response || error)
    ElMessage.error('创建文件夹失败: ' + (error.response?.data?.message || error.message || '未知错误'))
  }
}

// 删除文件夹
const handleDeleteFolder = async (folderData) => {
  try {
    await ElMessageBox.confirm(
      '删除文件夹将删除其中的所有文件，确定要删除吗？', 
      '警告', 
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
    
    // 获取文件夹路径
    const folderPath = getFolderPath(folderData)
    
    // 获取文件夹下的所有文件（包括子文件夹中的文件）
    const filesToDelete = []
    const collectFiles = (node) => {
      if (node.children) {
        node.children.forEach(child => {
          if (child.isFolder) {
            collectFiles(child)
          } else {
            filesToDelete.push(child.id.replace('file-', ''))
          }
        })
      }
    }
    collectFiles(folderData)
    
    // 同时查找该文件夹路径下的 .folder 占位文件
    const folderFiles = files.value.filter(f => {
      const fileName = f.name
      // 匹配该文件夹及其子文件夹的 .folder 文件
      return fileName.startsWith(folderPath + '/') || fileName === folderPath + '/.folder'
    })
    
    // 添加 .folder 占位文件到删除列表
    folderFiles.forEach(f => {
      if (f.name.endsWith('.folder') && !filesToDelete.includes(String(f.id))) {
        filesToDelete.push(String(f.id))
      }
    })
    
    console.log('要删除的文件:', filesToDelete)
    
    if (filesToDelete.length === 0) {
      ElMessage.warning('文件夹为空或已被删除')
      loadFiles()
      return
    }
    
    // 删除所有文件
    for (const fileId of filesToDelete) {
      await deleteSharedFile(fileId)
    }
    
    ElMessage.success('文件夹删除成功')
    loadFiles()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('Delete folder error:', error)
      ElMessage.error('删除文件夹失败')
    }
  }
}

const getTypeTagType = (type) => {
  const types = {
    document: 'primary',
    image: 'success',
    video: 'warning',
    other: 'info'
  }
  return types[type] || 'info'
}

const getTypeText = (type) => {
  const texts = {
    document: '文档',
    image: '图片',
    video: '视频',
    other: '其他'
  }
  return texts[type] || '未知'
}

const formatFileSize = (size) => {
  if (size < 1024) return size + ' B'
  if (size < 1024 * 1024) return (size / 1024).toFixed(1) + ' KB'
  if (size < 1024 * 1024 * 1024) return (size / (1024 * 1024)).toFixed(1) + ' MB'
  return (size / (1024 * 1024 * 1024)).toFixed(1) + ' GB'
}

// 格式化日期时间
const formatDateTime = (dateTime) => {
  if (!dateTime) return '-'
  const date = new Date(dateTime)
  return date.toLocaleString('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false
  })
}

onMounted(() => {
  loadFiles()
})
</script>

<style scoped>
.shared-files-page {
  padding: 0;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-header h1 {
  font-size: 24px;
  color: #303133;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 10px;
}

.file-tabs {
  margin-bottom: 20px;
}

.search-section {
  margin-bottom: 20px;
}

.table-section {
  margin-bottom: 20px;
}

.pagination {
  margin-top: 20px;
  text-align: right;
}

.tree-view {
  min-height: 400px;
}

.tree-node {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  padding: 8px 0;
}

.node-content {
  display: flex;
  align-items: center;
  flex: 1;
  gap: 8px;
}

.folder-icon {
  color: #409EFF;
  font-size: 18px;
}

.file-icon {
  color: #909399;
  font-size: 16px;
}

.node-label {
  font-size: 14px;
  color: #303133;
  font-weight: 500;
}

.file-info {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-left: 20px;
  font-size: 13px;
  color: #606266;
}

.file-size {
  color: #909399;
}

.file-uploader {
  color: #606266;
}

.file-time {
  color: #909399;
}

.node-actions {
  display: flex;
  gap: 8px;
  opacity: 0;
  transition: opacity 0.2s;
}

.tree-node:hover .node-actions {
  opacity: 1;
}

:deep(.el-tree-node__content) {
  height: auto;
  padding: 4px 0;
}

:deep(.el-tree-node__content:hover) {
  background-color: #f5f7fa;
}

:deep(.el-table) {
  font-size: 14px;
}

:deep(.el-form-item) {
  margin-bottom: 20px;
}

:deep(.el-tabs__nav-wrap::after) {
  height: 1px;
}
</style>
