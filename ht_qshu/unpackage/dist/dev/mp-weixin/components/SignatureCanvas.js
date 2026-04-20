"use strict";
const common_vendor = require("../common/vendor.js");
const utils_request = require("../utils/request.js");
const _sfc_main = {
  name: "SignatureCanvas",
  props: {
    label: {
      type: String,
      default: "本人签名："
    },
    canvasId: {
      type: String,
      default: "signatureCanvas"
    },
    // 已有的签名URL（用于回显）
    value: {
      type: String,
      default: ""
    }
  },
  data() {
    return {
      signatureCtx: null,
      isDrawing: false,
      lastPoint: null,
      hasSignature: false,
      canvasWidth: 0,
      canvasHeight: 200,
      signatureUrl: "",
      signaturePath: ""
    };
  },
  watch: {
    value: {
      immediate: true,
      handler(val) {
        if (val) {
          this.signatureUrl = val;
          this.hasSignature = true;
          if (val.includes("/uploads/signatures/")) {
            const match = val.match(/\/uploads\/signatures\/(.+)$/);
            if (match) {
              this.signaturePath = "uploads/signatures/" + match[1];
            }
          } else if (val.includes("/storage/signatures/")) {
            const match = val.match(/\/storage\/signatures\/(.+)$/);
            if (match) {
              this.signaturePath = "signatures/" + match[1];
            }
          } else if (val.includes("/storage/")) {
            const match = val.match(/\/storage\/(.+)$/);
            if (match) {
              this.signaturePath = match[1];
            }
          }
        }
      }
    }
  },
  mounted() {
    const systemInfo = common_vendor.index.getSystemInfoSync();
    const screenWidth = systemInfo.windowWidth;
    this.canvasWidth = screenWidth - 60;
    this.$nextTick(() => {
      this.initSignatureCanvas();
    });
  },
  methods: {
    // 初始化签名canvas
    initSignatureCanvas() {
      if (!this.signatureCtx) {
        this.signatureCtx = common_vendor.index.createCanvasContext(this.canvasId, this);
      }
      this.signatureCtx.setStrokeStyle("#000000");
      this.signatureCtx.setLineWidth(3);
      this.signatureCtx.setLineCap("round");
      this.signatureCtx.setLineJoin("round");
    },
    // 开始绘制
    handleTouchStart(e) {
      if (!this.signatureCtx) {
        this.initSignatureCanvas();
      }
      this.isDrawing = true;
      this.hasSignature = true;
      const touch = e.touches[0];
      const x = touch.x;
      const y = touch.y;
      this.signatureCtx.beginPath();
      this.signatureCtx.moveTo(x, y);
      this.signatureCtx.lineTo(x, y);
      this.signatureCtx.stroke();
      this.signatureCtx.draw(true);
      this.lastPoint = { x, y };
    },
    // 绘制中
    handleTouchMove(e) {
      if (!this.isDrawing || !this.lastPoint)
        return;
      const touch = e.touches[0];
      const x = touch.x;
      const y = touch.y;
      this.signatureCtx.beginPath();
      this.signatureCtx.moveTo(this.lastPoint.x, this.lastPoint.y);
      this.signatureCtx.lineTo(x, y);
      this.signatureCtx.stroke();
      this.signatureCtx.draw(true);
      this.lastPoint = { x, y };
    },
    // 结束绘制
    handleTouchEnd() {
      if (!this.isDrawing)
        return;
      this.isDrawing = false;
      this.lastPoint = null;
    },
    // 完成签名
    async finishSignature() {
      if (!this.hasSignature) {
        common_vendor.index.showToast({ title: "请先签名", icon: "none" });
        return;
      }
      common_vendor.index.showLoading({ title: "上传中...", mask: true });
      this.saveSignatureToBase64(async (success, base64Data) => {
        if (!success) {
          common_vendor.index.hideLoading();
          common_vendor.index.showToast({ title: "签名生成失败", icon: "none" });
          return;
        }
        try {
          const res = await this.uploadSignature(base64Data);
          common_vendor.index.hideLoading();
          if (res.success) {
            this.signatureUrl = res.data.url;
            this.signaturePath = res.data.path;
            this.$emit("input", this.signatureUrl);
            this.$emit("change", {
              url: this.signatureUrl,
              path: this.signaturePath
            });
            common_vendor.index.showToast({ title: "签名已保存", icon: "success" });
          } else {
            common_vendor.index.showToast({ title: res.message || "上传失败", icon: "none" });
          }
        } catch (error) {
          common_vendor.index.hideLoading();
          common_vendor.index.__f__("error", "at components/SignatureCanvas.vue:199", "上传签名失败:", error);
          common_vendor.index.showToast({ title: "上传失败", icon: "none" });
        }
      });
    },
    // 保存签名为base64
    saveSignatureToBase64(callback) {
      common_vendor.index.canvasToTempFilePath({
        canvasId: this.canvasId,
        fileType: "png",
        quality: 1,
        width: this.canvasWidth,
        height: this.canvasHeight,
        destWidth: this.canvasWidth * 2,
        destHeight: this.canvasHeight * 2,
        success: (res) => {
          common_vendor.index.getFileSystemManager().readFile({
            filePath: res.tempFilePath,
            encoding: "base64",
            success: (data) => {
              const base64Data = "data:image/png;base64," + data.data;
              if (callback)
                callback(true, base64Data);
            },
            fail: (error) => {
              common_vendor.index.__f__("error", "at components/SignatureCanvas.vue:224", "读取签名失败:", error);
              if (callback)
                callback(false, null);
            }
          });
        },
        fail: (error) => {
          common_vendor.index.__f__("error", "at components/SignatureCanvas.vue:230", "导出签名失败:", error);
          if (callback)
            callback(false, null);
        }
      }, this);
    },
    // 上传签名到服务器
    uploadSignature(base64Data) {
      return utils_request.request.post("/upload-signature", { signature: base64Data });
    },
    // 清除签名
    clearSignature() {
      this.signatureUrl = "";
      this.signaturePath = "";
      this.isDrawing = false;
      this.lastPoint = null;
      this.hasSignature = false;
      this.$emit("input", "");
      this.$emit("change", { url: "", path: "" });
      this.$nextTick(() => {
        if (this.signatureCtx) {
          this.signatureCtx.clearRect(0, 0, this.canvasWidth, this.canvasHeight);
          this.signatureCtx.draw();
        }
        this.initSignatureCanvas();
      });
    },
    // 获取签名路径（供父组件调用）
    getSignaturePath() {
      return this.signaturePath;
    }
  }
};
function _sfc_render(_ctx, _cache, $props, $setup, $data, $options) {
  return common_vendor.e({
    a: common_vendor.t($props.label),
    b: !$data.signatureUrl
  }, !$data.signatureUrl ? common_vendor.e({
    c: $props.canvasId,
    d: $data.canvasWidth + "px",
    e: $data.canvasHeight + "px",
    f: common_vendor.o((...args) => $options.handleTouchStart && $options.handleTouchStart(...args)),
    g: common_vendor.o((...args) => $options.handleTouchMove && $options.handleTouchMove(...args)),
    h: common_vendor.o((...args) => $options.handleTouchEnd && $options.handleTouchEnd(...args)),
    i: common_vendor.o((...args) => $options.clearSignature && $options.clearSignature(...args)),
    j: $data.hasSignature
  }, $data.hasSignature ? {
    k: common_vendor.o((...args) => $options.finishSignature && $options.finishSignature(...args))
  } : {}) : {
    l: $data.signatureUrl,
    m: common_vendor.o((...args) => $options.clearSignature && $options.clearSignature(...args))
  });
}
const Component = /* @__PURE__ */ common_vendor._export_sfc(_sfc_main, [["render", _sfc_render], ["__scopeId", "data-v-51c97eeb"]]);
wx.createComponent(Component);
//# sourceMappingURL=../../.sourcemap/mp-weixin/components/SignatureCanvas.js.map
