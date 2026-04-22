<template>
  <view class="container">
    <!-- 列表模式（背题） -->
    <view v-if="mode === 'read' && viewType === 'list'" class="list-container">
      <view class="list-header">
        <text class="list-title">📖 题目列表</text>
        <text class="list-count">共 {{ questions.length }} 题</text>
      </view>
      
      <view class="question-list">
        <view v-for="(question, index) in questions" :key="question.id" class="list-item">
          <view class="list-item-header">
            <text class="list-item-number">{{ index + 1 }}</text>
            <text class="list-item-type">{{ getQuestionTypeName(question.type) }}</text>
          </view>
          
          <text class="list-item-question">{{ question.question }}</text>
          
          <!-- 选择题选项 -->
          <view v-if="question.type === 'choice' || question.type === 'multiple'" class="list-item-options">
            <view 
              v-for="(option, optIndex) in question.options" 
              :key="optIndex"
              class="list-option"
              :class="{'list-option-correct': isOptionCorrect(question, option.charAt(0))}"
            >
              <text>{{ option }}</text>
            </view>
          </view>
          
          <!-- 填空题/判断题答案 -->
          <view v-else class="list-item-answer">
            <text class="list-answer-label">答案：</text>
            <text class="list-answer-value">{{ question.answer }}</text>
          </view>
        </view>
      </view>
    </view>
    
    <!-- 总结页面 -->
    <view v-else-if="showSummary" class="summary-container">
      <view class="summary-card">
        <text class="summary-title">🎉 答题完成</text>
        
        <view class="summary-stats">
          <view class="stat-item">
            <text class="stat-label">总题数</text>
            <text class="stat-value">{{ questions.length }}</text>
          </view>
          <view class="stat-item correct">
            <text class="stat-label">答对</text>
            <text class="stat-value">{{ correctCount }}</text>
          </view>
          <view class="stat-item wrong">
            <text class="stat-label">答错</text>
            <text class="stat-value">{{ wrongCount }}</text>
          </view>
        </view>
        
        <view class="accuracy-circle">
          <text class="accuracy-value">{{ accuracy }}%</text>
          <text class="accuracy-label">正确率</text>
        </view>
        
        <view class="summary-actions">
          <view class="btn btn-primary" @click="restartPractice">再练一次</view>
          <view class="btn btn-secondary" @click="backToChapter">返回章节</view>
        </view>
      </view>
    </view>

    <!-- 答题页面 -->
    <view v-else-if="questions.length > 0" class="practice-container">
      <view class="progress-bar">
        <view class="progress-header">
          <text class="progress-text">{{ currentIndex + 1 }} / {{ questions.length }}</text>
          <text class="mode-badge" :class="mode === 'read' ? 'read-mode' : 'practice-mode'">
            {{ mode === 'read' ? '📖 背题模式' : '✏️ 练习模式' }}
          </text>
        </view>
        <view class="progress">
          <view class="progress-fill" :style="{width: progressWidth}"></view>
        </view>
      </view>

      <view class="question-card" :class="{'read-mode-card': mode === 'read'}">
        <view class="question-type">
          {{ getQuestionTypeName(currentQuestion.type) }}
        </view>
        <text class="question-text">{{ currentQuestion.question }}</text>

        <!-- 选择题/多选题 -->
        <view v-if="currentQuestion.type === 'choice' || currentQuestion.type === 'multiple'" class="options">
          <view 
            v-for="(option, index) in currentQuestion.options" 
            :key="index"
            class="option"
            :class="{
              'selected': isSelected(option.charAt(0)),
              'correct': showResult && isCorrectOption(option.charAt(0)),
              'wrong': showResult && isSelected(option.charAt(0)) && !isCorrectOption(option.charAt(0))
            }"
            @click="selectAnswer(option.charAt(0))"
          >
            <text>{{ option }}</text>
          </view>
          <!-- 多选题确认按钮 -->
          <view v-if="currentQuestion.type === 'multiple' && selectedAnswers.length > 0 && !showResult" class="confirm-btn" @click="submitAnswer">
            <text>确认提交 (已选{{ selectedAnswers.length }}项)</text>
          </view>
        </view>

        <!-- 填空题 -->
        <view v-else-if="currentQuestion.type === 'fill'" class="fill-section">
          <view class="fill-hint">
            <text>📝 请在下方输入答案，多个空用顿号（、）分隔</text>
          </view>
          <textarea 
            class="fill-input" 
            v-model="fillAnswer"
            placeholder="请输入答案..."
            :disabled="mode === 'read' || showResult"
          />
          <view v-if="!showResult && mode !== 'read'" class="confirm-btn" @click="submitFillAnswer">
            <text>提交答案</text>
          </view>
        </view>

        <!-- 判断题 -->
        <view v-else-if="currentQuestion.type === 'judge'" class="judge-options">
          <view 
            class="judge-btn"
            :class="{
              'selected': selectedAnswer === true,
              'correct': showResult && isJudgeCorrectAnswer(true),
              'wrong': showResult && selectedAnswer === true && !isJudgeCorrectAnswer(true)
            }"
            @click="selectAnswer(true)"
          >
            <text>✓ 正确</text>
          </view>
          <view 
            class="judge-btn"
            :class="{
              'selected': selectedAnswer === false,
              'correct': showResult && isJudgeCorrectAnswer(false),
              'wrong': showResult && selectedAnswer === false && !isJudgeCorrectAnswer(false)
            }"
            @click="selectAnswer(false)"
          >
            <text>✗ 错误</text>
          </view>
        </view>

        <!-- 练习模式结果 -->
        <view v-if="showResult && mode === 'practice'" class="result">
          <text :class="isCorrect ? 'correct-text' : 'wrong-text'">
            {{ isCorrect ? '✓ 回答正确' : '✗ 回答错误' }}
          </text>
          <text v-if="!isCorrect" class="answer-detail">正确答案：{{ getAnswerText() }}</text>
        </view>
        
        <!-- 背题模式答案 -->
        <view v-if="mode === 'read'" class="answer-hint">
          <text class="answer-text">✓ 正确答案：{{ getAnswerText() }}</text>
        </view>
      </view>

      <view class="actions">
        <view v-if="mode === 'read' || showResult" class="btn btn-primary" @click="nextQuestion">
          {{ currentIndex < questions.length - 1 ? '下一题' : '完成' }}
        </view>
      </view>
    </view>

    <view v-else class="empty">
      <text>暂无题目</text>
    </view>
  </view>
