// 题库配置
export default {
  // 是否使用本地数据
  useLocalData: false,
  
  // 云端 JSON 地址
  remoteUrl: 'https://renli.cyygg.cn/tim.json',
  
  // 使用说明：
  // 1. 小程序开发：useLocalData = true（使用本地 tim.json）
  // 2. 云端获取：useLocalData = false（从 remoteUrl 获取）
  // 3. 如果使用远程：需要在微信公众平台配置合法域名
}
