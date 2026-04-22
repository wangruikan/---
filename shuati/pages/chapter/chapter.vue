<template>
  <view class="container">
    <view class="header">
      <text class="title">{{ subjectName }}</text>
      <text class="subtitle">选择章节</text>
    </view>

    <!-- 答题模式设置 -->
    <view class="mode-card">
      <view class="mode-title">答题模式</view>
      <view class="mode-options">
        <view 
          class="mode-option"
          :class="{'active': mode === 'practice'}"
          @click="mode = 'practice'"
        >
          <text class="mode-icon">✏️</text>
          <text>练习</text>
        </view>
        <view 
          class="mode-option"
          :class="{'active': mode === 'read'}"
          @click="mode = 'read'"
        >
          <text class="mode-icon">📖</text>
          <text>背题</text>
        </view>
      </view>
      
      <!-- 背题模式下显示方式选择 -->
      <view v-if="mode === 'read'" class="view-type-section">
        <view class="mode-title" style="margin-top: 25rpx;">显示方式</view>
        <view class="mode-options">
          <view 
            class="mode-option small"
            :class="{'active': viewType === 'single'}"
            @click="viewType = 'single'"
          >
            <text>📄 单题</text>
          </view>
          <view 
            class="mode-option small"
            :class="{'active': viewType === 'list'}"
            @click="viewType = 'list'"
          >
            <text>📋 列表</text>
          </view>
        </view>
      </view>
    </view>

    <!-- 全部练习按钮 -->
    <view class="all-btn" @click="startAllPractice">
      <text class="all-btn-icon">🚀</text>
      <text class="all-btn-text">全部章节练习</text>
      <text class="all-btn-count">{{ totalQuestions }} 题</text>
    </view>

    <!-- 章节列表 -->
    <view class="chapter-list">
      <view 
        v-for="(chapter, index) in chapters" 
        :key="chapter.id" 
        class="chapter-card"
        @click="startChapterPractice(chapter)"
      >
        <view class="chapter-number">{{ index + 1 }}</view>
        <view class="chapter-info">
          <text class="chapter-name">{{ chapter.name }}</text>
          <text class="chapter-count">{{ chapter.questions.length }} 题</text>
        </view>
        <text class="arrow">›</text>
      </view>
    </view>
  </view>
</template>

<script>
import config from '@/config.js'

// 本地数据（云端模式下不使用）
let questionsData = null
try {
  questionsData = require('@/tim.json')
} catch (e) {
  console.error('加载本地题库失败:', e)
}

export default {
  data() {
    return {
      categoryId: '',
      subjectId: '',
      subjectName: '',
      chapters: [],
      mode: 'practice',
      viewType: 'single', // single: 单题, list: 列表
      useLocalData: config.useLocalData,
      jsonUrl: config.remoteUrl
    }
  },
  computed: {
    totalQuestions() {
      let total = 0
      this.chapters.forEach(chapter => {
        total += chapter.questions.length
      })
      return total
    }
  },
  onLoad(options) {
    this.categoryId = options.categoryId
    this.subjectId = options.subjectId
    this.loadData()
  },
  methods: {
    loadData() {
      if (this.useLocalData) {
        this.parseData(questionsData)
        return
      }
      
      uni.showLoading({ title: '加载中...' })
      uni.request({
        url: this.jsonUrl,
        success: (res) => {
          this.parseData(res.data)
        },
        fail: () => {
          uni.showToast({ title: '加载失败', icon: 'none' })
        },
        complete: () => {
          uni.hideLoading()
        }
      })
    },
    parseData(data) {
      if (data && data.categories) {
        const category = data.categories.find(c => c.id === this.categoryId)
        if (category && category.subjects) {
          const subject = category.subjects.find(s => s.id === this.subjectId)
          if (subject) {
            this.subjectName = subject.name
            this.chapters = subject.chapters || []
          }
        }
      }
    },
    startChapterPractice(chapter) {
      uni.navigateTo({
        url: `/pages/practice/practice?categoryId=${this.categoryId}&subjectId=${this.subjectId}&chapterId=${chapter.id}&mode=${this.mode}&viewType=${this.viewType}`
      })
    },
    startAllPractice() {
      uni.navigateTo({
        url: `/pages/practice/practice?categoryId=${this.categoryId}&subjectId=${this.subjectId}&chapterId=all&mode=${this.mode}&viewType=${this.viewType}`
      })
    }
  }
}
</script>

<style scoped>
.container {
  min-height: 100vh;
  padding: 40rpx;
  background: #f5f5f5;
}

.header {
  text-align: center;
  padding: 30rpx 0 40rpx;
}

.title {
  display: block;
  font-size: 44rpx;
  font-weight: bold;
  color: #333;
  margin-bottom: 10rpx;
}

.subtitle {
  display: block;
  font-size: 28rpx;
  color: #666;
}

.mode-card {
  background: white;
  padding: 30rpx;
  border-radius: 20rpx;
  margin-bottom: 30rpx;
  box-shadow: 0 4rpx 20rpx rgba(0,0,0,0.08);
}

.mode-title {
  font-size: 28rpx;
  color: #666;
  margin-bottom: 20rpx;
}

.mode-options {
  display: flex;
  gap: 20rpx;
}

.mode-option {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 15rpx;
  padding: 25rpx;
  background: #f5f5f5;
  border-radius: 15rpx;
  font-size: 30rpx;
  color: #666;
  border: 3rpx solid transparent;
}

.mode-option.active {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border-color: transparent;
}

.mode-option.small {
  padding: 20rpx;
  font-size: 26rpx;
}

.mode-icon {
  font-size: 36rpx;
}

.all-btn {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 35rpx 40rpx;
  border-radius: 20rpx;
  margin-bottom: 30rpx;
  display: flex;
  align-items: center;
  gap: 20rpx;
  box-shadow: 0 8rpx 30rpx rgba(102, 126, 234, 0.4);
}

.all-btn:active {
  opacity: 0.9;
  transform: scale(0.98);
}

.all-btn-icon {
  font-size: 44rpx;
}

.all-btn-text {
  flex: 1;
  font-size: 34rpx;
  font-weight: bold;
  color: white;
}

.all-btn-count {
  font-size: 28rpx;
  color: rgba(255,255,255,0.8);
}

.chapter-list {
  display: flex;
  flex-direction: column;
  gap: 20rpx;
}

.chapter-card {
  background: white;
  padding: 35rpx 30rpx;
  border-radius: 15rpx;
  box-shadow: 0 2rpx 15rpx rgba(0,0,0,0.06);
  display: flex;
  align-items: center;
  gap: 25rpx;
}

.chapter-card:active {
  background: #f9f9f9;
}

.chapter-number {
  width: 60rpx;
  height: 60rpx;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28rpx;
  font-weight: bold;
  color: white;
}

.chapter-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 8rpx;
}

.chapter-name {
  font-size: 30rpx;
  color: #333;
  font-weight: 500;
}

.chapter-count {
  font-size: 24rpx;
  color: #999;
}

.arrow {
  font-size: 36rpx;
  color: #ccc;
}
</style>