</template>

<script>
import config from '@/config.js'

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
      chapterId: '',
      mode: 'practice',
      viewType: 'single',
      questions: [],
      currentIndex: 0,
      selectedAnswer: null,
      selectedAnswers: [],
      fillAnswer: '',
      showResult: false,
      isCorrect: false,
      correctCount: 0,
      wrongCount: 0,
      showSummary: false,
      useLocalData: config.useLocalData,
      jsonUrl: config.remoteUrl
    }
  },
  computed: {
    currentQuestion() {
      return this.questions[this.currentIndex] || {}
    },
    progressWidth() {
      return ((this.currentIndex + 1) / this.questions.length * 100) + '%'
    },
    accuracy() {
      const total = this.correctCount + this.wrongCount
      return total > 0 ? Math.round((this.correctCount / total) * 100) : 0
    }
  },
  onLoad(options) {
    this.categoryId = options.categoryId
    this.subjectId = options.subjectId
    this.chapterId = options.chapterId
    this.mode = options.mode || 'practice'
    this.viewType = options.viewType || 'single'
    this.loadQuestions()
    
    if (this.mode === 'read') {
      this.showResult = true
    }
  },
  methods: {
    getQuestionTypeName(type) {
      const typeMap = {
        'choice': '单选题',
        'multiple': '多选题',
        'fill': '填空题',
        'judge': '判断题'
      }
      return typeMap[type] || '未知题型'
    },
    loadQuestions() {
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
      if (!data || !data.categories) return
      
      const category = data.categories.find(c => c.id === this.categoryId)
      if (!category || !category.subjects) return
      
      const subject = category.subjects.find(s => s.id === this.subjectId)
      if (!subject || !subject.chapters) return
      
      let allQuestions = []
      
      if (this.chapterId === 'all') {
        subject.chapters.forEach(chapter => {
          allQuestions = allQuestions.concat(chapter.questions)
        })
      } else {
        const chapter = subject.chapters.find(c => c.id === this.chapterId)
        if (chapter) {
          allQuestions = chapter.questions
        }
      }
      
      this.questions = this.shuffleArray([...allQuestions])
    },
    shuffleArray(array) {
      for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]]
      }
      return array
    },
    isSelected(option) {
      if (this.currentQuestion.type === 'multiple') {
        return this.selectedAnswers.includes(option)
      }
      return this.selectedAnswer === option
    },
    isCorrectOption(option) {
      const answer = this.currentQuestion.answer
      if (this.currentQuestion.type === 'multiple') {
        return answer.includes(option)
      }
      return answer === option
    },
    selectAnswer(answer) {
      if (this.mode === 'read' || this.showResult) return
      
      if (this.currentQuestion.type === 'multiple') {
        const index = this.selectedAnswers.indexOf(answer)
        if (index > -1) {
          this.selectedAnswers.splice(index, 1)
        } else {
          this.selectedAnswers.push(answer)
        }
      } else {
        this.selectedAnswer = answer
        this.submitAnswer()
      }
    },
    submitFillAnswer() {
      if (!this.fillAnswer.trim()) {
        uni.showToast({ title: '请输入答案', icon: 'none' })
        return
      }
      this.showResult = true
      // 填空题直接显示答案，让用户自己对比
      this.isCorrect = false
      this.wrongCount++
    },
    submitAnswer() {
      this.showResult = true
      
      if (this.currentQuestion.type === 'multiple') {
        const correctAnswer = this.currentQuestion.answer.split('').sort().join('')
        const userAnswer = this.selectedAnswers.sort().join('')
        this.isCorrect = correctAnswer === userAnswer
      } else if (this.currentQuestion.type === 'judge') {
        // 判断题答案: A=正确/true, B=错误/false
        const correctAnswer = this.currentQuestion.answer === 'A' || this.currentQuestion.answer === true
        this.isCorrect = this.selectedAnswer === correctAnswer
      } else {
        this.isCorrect = this.selectedAnswer === this.currentQuestion.answer
      }
      
      if (this.isCorrect) {
        this.correctCount++
      } else {
        this.wrongCount++
      }
    },
    nextQuestion() {
      if (this.currentIndex < this.questions.length - 1) {
        this.currentIndex++
        this.selectedAnswer = null
        this.selectedAnswers = []
        this.fillAnswer = ''
        this.showResult = this.mode === 'read'
        this.isCorrect = false
      } else {
        if (this.mode === 'practice') {
          this.showSummary = true
        } else {
          uni.showToast({ title: '已阅读完毕', icon: 'success' })
          setTimeout(() => uni.navigateBack(), 1500)
        }
      }
    },
    restartPractice() {
      this.currentIndex = 0
      this.selectedAnswer = null
      this.selectedAnswers = []
      this.fillAnswer = ''
      this.showResult = false
      this.isCorrect = false
      this.correctCount = 0
      this.wrongCount = 0
      this.showSummary = false
      this.questions = this.shuffleArray([...this.questions])
    },
    backToChapter() {
      uni.navigateBack()
    },
    getAnswerText() {
      if (this.currentQuestion.type === 'judge') {
        const answer = this.currentQuestion.answer
        return (answer === 'A' || answer === true) ? '正确' : '错误'
      }
      return this.currentQuestion.answer
    },
    isJudgeCorrectAnswer(value) {
      const answer = this.currentQuestion.answer
      const correctAnswer = answer === 'A' || answer === true
      return value === correctAnswer
    },
    isOptionCorrect(question, option) {
      if (question.type === 'multiple') {
        return question.answer.includes(option)
      }
      return question.answer === option
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

.practice-container {
  display: flex;
  flex-direction: column;
}

.progress-bar {
  margin-bottom: 40rpx;
}

.progress-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20rpx;
}

