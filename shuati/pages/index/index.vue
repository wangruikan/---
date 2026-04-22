<template>
  <view class="container">
    <view class="header">
      <text class="title">刷题宝</text>
      <text class="subtitle">考研刷题神器</text>
    </view>

    <!-- 分类Tab切换 -->
    <view class="category-tabs">
      <view 
        v-for="category in categories" 
        :key="category.id" 
        class="tab-item"
        :class="{'active': currentCategoryId === category.id}"
        @click="switchCategory(category.id)"
      >
        <text class="tab-icon">{{ category.icon }}</text>
        <text class="tab-name">{{ category.name }}</text>
      </view>
    </view>

    <!-- 当前分类下的科目列表 -->
    <view class="subject-list" v-if="currentCategory">
      <view 
        v-for="subject in currentCategory.subjects" 
        :key="subject.id" 
        class="subject-card"
        @click="selectSubject(subject)"
      >
        <view class="subject-info">
          <text class="subject-icon">{{ subject.icon }}</text>
          <view class="subject-text">
            <text class="subject-name">{{ subject.name }}</text>
            <text class="subject-count">{{ subject.chapters.length }} 章节 · {{ getTotalQuestions(subject) }} 题</text>
          </view>
        </view>
        <text class="arrow">›</text>
      </view>
    </view>
  </view>
</template>

<script>
import config from '@/config.js'

// 本地数据
let questionsData = null
try {
  questionsData = require('@/tim.json')
} catch (e) {
  console.error('加载本地题库失败:', e)
}

export default {
  data() {
    return {
      categories: [],
      currentCategoryId: '',
      useLocalData: config.useLocalData,
      jsonUrl: config.remoteUrl
    }
  },
  computed: {
    currentCategory() {
      return this.categories.find(c => c.id === this.currentCategoryId)
    }
  },
  onShow() {
    this.loadData()
  },
  methods: {
    loadData() {
      if (this.useLocalData) {
        if (questionsData && questionsData.categories) {
          this.categories = questionsData.categories
          if (this.categories.length > 0 && !this.currentCategoryId) {
            this.currentCategoryId = this.categories[0].id
          }
        }
        return
      }
      
      uni.showLoading({ title: '加载中...' })
      uni.request({
        url: this.jsonUrl,
        dataType: 'json',
        header: {
          'Accept': 'application/json; charset=utf-8'
        },
        success: (res) => {
          console.log('云端数据加载成功:', res.data)
          console.log('数据类型:', typeof res.data)
          let data = res.data
          // 如果返回的是字符串，手动解析
          if (typeof data === 'string') {
            try {
              data = JSON.parse(data)
            } catch (e) {
              console.error('JSON解析失败:', e)
            }
          }
          if (data && data.categories) {
            this.categories = data.categories
            if (this.categories.length > 0 && !this.currentCategoryId) {
              this.currentCategoryId = this.categories[0].id
            }
          }
        },
        fail: (err) => {
          console.error('云端数据加载失败:', err)
          uni.showToast({ title: '加载失败', icon: 'none' })
        },
        complete: () => {
          uni.hideLoading()
        }
      })
    },
    switchCategory(categoryId) {
      this.currentCategoryId = categoryId
    },
    getTotalQuestions(subject) {
      let total = 0
      subject.chapters.forEach(chapter => {
        total += chapter.questions.length
      })
      return total
    },
    selectSubject(subject) {
      uni.navigateTo({
        url: `/pages/chapter/chapter?categoryId=${this.currentCategoryId}&subjectId=${subject.id}`
      })
    }
  }
}
</script>

<style scoped>
.container {
  min-height: 100vh;
  padding: 40rpx;
  padding-bottom: 80rpx;
  background: linear-gradient(180deg, #667eea 0%, #764ba2 35%);
  background-color: #f5f5f5;
}

.header {
  text-align: center;
  padding: 40rpx 0 50rpx;
  color: white;
}

.title {
  display: block;
  font-size: 56rpx;
  font-weight: bold;
  margin-bottom: 15rpx;
}

.subtitle {
  display: block;
  font-size: 26rpx;
  opacity: 0.9;
}

/* 分类Tab */
.category-tabs {
  display: flex;
  background: white;
  border-radius: 20rpx;
  padding: 15rpx;
  margin-bottom: 30rpx;
  box-shadow: 0 4rpx 20rpx rgba(0,0,0,0.1);
}

.tab-item {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12rpx;
  padding: 25rpx 20rpx;
  border-radius: 15rpx;
  font-size: 28rpx;
  color: #666;
  transition: all 0.3s;
}

.tab-item.active {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  font-weight: bold;
}

.tab-icon {
  font-size: 32rpx;
}

.tab-name {
  font-size: 28rpx;
}

/* 科目列表 */
.subject-list {
  display: flex;
  flex-direction: column;
  gap: 25rpx;
}

.subject-card {
  background: white;
  padding: 40rpx;
  border-radius: 20rpx;
  box-shadow: 0 4rpx 20rpx rgba(0,0,0,0.08);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.subject-card:active {
  transform: scale(0.98);
  opacity: 0.9;
}

.subject-info {
  display: flex;
  align-items: center;
  gap: 30rpx;
}

.subject-icon {
  font-size: 50rpx;
  width: 80rpx;
  height: 80rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 20rpx;
}

.subject-text {
  display: flex;
  flex-direction: column;
  gap: 10rpx;
}

.subject-name {
  font-size: 34rpx;
  font-weight: bold;
  color: #333;
}

.subject-count {
  font-size: 26rpx;
  color: #999;
}

.arrow {
  font-size: 40rpx;
  color: #ccc;
}
</style>
