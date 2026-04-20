"use strict";
const common_vendor = require("../common/vendor.js");
const BASE_URL = "https://renli.cyygg.cn/api/mini";
common_vendor.index.__f__("log", "at utils/request.js:10", "API地址:", BASE_URL);
function request(options) {
  return new Promise((resolve, reject) => {
    const token = common_vendor.index.getStorageSync("token");
    const header = {
      "Content-Type": "application/json",
      "Accept": "application/json"
    };
    if (token) {
      header["Authorization"] = `Bearer ${token}`;
      header["X-Auth-Token"] = token;
    }
    let fullUrl = BASE_URL + options.url;
    if (token && options.method === "GET") {
      const separator = fullUrl.includes("?") ? "&" : "?";
      fullUrl += separator + "token=" + encodeURIComponent(token);
    }
    common_vendor.index.request({
      url: fullUrl,
      method: options.method || "GET",
      data: options.data || {},
      header,
      success: (res) => {
        if (res.statusCode === 200) {
          resolve(res.data);
        } else if (res.statusCode === 401) {
          common_vendor.index.removeStorageSync("token");
          common_vendor.index.removeStorageSync("employeeInfo");
          common_vendor.index.showToast({
            title: "登录已过期，请重新登录",
            icon: "none"
          });
          setTimeout(() => {
            common_vendor.index.reLaunch({
              url: "/pages/login/login"
            });
          }, 1500);
          reject(res.data);
        } else if (res.statusCode === 422) {
          const message = res.data.message || "数据验证失败";
          common_vendor.index.showToast({
            title: message,
            icon: "none"
          });
          reject(res.data);
        } else {
          const message = res.data.message || "请求失败";
          common_vendor.index.showToast({
            title: message,
            icon: "none"
          });
          reject(res.data);
        }
      },
      fail: (err) => {
        common_vendor.index.__f__("error", "at utils/request.js:80", "请求失败:", err);
        common_vendor.index.showToast({
          title: "网络错误，请检查网络连接",
          icon: "none"
        });
        reject(err);
      }
    });
  });
}
const request$1 = {
  get(url, data) {
    return request({
      url,
      method: "GET",
      data
    });
  },
  post(url, data) {
    return request({
      url,
      method: "POST",
      data
    });
  },
  put(url, data) {
    return request({
      url,
      method: "PUT",
      data
    });
  },
  delete(url, data) {
    return request({
      url,
      method: "DELETE",
      data
    });
  },
  // 文件上传方法
  upload(url, filePath, formData = {}) {
    return new Promise((resolve, reject) => {
      const token = common_vendor.index.getStorageSync("token");
      const header = {};
      if (token) {
        header["Authorization"] = `Bearer ${token}`;
        header["X-Auth-Token"] = token;
      }
      common_vendor.index.uploadFile({
        url: BASE_URL + url,
        filePath,
        name: "file",
        formData,
        header,
        success: (res) => {
          try {
            const data = JSON.parse(res.data);
            if (res.statusCode === 200) {
              resolve(data);
            } else if (res.statusCode === 401) {
              common_vendor.index.removeStorageSync("token");
              common_vendor.index.removeStorageSync("employeeInfo");
              common_vendor.index.showToast({
                title: "登录已过期，请重新登录",
                icon: "none"
              });
              setTimeout(() => {
                common_vendor.index.reLaunch({
                  url: "/pages/login/login"
                });
              }, 1500);
              reject(data);
            } else {
              const message = data.message || "上传失败";
              common_vendor.index.showToast({
                title: message,
                icon: "none"
              });
              reject(data);
            }
          } catch (e) {
            common_vendor.index.__f__("error", "at utils/request.js:172", "解析上传响应失败:", e);
            reject({ message: "上传失败" });
          }
        },
        fail: (err) => {
          common_vendor.index.__f__("error", "at utils/request.js:177", "上传失败:", err);
          common_vendor.index.showToast({
            title: "上传失败，请重试",
            icon: "none"
          });
          reject(err);
        }
      });
    });
  }
};
exports.BASE_URL = BASE_URL;
exports.request = request$1;
//# sourceMappingURL=../../.sourcemap/mp-weixin/utils/request.js.map