.progress-text {
  font-size: 28rpx;
  color: #666;
}

.mode-badge {
  padding: 8rpx 20rpx;
  border-radius: 20rpx;
  font-size: 24rpx;
  font-weight: 500;
}

.practice-mode {
  background: #e3f2fd;
  color: #007aff;
}

.read-mode {
  background: #fff3e0;
  color: #ff9800;
}

.progress {
  height: 8rpx;
  background: #e5e5e5;
  border-radius: 4rpx;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #667eea, #764ba2);
  transition: width 0.3s;
}

.question-card {
  background: white;
  padding: 40rpx;
  border-radius: 20rpx;
  box-shadow: 0 4rpx 20rpx rgba(0,0,0,0.08);
  margin-bottom: 40rpx;
}

.read-mode-card {
  border: 2rpx solid #ffe0b2;
}

.question-type {
  display: inline-block;
  padding: 10rpx 20rpx;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border-radius: 8rpx;
  font-size: 24rpx;
  margin-bottom: 30rpx;
}

.question-text {
  display: block;
  font-size: 32rpx;
  color: #333;
  line-height: 1.6;
  margin-bottom: 40rpx;
}

.options {
  display: flex;
  flex-direction: column;
  gap: 20rpx;
}

.option {
  padding: 30rpx;
  background: #f5f5f5;
  border-radius: 15rpx;
  border: 2rpx solid transparent;
  font-size: 30rpx;
  color: #333;
}

