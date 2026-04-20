"use strict";
const common_vendor = require("../../common/vendor.js");
const api_document = require("../../api/document.js");
const _sfc_main = {
  data() {
    return {
      loading: false,
      documents: [],
      employeeInfo: null
    };
  },
  computed: {
    totalCount() {
      return this.documents.length;
    },
    uploadedCount() {
      return this.documents.filter((d) => d.uploaded).length;
    },
    pendingCount() {
      return this.documents.filter((d) => !d.uploaded).length;
    }
  },
  onLoad() {
    this.employeeInfo = common_vendor.index.getStorageSync("employeeInfo");
    if (!this.employeeInfo) {
      common_vendor.index.showToast({
        title: "请先登录",
        icon: "none"
      });
      setTimeout(() => {
        common_vendor.index.reLaunch({
          url: "/pages/login/login"
        });
      }, 1500);
      return;
    }
    this.loadDocuments();
  },
  onShow() {
    if (this.employeeInfo) {
      this.loadDocuments();
    }
  },
  methods: {
    async loadDocuments() {
      this.loading = true;
      try {
        const res = await api_document.getMyDocuments();
        if (res.success) {
          this.documents = res.data || [];
        } else {
          common_vendor.index.showToast({
            title: res.message || "加载失败",
            icon: "none"
          });
        }
      } catch (error) {
        common_vendor.index.__f__("error", "at pages/document/upload.vue:151", "加载资料列表失败:", error);
        common_vendor.index.showToast({
          title: "加载失败，请重试",
          icon: "none"
        });
      } finally {
        this.loading = false;
      }
    },
    async handleUpload(item) {
      try {
        let sourceType = ["album", "camera"];
        let extension = [];
        if (item.document_type === "image") {
          sourceType = ["album", "camera"];
          extension = ["jpg", "jpeg", "png", "gif", "webp"];
        } else if (item.document_type === "pdf") {
          sourceType = ["album"];
          extension = ["pdf"];
        } else if (item.document_type === "document") {
          sourceType = ["album"];
          extension = ["pdf", "doc", "docx", "xls", "xlsx"];
        } else {
          sourceType = ["album", "camera"];
          extension = ["jpg", "jpeg", "png", "gif", "webp", "pdf", "doc", "docx", "xls", "xlsx"];
        }
        const chooseResult = await this.chooseFiles(sourceType, extension);
        if (!chooseResult || chooseResult.length === 0) {
          return;
        }
        common_vendor.index.showLoading({
          title: `正在上传(0/${chooseResult.length})...`,
          mask: true
        });
        let successCount = 0;
        let failCount = 0;
        for (let i = 0; i < chooseResult.length; i++) {
          common_vendor.index.showLoading({
            title: `正在上传(${i + 1}/${chooseResult.length})...`,
            mask: true
          });
          try {
            const uploadRes = await api_document.uploadDocument(item.config_id, chooseResult[i].tempFilePath);
            if (uploadRes.success) {
              successCount++;
            } else {
              failCount++;
            }
          } catch (err) {
            failCount++;
            common_vendor.index.__f__("error", "at pages/document/upload.vue:213", "上传失败:", err);
          }
        }
        common_vendor.index.hideLoading();
        if (successCount > 0) {
          common_vendor.index.showToast({
            title: `成功上传${successCount}个文件${failCount > 0 ? `，${failCount}个失败` : ""}`,
            icon: successCount === chooseResult.length ? "success" : "none"
          });
          this.loadDocuments();
        } else {
          common_vendor.index.showToast({
            title: "上传失败",
            icon: "none"
          });
        }
      } catch (error) {
        common_vendor.index.hideLoading();
        common_vendor.index.__f__("error", "at pages/document/upload.vue:234", "上传失败:", error);
        common_vendor.index.showToast({
          title: error.message || "上传失败",
          icon: "none"
        });
      }
    },
    // 选择多个文件
    chooseFiles(sourceType, extension) {
      return new Promise((resolve, reject) => {
        const imageExtensions = ["jpg", "jpeg", "png", "gif", "webp"];
        const isOnlyImage = extension.every((ext) => imageExtensions.includes(ext));
        const hasDocument = extension.some((ext) => ["pdf", "doc", "docx", "xls", "xlsx"].includes(ext));
        if (isOnlyImage && !hasDocument) {
          common_vendor.index.chooseImage({
            count: 9,
            // 最多选择9张图片
            sourceType,
            success: (res) => {
              common_vendor.index.__f__("log", "at pages/document/upload.vue:255", "选择图片成功:", res);
              resolve(res.tempFilePaths.map((path) => ({ tempFilePath: path })));
            },
            fail: (err) => {
              common_vendor.index.__f__("error", "at pages/document/upload.vue:259", "选择图片失败:", err);
              reject(err);
            }
          });
        } else {
          common_vendor.index.chooseMessageFile({
            count: 9,
            // 最多选择9个文件
            type: "file",
            extension,
            success: (res) => {
              common_vendor.index.__f__("log", "at pages/document/upload.vue:270", "选择文件成功:", res);
              resolve(res.tempFiles.map((file) => ({ tempFilePath: file.path })));
            },
            fail: (err) => {
              common_vendor.index.__f__("error", "at pages/document/upload.vue:274", "选择文件失败:", err);
              if (extension.some((ext) => imageExtensions.includes(ext))) {
                common_vendor.index.__f__("log", "at pages/document/upload.vue:277", "尝试使用chooseImage...");
                common_vendor.index.chooseImage({
                  count: 9,
                  sourceType,
                  success: (imgRes) => {
                    common_vendor.index.__f__("log", "at pages/document/upload.vue:282", "使用chooseImage成功:", imgRes);
                    resolve(imgRes.tempFilePaths.map((path) => ({ tempFilePath: path })));
                  },
                  fail: (imgErr) => {
                    common_vendor.index.__f__("error", "at pages/document/upload.vue:286", "chooseImage也失败:", imgErr);
                    common_vendor.index.showToast({
                      title: "选择文件失败，请重试",
                      icon: "none"
                    });
                    reject(imgErr);
                  }
                });
              } else {
                common_vendor.index.showToast({
                  title: "选择文件失败，请重试",
                  icon: "none"
                });
                reject(err);
              }
            }
          });
        }
      });
    },
    async handleDelete(file, item) {
      common_vendor.index.showModal({
        title: "确认删除",
        content: `确定要删除文件"${file.original_filename}"吗？`,
        success: async (res) => {
          if (res.confirm) {
            common_vendor.index.showLoading({ title: "删除中...", mask: true });
            try {
              const result = await api_document.deleteDocument(file.id);
              common_vendor.index.hideLoading();
              if (result.success) {
                common_vendor.index.showToast({ title: "删除成功", icon: "success" });
                this.loadDocuments();
              } else {
                common_vendor.index.showToast({ title: result.message || "删除失败", icon: "none" });
              }
            } catch (error) {
              common_vendor.index.hideLoading();
              common_vendor.index.__f__("error", "at pages/document/upload.vue:325", "删除失败:", error);
              common_vendor.index.showToast({ title: "删除失败", icon: "none" });
            }
          }
        }
      });
    },
    getDocumentTypeText(type) {
      const texts = { image: "仅图片", pdf: "仅PDF", document: "文档", all: "所有类型" };
      return texts[type] || type;
    },
    getUploadButtonText(type) {
      if (type === "image")
        return "拍照/选择图片";
      else if (type === "pdf")
        return "选择PDF文件";
      else if (type === "document")
        return "选择文档";
      else
        return "拍照/选择文件";
    },
    formatDateTime(dateTime) {
      if (!dateTime)
        return "-";
      const date = new Date(dateTime);
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, "0");
      const day = String(date.getDate()).padStart(2, "0");
      const hour = String(date.getHours()).padStart(2, "0");
      const minute = String(date.getMinutes()).padStart(2, "0");
      return `${year}-${month}-${day} ${hour}:${minute}`;
    }
  }
};
function _sfc_render(_ctx, _cache, $props, $setup, $data, $options) {
  return common_vendor.e({
    a: common_vendor.t($options.totalCount),
    b: common_vendor.t($options.uploadedCount),
    c: common_vendor.t($options.pendingCount),
    d: common_vendor.f($data.documents, (item, index, i0) => {
      return common_vendor.e({
        a: common_vendor.t(item.document_name),
        b: item.is_required
      }, item.is_required ? {} : {}, {
        c: common_vendor.t($options.getDocumentTypeText(item.document_type)),
        d: common_vendor.t(item.uploaded ? `✓ 已上传(${item.file_count || 1})` : "○ 未上传"),
        e: common_vendor.n(item.uploaded ? "uploaded" : "pending"),
        f: item.files && item.files.length > 0
      }, item.files && item.files.length > 0 ? {
        g: common_vendor.f(item.files, (file, fIndex, i1) => {
          return {
            a: common_vendor.t(file.original_filename),
            b: common_vendor.t(file.file_size_formatted),
            c: common_vendor.o(($event) => $options.handleDelete(file, item), file.id),
            d: file.id
          };
        })
      } : {}, {
        h: common_vendor.t(item.uploaded ? "继续上传" : $options.getUploadButtonText(item.document_type)),
        i: common_vendor.o(($event) => $options.handleUpload(item), item.config_id),
        j: item.config_id,
        k: item.uploaded ? 1 : ""
      });
    }),
    e: !$data.loading && $data.documents.length === 0
  }, !$data.loading && $data.documents.length === 0 ? {} : {}, {
    f: $data.loading
  }, $data.loading ? {} : {});
}
const MiniProgramPage = /* @__PURE__ */ common_vendor._export_sfc(_sfc_main, [["render", _sfc_render], ["__scopeId", "data-v-17eb5e5a"]]);
wx.createPage(MiniProgramPage);
//# sourceMappingURL=../../../.sourcemap/mp-weixin/pages/document/upload.js.map
