module.exports = {
  devServer: {
    proxy: {
      '/api': {
        target: 'https://renli.cyygg.cn',
        changeOrigin: true,
        pathRewrite: {
          '^/api': ''
        }
      }
    }
  }
}