.option.selected {
  background: #e3f2fd;
  border-color: #007aff;
}

.option.correct {
  background: #d4edda;
  border-color: #28a745;
}

.option.wrong {
  background: #f8d7da;
  border-color: #dc3545;
}

.confirm-btn {
  margin-top: 30rpx;
  padding: 25rpx;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 15rpx;
  text-align: center;
  color: white;
  font-size: 30rpx;
  font-weight: 500;
}

/* 填空题样式 */
.fill-section {
  display: flex;
  flex-direction: column;
  gap: 20rpx;
}

.fill-hint {
  padding: 20rpx;
  background: #fff3e0;
  border-radius: 10rpx;
  font-size: 26rpx;
  color: #ff9800;
}

.fill-input {
  width: 100%;
  min-height: 200rpx;
  padding: 25rpx;
  background: #f5f5f5;
  border-radius: 15rpx;
  font-size: 30rpx;
  color: #333;
  box-sizing: border-box;
}

/* 判断题样式 */
.judge-options {
  display: flex;
  gap: 30rpx;
}

.judge-btn {
  flex: 1;
  padding: 40rpx;
  background: #f5f5f5;
  border-radius: 15rpx;
  text-align: center;
  font-size: 32rpx;
  border: 2rpx solid transparent;
}

.judge-btn.selected {
  background: #e3f2fd;
  border-color: #007aff;
}

.judge-btn.correct {
  background: #d4edda;
  border-color: #28a745;
}

.judge-btn.wrong {
  background: #f8d7da;
  border-color: #dc3545;
}

.result {
  margin-top: 30rpx;
  padding: 25rpx;
  border-radius: 15rpx;
  background: #f9f9f9;
}

.correct-text {
  color: #28a745;
  font-size: 30rpx;
  font-weight: 500;
  display: block;
}

.wrong-text {
  color: #dc3545;
  font-size: 30rpx;
  font-weight: 500;
  display: block;
}

.answer-detail {
  display: block;
  margin-top: 15rpx;
  font-size: 28rpx;
  color: #666;
  line-height: 1.5;
}

.answer-hint {
  margin-top: 30rpx;
  padding: 25rpx;
  background: #d4edda;
  border-left: 4rpx solid #28a745;
  border-radius: 10rpx;
}

.answer-text {
  font-size: 30rpx;
  color: #155724;
  font-weight: 500;
}

.actions {
  margin-top: auto;
}

