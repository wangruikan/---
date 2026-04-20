"use strict";
const common_vendor = require("../../common/vendor.js");
const api_resignation = require("../../api/resignation.js");
const utils_request = require("../../utils/request.js");
const _sfc_main = {
  data() {
    return {
      fileList: [],
      loading: false,
      uploading: false
    };
  },
  onLoad() {
    this.loadFileList();
  },
  onShow() {
    this.loadFileList();
  },
  // 下拉刷新
  onPullDownRefresh() {
    this.loadFileList().then(() => {
      common_vendor.index.stopPullDownRefresh();
    });
  },
  methods: {
    // 加载文件列表
    async loadFileList() {
      this.loading = true;
      try {
        const res = await api_resignation.getMyResignationCertificates();
        if (res.success) {
          this.fileList = res.data || [];
        } else {
          common_vendor.index.showToast({
            title: res.message || "加载失败",
            icon: "none"
          });
        }
      } catch (error) {
        common_vendor.index.__f__("error", "at pages/resignation/index.vue:115", "加载离职证明列表失败:", error);
        common_vendor.index.showToast({
          title: "加载失败，请重试",
          icon: "none"
        });
      } finally {
        this.loading = false;
      }
    },
    // 选择文件
    chooseFile() {
      common_vendor.index.chooseImage({
        count: 5,
        sizeType: ["original", "compressed"],
        sourceType: ["album", "camera"],
        success: (res) => {
          const tempFilePaths = res.tempFilePaths;
          this.uploadFiles(tempFilePaths);
        }
      });
    },
    // 上传文件
    async uploadFiles(filePaths) {
      if (filePaths.length === 0)
        return;
      this.uploading = true;
      common_vendor.index.showLoading({
        title: "上传中...",
        mask: true
      });
      let successCount = 0;
      let failCount = 0;
      for (let i = 0; i < filePaths.length; i++) {
        try {
          const res = await api_resignation.uploadResignationCertificate(filePaths[i]);
          if (res.success) {
            successCount++;
          } else {
            failCount++;
            common_vendor.index.__f__("error", "at pages/resignation/index.vue:158", "上传失败:", res.message);
          }
        } catch (error) {
          failCount++;
          common_vendor.index.__f__("error", "at pages/resignation/index.vue:162", "上传失败:", error);
        }
      }
      common_vendor.index.hideLoading();
      this.uploading = false;
      if (successCount > 0) {
        common_vendor.index.showToast({
          title: `成功上传 ${successCount} 个文件`,
          icon: "success"
        });
        this.loadFileList();
      }
      if (failCount > 0) {
        common_vendor.index.showToast({
          title: `${failCount} 个文件上传失败`,
          icon: "none"
        });
      }
    },
    // 预览文件
    previewFile(item) {
      const fileType = item.file_type.toLowerCase();
      if (fileType.includes("image") || fileType.includes("jpg") || fileType.includes("png") || fileType.includes("jpeg")) {
        const imageUrl = this.getFileUrl(item.file_path);
        common_vendor.index.previewImage({
          urls: [imageUrl],
          current: imageUrl
        });
      } else if (fileType.includes("pdf")) {
        common_vendor.index.showLoading({
          title: "加载中...",
          mask: true
        });
        const fileUrl = this.getFileUrl(item.file_path);
        common_vendor.index.downloadFile({
          url: fileUrl,
          success: (res) => {
            if (res.statusCode === 200) {
              common_vendor.index.openDocument({
                filePath: res.tempFilePath,
                fileType: "pdf",
                success: () => {
                  common_vendor.index.__f__("log", "at pages/resignation/index.vue:214", "打开文档成功");
                },
                fail: (err) => {
                  common_vendor.index.__f__("error", "at pages/resignation/index.vue:217", "打开文档失败:", err);
                  common_vendor.index.showToast({
                    title: "无法打开文件",
                    icon: "none"
                  });
                }
              });
            }
          },
          fail: (err) => {
            common_vendor.index.__f__("error", "at pages/resignation/index.vue:227", "下载失败:", err);
            common_vendor.index.showToast({
              title: "下载失败",
              icon: "none"
            });
          },
          complete: () => {
            common_vendor.index.hideLoading();
          }
        });
      } else {
        common_vendor.index.showToast({
          title: "不支持预览此文件类型",
          icon: "none"
        });
      }
    },
    // 删除文件
    deleteFile(item) {
      common_vendor.index.showModal({
        title: "提示",
        content: `确定要删除文件 "${item.file_name}" 吗？`,
        success: async (res) => {
          if (res.confirm) {
            common_vendor.index.showLoading({
              title: "删除中...",
              mask: true
            });
            try {
              const result = await api_resignation.deleteResignationCertificate(item.id);
              if (result.success) {
                common_vendor.index.showToast({
                  title: "删除成功",
                  icon: "success"
                });
                this.loadFileList();
              } else {
                common_vendor.index.showToast({
                  title: result.message || "删除失败",
                  icon: "none"
                });
              }
            } catch (error) {
              common_vendor.index.__f__("error", "at pages/resignation/index.vue:273", "删除失败:", error);
              common_vendor.index.showToast({
                title: "删除失败，请重试",
                icon: "none"
              });
            } finally {
              common_vendor.index.hideLoading();
            }
          }
        }
      });
    },
    // 获取文件图标
    getFileIcon(fileType) {
      if (!fileType)
        return "📄";
      const type = fileType.toLowerCase();
      if (type.includes("image") || type.includes("jpg") || type.includes("png") || type.includes("jpeg")) {
        return "🖼️";
      } else if (type.includes("pdf")) {
        return "📕";
      } else {
        return "📄";
      }
    },
    // 格式化文件大小
    formatFileSize(bytes) {
      if (!bytes || bytes === 0)
        return "0 B";
      const k = 1024;
      const sizes = ["B", "KB", "MB", "GB"];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return (bytes / Math.pow(k, i)).toFixed(2) + " " + sizes[i];
    },
    // 格式化时间
    formatTime(dateString) {
      if (!dateString)
        return "";
      const date = new Date(dateString);
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, "0");
      const day = String(date.getDate()).padStart(2, "0");
      const hour = String(date.getHours()).padStart(2, "0");
      const minute = String(date.getMinutes()).padStart(2, "0");
      return `${year}-${month}-${day} ${hour}:${minute}`;
    },
    // 获取文件URL
    getFileUrl(filePath) {
      const serverUrl = utils_request.BASE_URL.replace("/api/mini", "");
      return `${serverUrl}/storage/${filePath}`;
    }
  }
};
function _sfc_render(_ctx, _cache, $props, $setup, $data, $options) {
  return common_vendor.e({
    a: common_vendor.t($data.uploading ? "上传中..." : "上传离职证明"),
    b: common_vendor.o((...args) => $options.chooseFile && $options.chooseFile(...args)),
    c: $data.uploading,
    d: $data.fileList.length > 0
  }, $data.fileList.length > 0 ? {
    e: common_vendor.t($data.fileList.length),
    f: common_vendor.f($data.fileList, (item, k0, i0) => {
      return {
        a: common_vendor.t($options.getFileIcon(item.file_type)),
        b: common_vendor.t(item.file_name),
        c: common_vendor.t($options.formatFileSize(item.file_size)),
        d: common_vendor.t($options.formatTime(item.created_at)),
        e: common_vendor.t(item.upload_source === "miniprogram" ? "小程序" : "PC端"),
        f: common_vendor.n(item.upload_source === "miniprogram" ? "tag-primary" : "tag-default"),
        g: common_vendor.o(($event) => $options.previewFile(item), item.id),
        h: common_vendor.o(($event) => $options.deleteFile(item), item.id),
        i: item.id
      };
    })
  } : {}, {
    g: $data.fileList.length === 0 && !$data.loading
  }, $data.fileList.length === 0 && !$data.loading ? {} : {}, {
    h: $data.loading
  }, $data.loading ? {} : {});
}
const MiniProgramPage = /* @__PURE__ */ common_vendor._export_sfc(_sfc_main, [["render", _sfc_render], ["__scopeId", "data-v-ad67a4cc"]]);
wx.createPage(MiniProgramPage);
//# sourceMappingURL=../../../.sourcemap/mp-weixin/pages/resignation/index.js.map
