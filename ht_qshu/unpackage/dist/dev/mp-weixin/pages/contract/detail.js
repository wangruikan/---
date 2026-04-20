"use strict";
const common_vendor = require("../../common/vendor.js");
const api_contract = require("../../api/contract.js");
const _sfc_main = {
  data() {
    return {
      contractId: null,
      contract: null,
      loading: false,
      previewImages: [],
      // PDF转换后的图片数组
      // 签名相关
      showSignPopup: false,
      // 是否显示签名弹窗
      ctx: null,
      canvasWidth: 0,
      canvasHeight: 0,
      lastPoint: null,
      hasSigned: false
    };
  },
  onLoad(options) {
    if (options.id) {
      this.contractId = options.id;
      this.loadContractDetail();
    }
  },
  methods: {
    // 加载合同详情
    async loadContractDetail() {
      this.loading = true;
      try {
        const res = await api_contract.getContractDetail(this.contractId);
        common_vendor.index.__f__("log", "at pages/contract/detail.vue:170", "合同详情:", res.data.contract);
        if (res.success) {
          this.contract = res.data.contract;
          if (res.data.preview_images && res.data.preview_images.length > 0) {
            this.previewImages = res.data.preview_images;
            common_vendor.index.__f__("log", "at pages/contract/detail.vue:178", "PDF预览图片:", this.previewImages);
          }
        }
      } catch (error) {
        common_vendor.index.__f__("error", "at pages/contract/detail.vue:182", "加载合同详情失败:", error);
        common_vendor.index.showToast({
          title: "加载失败",
          icon: "none"
        });
      } finally {
        this.loading = false;
      }
    },
    // 直接打开签名弹窗（签名位置由后端预设决定）
    handleDirectSign() {
      common_vendor.index.__f__("log", "at pages/contract/detail.vue:194", "直接打开签名弹窗");
      this.showSignPopup = true;
      this.$nextTick(() => {
        setTimeout(() => {
          this.initCanvas();
        }, 300);
      });
    },
    // 初始化Canvas
    initCanvas() {
      const query = common_vendor.index.createSelectorQuery().in(this);
      query.select(".sign-canvas").boundingClientRect((data) => {
        if (!data) {
          common_vendor.index.__f__("error", "at pages/contract/detail.vue:212", "Canvas元素未找到");
          return;
        }
        this.canvasWidth = data.width;
        this.canvasHeight = data.height;
        common_vendor.index.__f__("log", "at pages/contract/detail.vue:219", "Canvas尺寸:", this.canvasWidth, "x", this.canvasHeight);
        this.ctx = common_vendor.index.createCanvasContext("signCanvas", this);
        this.ctx.setStrokeStyle("#000");
        this.ctx.setLineWidth(3);
        this.ctx.setLineCap("round");
        this.ctx.setLineJoin("round");
        this.ctx.setFillStyle("#ffffff");
        this.ctx.fillRect(0, 0, this.canvasWidth, this.canvasHeight);
        this.ctx.draw();
        common_vendor.index.__f__("log", "at pages/contract/detail.vue:232", "Canvas初始化成功");
      }).exec();
    },
    // 开始触摸
    touchStart(e) {
      if (!this.ctx)
        return;
      const touch = e.touches[0];
      this.lastPoint = {
        x: touch.x,
        y: touch.y
      };
      this.hasSigned = true;
    },
    // 移动触摸
    touchMove(e) {
      if (!this.ctx || !this.lastPoint)
        return;
      const touch = e.touches[0];
      const currentPoint = {
        x: touch.x,
        y: touch.y
      };
      this.ctx.beginPath();
      this.ctx.moveTo(this.lastPoint.x, this.lastPoint.y);
      this.ctx.lineTo(currentPoint.x, currentPoint.y);
      this.ctx.stroke();
      this.ctx.draw(true);
      this.lastPoint = currentPoint;
    },
    // 结束触摸
    touchEnd() {
      this.lastPoint = null;
    },
    // 清空签名
    clearSign() {
      if (!this.ctx)
        return;
      this.ctx.setFillStyle("#ffffff");
      this.ctx.fillRect(0, 0, this.canvasWidth, this.canvasHeight);
      this.ctx.draw();
      this.hasSigned = false;
    },
    // 确认签名
    confirmSign() {
      if (!this.hasSigned) {
        common_vendor.index.showToast({
          title: "请先签署您的姓名",
          icon: "none"
        });
        return;
      }
      common_vendor.index.showModal({
        title: "身份验证",
        content: "请输入您的身份证后4位以确认签署",
        editable: true,
        placeholderText: "请输入身份证后4位",
        success: (res) => {
          if (res.confirm && res.content) {
            this.getSignatureImage(res.content);
          }
        }
      });
    },
    // 获取签名图片
    async getSignatureImage(idLast4) {
      common_vendor.index.showLoading({ title: "处理签名..." });
      common_vendor.index.canvasToTempFilePath({
        canvasId: "signCanvas",
        success: (res) => {
          common_vendor.index.__f__("log", "at pages/contract/detail.vue:313", "签名图片路径:", res.tempFilePath);
          this.convertImageToBase64(res.tempFilePath, idLast4);
        },
        fail: (err) => {
          common_vendor.index.__f__("error", "at pages/contract/detail.vue:317", "生成签名图片失败:", err);
          common_vendor.index.hideLoading();
          common_vendor.index.showToast({
            title: "签名处理失败",
            icon: "none"
          });
        }
      }, this);
    },
    // 将图片转为base64
    convertImageToBase64(filePath, idLast4) {
      const fs = common_vendor.index.getFileSystemManager();
      fs.readFile({
        filePath,
        encoding: "base64",
        success: (res) => {
          const base64 = "data:image/png;base64," + res.data;
          common_vendor.index.__f__("log", "at pages/contract/detail.vue:335", "Base64签名生成成功");
          this.submitSign(idLast4, base64);
        },
        fail: (err) => {
          common_vendor.index.__f__("error", "at pages/contract/detail.vue:339", "转换base64失败:", err);
          common_vendor.index.hideLoading();
          common_vendor.index.showToast({
            title: "签名处理失败",
            icon: "none"
          });
        }
      });
    },
    // 提交签署（签名位置由后端根据预设位置处理）
    async submitSign(idLast4, signatureBase64) {
      var _a;
      common_vendor.index.showLoading({ title: "提交签署中..." });
      try {
        common_vendor.index.__f__("log", "at pages/contract/detail.vue:354", "提交签名数据（位置由后端预设决定）");
        const res = await api_contract.signContract(this.contractId, {
          id_last_4: idLast4,
          signature_image: signatureBase64
          // 签名位置不再由前端传递，后端会从 signature_positions 字段获取预设位置
        });
        if (res.success) {
          common_vendor.index.hideLoading();
          common_vendor.index.showToast({
            title: "签署成功",
            icon: "success",
            duration: 2e3
          });
          this.showSignPopup = false;
          setTimeout(() => {
            common_vendor.index.navigateBack();
          }, 2e3);
        }
      } catch (error) {
        common_vendor.index.__f__("error", "at pages/contract/detail.vue:379", "签署失败:", error);
        common_vendor.index.hideLoading();
        common_vendor.index.showToast({
          title: ((_a = error.data) == null ? void 0 : _a.message) || "签署失败",
          icon: "none"
        });
      }
    },
    // 关闭签名弹窗
    closeSignPopup() {
      this.showSignPopup = false;
    },
    // 拒绝合同
    handleReject() {
      common_vendor.index.showModal({
        title: "拒绝合同",
        content: "请输入拒绝原因",
        editable: true,
        placeholderText: "请输入拒绝原因",
        success: async (res) => {
          if (res.confirm && res.content) {
            await this.submitReject(res.content);
          }
        }
      });
    },
    // 提交拒绝
    async submitReject(reason) {
      common_vendor.index.showLoading({ title: "提交中..." });
      try {
        const res = await api_contract.rejectContract(this.contractId, {
          reason
        });
        if (res.success) {
          common_vendor.index.showToast({
            title: "已拒绝",
            icon: "success"
          });
          setTimeout(() => {
            common_vendor.index.navigateBack();
          }, 1500);
        }
      } catch (error) {
        common_vendor.index.__f__("error", "at pages/contract/detail.vue:429", "拒绝失败:", error);
      } finally {
        common_vendor.index.hideLoading();
      }
    },
    // 预览PDF（已签署状态）
    handlePreviewPDF() {
      if (!this.contract || !this.contract.file_url)
        return;
      common_vendor.index.showLoading({ title: "准备预览..." });
      common_vendor.index.downloadFile({
        url: this.contract.file_url,
        success: (res) => {
          if (res.statusCode === 200) {
            common_vendor.index.openDocument({
              filePath: res.tempFilePath,
              fileType: "pdf",
              success: () => {
                common_vendor.index.hideLoading();
              },
              fail: (err) => {
                common_vendor.index.__f__("error", "at pages/contract/detail.vue:452", "打开文档失败:", err);
                common_vendor.index.hideLoading();
                common_vendor.index.showToast({
                  title: "PDF预览失败",
                  icon: "none"
                });
              }
            });
          }
        },
        fail: (err) => {
          common_vendor.index.__f__("error", "at pages/contract/detail.vue:463", "下载失败:", err);
          common_vendor.index.hideLoading();
        }
      });
    },
    getContractTypeText(type) {
      const types = {
        labor: "劳动合同",
        termination: "解除协议合同",
        retirement: "退休解除协议合同"
      };
      return types[type] || type;
    },
    getStatusText(status) {
      const statuses = {
        draft: "草稿",
        pending_sign: "待签署",
        employee_signed: "已签署",
        completed: "已完成",
        rejected: "已拒绝"
      };
      return statuses[status] || status;
    },
    getStatusClass(status) {
      return `status-${status}`;
    },
    formatDateTime(dateTimeStr) {
      if (!dateTimeStr)
        return "-";
      const date = new Date(dateTimeStr);
      const y = date.getFullYear();
      const m = String(date.getMonth() + 1).padStart(2, "0");
      const d = String(date.getDate()).padStart(2, "0");
      const h = String(date.getHours()).padStart(2, "0");
      const min = String(date.getMinutes()).padStart(2, "0");
      return `${y}-${m}-${d} ${h}:${min}`;
    }
  }
};
function _sfc_render(_ctx, _cache, $props, $setup, $data, $options) {
  var _a;
  return common_vendor.e({
    a: $data.loading
  }, $data.loading ? {} : $data.contract ? common_vendor.e({
    c: common_vendor.t($options.getContractTypeText($data.contract.contract_type)),
    d: common_vendor.t($options.getStatusText($data.contract.status)),
    e: common_vendor.n($options.getStatusClass($data.contract.status)),
    f: common_vendor.t($data.contract.original_filename),
    g: common_vendor.t(((_a = $data.contract.creator) == null ? void 0 : _a.name) || "系统"),
    h: common_vendor.t($options.formatDateTime($data.contract.uploaded_at)),
    i: $data.contract.status === "pending_sign" && $data.previewImages.length > 0
  }, $data.contract.status === "pending_sign" && $data.previewImages.length > 0 ? {
    j: common_vendor.f($data.previewImages, (imgUrl, index, i0) => {
      return {
        a: common_vendor.t(index + 1),
        b: imgUrl,
        c: index
      };
    }),
    k: common_vendor.o((...args) => $options.handleDirectSign && $options.handleDirectSign(...args))
  } : $data.contract.status === "pending_sign" ? {
    m: common_vendor.o((...args) => $options.handlePreviewPDF && $options.handlePreviewPDF(...args)),
    n: common_vendor.o((...args) => $options.handleDirectSign && $options.handleDirectSign(...args))
  } : {
    o: common_vendor.o((...args) => $options.handlePreviewPDF && $options.handlePreviewPDF(...args))
  }, {
    l: $data.contract.status === "pending_sign",
    p: $data.showSignPopup
  }, $data.showSignPopup ? {
    q: common_vendor.o((...args) => $options.closeSignPopup && $options.closeSignPopup(...args)),
    r: common_vendor.o((...args) => $options.touchStart && $options.touchStart(...args)),
    s: common_vendor.o((...args) => $options.touchMove && $options.touchMove(...args)),
    t: common_vendor.o((...args) => $options.touchEnd && $options.touchEnd(...args)),
    v: common_vendor.o((...args) => $options.clearSign && $options.clearSign(...args)),
    w: common_vendor.o((...args) => $options.confirmSign && $options.confirmSign(...args)),
    x: common_vendor.o(() => {
    }),
    y: common_vendor.o((...args) => $options.closeSignPopup && $options.closeSignPopup(...args))
  } : {}, {
    z: $data.contract.status === "employee_signed"
  }, $data.contract.status === "employee_signed" ? {
    A: common_vendor.t($options.formatDateTime($data.contract.employee_signed_at))
  } : $data.contract.status === "completed" ? {
    C: common_vendor.t($options.formatDateTime($data.contract.completed_at))
  } : $data.contract.status === "pending_sign" ? {
    E: common_vendor.o((...args) => $options.handleReject && $options.handleReject(...args))
  } : {}, {
    B: $data.contract.status === "completed",
    D: $data.contract.status === "pending_sign"
  }) : {}, {
    b: $data.contract
  });
}
const MiniProgramPage = /* @__PURE__ */ common_vendor._export_sfc(_sfc_main, [["render", _sfc_render], ["__scopeId", "data-v-450aad6f"]]);
wx.createPage(MiniProgramPage);
//# sourceMappingURL=../../../.sourcemap/mp-weixin/pages/contract/detail.js.map