.btn {
  padding: 30rpx;
  border-radius: 15rpx;
  text-align: center;
  font-size: 32rpx;
  font-weight: bold;
  color: white;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.btn-secondary {
  background: #6c757d;
  margin-top: 20rpx;
}

.empty {
  text-align: center;
  padding: 100rpx 0;
  color: #999;
  font-size: 32rpx;
}

/* 总结页面 */
.summary-container {
  min-height: 80vh;
  display: flex;
  align-items: center;
  justify-content: center;
}

.summary-card {
  background: white;
  padding: 60rpx 40rpx;
  border-radius: 25rpx;
  box-shadow: 0 8rpx 40rpx rgba(0,0,0,0.1);
  width: 100%;
}

.summary-title {
  display: block;
  text-align: center;
  font-size: 48rpx;
  font-weight: bold;
  color: #333;
  margin-bottom: 50rpx;
}

.summary-stats {
  display: flex;
  justify-content: space-around;
  margin-bottom: 50rpx;
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 25rpx 35rpx;
  background: #f5f5f5;
  border-radius: 15rpx;
}

.stat-item.correct {
  background: #d4edda;
}

.stat-item.wrong {
  background: #f8d7da;
}

.stat-label {
  font-size: 26rpx;
  color: #666;
  margin-bottom: 10rpx;
}

.stat-value {
  font-size: 44rpx;
  font-weight: bold;
  color: #333;
}

.stat-item.correct .stat-value {
  color: #28a745;
}

.stat-item.wrong .stat-value {
  color: #dc3545;
}

.accuracy-circle {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 260rpx;
  height: 260rpx;
  margin: 0 auto 50rpx;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  box-shadow: 0 8rpx 30rpx rgba(102, 126, 234, 0.4);
}

.accuracy-value {
  font-size: 64rpx;
  font-weight: bold;
  color: white;
}

.accuracy-label {
  font-size: 26rpx;
  color: rgba(255, 255, 255, 0.9);
}

.summary-actions {
  display: flex;
  flex-direction: column;
}

/* 列表模式样式 */
.list-container {
  min-height: 100vh;
}

.list-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 50rpx 40rpx 40rpx;
  margin: -40rpx -40rpx 40rpx;
  color: white;
  border-radius: 0 0 30rpx 30rpx;
}

.list-title {
  display: block;
  font-size: 44rpx;
  font-weight: bold;
  margin-bottom: 15rpx;
}

.list-count {
  display: block;
  font-size: 28rpx;
  opacity: 0.9;
}

.question-list {
  display: flex;
  flex-direction: column;
  gap: 30rpx;
  padding-bottom: 40rpx;
}

.list-item {
  background: white;
  padding: 35rpx;
  border-radius: 20rpx;
  box-shadow: 0 4rpx 20rpx rgba(0,0,0,0.08);
}

.list-item-header {
  display: flex;
  align-items: center;
  gap: 20rpx;
  margin-bottom: 25rpx;
}

.list-item-number {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 56rpx;
  height: 56rpx;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border-radius: 50%;
  font-size: 26rpx;
  font-weight: bold;
}

.list-item-type {
  padding: 8rpx 18rpx;
  background: #fff3e0;
  color: #ff9800;
  border-radius: 20rpx;
  font-size: 22rpx;
}

.list-item-question {
  display: block;
  font-size: 30rpx;
  color: #333;
  line-height: 1.6;
  margin-bottom: 25rpx;
}

.list-item-options {
  display: flex;
  flex-direction: column;
  gap: 15rpx;
}

.list-option {
  padding: 22rpx 28rpx;
  background: #f5f5f5;
  border-radius: 12rpx;
  font-size: 28rpx;
  color: #666;
  border: 2rpx solid transparent;
}

.list-option-correct {
  background: #d4edda;
  border-color: #28a745;
  color: #155724;
  font-weight: 500;
}

.list-item-answer {
  padding: 22rpx 28rpx;
  background: #d4edda;
  border-radius: 12rpx;
  border-left: 4rpx solid #28a745;
}

.list-answer-label {
  font-size: 28rpx;
  color: #155724;
  margin-right: 10rpx;
}

.list-answer-value {
  font-size: 28rpx;
  color: #155724;
  font-weight: bold;
  line-height: 1.5;
}
</style>
