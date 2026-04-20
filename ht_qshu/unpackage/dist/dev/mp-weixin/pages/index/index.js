"use strict";
const common_vendor = require("../../common/vendor.js");
const api_contract = require("../../api/contract.js");
const api_registration = require("../../api/registration.js");
const api_onboarding = require("../../api/onboarding.js");
const utils_request = require("../../utils/request.js");
const _sfc_main = {
  data() {
    return {
      employeeName: "",
      pendingContracts: [],
      // 须知弹窗相关
      showNoticeModal: false,
      hasAgreed: false,
      noticeFileName: "",
      noticeFileUrl: "",
      currentContractId: null
    };
  },
  computed: {
    welcomeText() {
      const hour = (/* @__PURE__ */ new Date()).getHours();
      if (hour < 6)
        return "夜深了，注意休息";
      if (hour < 12)
        return "早上好！";
      if (hour < 18)
        return "下午好！";
      return "晚上好！";
    }
  },
  onShow() {
    this.checkLogin();
    this.loadData();
  },
  methods: {
    checkLogin() {
      const employeeInfo = common_vendor.index.getStorageSync("employeeInfo");
      if (!employeeInfo) {
        common_vendor.index.reLaunch({
          url: "/pages/login/login"
        });
        return;
      }
      this.employeeName = employeeInfo.name;
    },
    async loadData() {
      try {
        common_vendor.index.showLoading({ title: "加载中..." });
        const res = await api_contract.getPendingContracts();
        if (res.success) {
          this.pendingContracts = res.data;
        }
      } catch (error) {
        common_vendor.index.__f__("error", "at pages/index/index.vue:152", "加载失败:", error);
      } finally {
        common_vendor.index.hideLoading();
      }
    },
    getContractTypeText(type) {
      const types = {
        labor: "劳动合同",
        termination: "解除协议合同",
        retirement: "退休解除协议合同",
        other: "其他合同"
      };
      return types[type] || type;
    },
    formatTime(time) {
      if (!time)
        return "";
      const date = new Date(time);
      const month = date.getMonth() + 1;
      const day = date.getDate();
      const hour = date.getHours();
      const minute = date.getMinutes();
      return `${month}月${day}日 ${hour}:${minute < 10 ? "0" + minute : minute}`;
    },
    async goToDetail(id) {
      common_vendor.index.__f__("log", "at pages/index/index.vue:179", "🖱️ 首页点击合同，ID:", id);
      const contract = this.pendingContracts.find((c) => c.id === id);
      common_vendor.index.__f__("log", "at pages/index/index.vue:184", "📋 合同对象:", contract);
      if (contract && contract.status === "pending_sign") {
        const canSign = await this.checkBeforeSign();
        if (!canSign) {
          return;
        }
        await this.checkNoticeAndSign(id);
      } else {
        common_vendor.index.navigateTo({
          url: `/pages/contract/detail?id=${id}`
        });
      }
    },
    // 签署前检查登记表和资料
    async checkBeforeSign() {
      try {
        common_vendor.index.showLoading({ title: "检查中..." });
        let hasRegistrationForm = false;
        try {
          const regRes = await api_registration.getMyRegistrationForm();
          if (regRes.success && regRes.data) {
            hasRegistrationForm = true;
          }
        } catch (error) {
          common_vendor.index.__f__("log", "at pages/index/index.vue:216", "未找到从业人员登记表");
        }
        let hasOnboardingForm = false;
        try {
          const onbRes = await api_onboarding.getMyOnboardingForm();
          if (onbRes.success && onbRes.data) {
            hasOnboardingForm = true;
          }
        } catch (error) {
          common_vendor.index.__f__("log", "at pages/index/index.vue:227", "未找到入职登记表");
        }
        common_vendor.index.hideLoading();
        if (!hasRegistrationForm && !hasOnboardingForm) {
          common_vendor.index.showModal({
            title: "提示",
            content: "请先填写从业人员登记表或入职登记表",
            showCancel: false,
            confirmText: "知道了"
          });
          return false;
        }
        const documentsComplete = await this.checkDocumentsComplete();
        if (!documentsComplete) {
          common_vendor.index.showModal({
            title: "提示",
            content: "请先上传完整资料",
            showCancel: false,
            confirmText: "知道了"
          });
          return false;
        }
        return true;
      } catch (error) {
        common_vendor.index.hideLoading();
        common_vendor.index.__f__("error", "at pages/index/index.vue:259", "检查失败:", error);
        common_vendor.index.showToast({
          title: "检查失败，请重试",
          icon: "none"
        });
        return false;
      }
    },
    // 检查资料是否上传完成
    async checkDocumentsComplete() {
      try {
        const res = await utils_request.request.get("/check-documents");
        if (res && res.success) {
          return res.data.complete || false;
        }
        return true;
      } catch (error) {
        common_vendor.index.__f__("error", "at pages/index/index.vue:282", "检查资料失败:", error);
        return true;
      }
    },
    // 检查须知并签署
    async checkNoticeAndSign(contractId) {
      common_vendor.index.__f__("log", "at pages/index/index.vue:290", "🔍 准备显示须知弹窗，合同ID:", contractId);
      common_vendor.index.hideLoading();
      this.showNoticeModal = true;
      this.hasAgreed = false;
      this.currentContractId = contractId;
      this.noticeFileName = "劳动合同须知.pdf";
      this.noticeFileUrl = "";
      common_vendor.index.__f__("log", "at pages/index/index.vue:302", "✅ 弹窗已显示");
      try {
        const res = await api_contract.getContractDetail(contractId);
        common_vendor.index.__f__("log", "at pages/index/index.vue:308", "📦 API返回:", res);
        if (res && res.success) {
          const { notice_file } = res.data;
          if (notice_file) {
            common_vendor.index.__f__("log", "at pages/index/index.vue:314", "📄 加载到须知文件:", notice_file);
            this.noticeFileName = notice_file.name;
            this.noticeFileUrl = notice_file.view_url;
          } else {
            common_vendor.index.__f__("log", "at pages/index/index.vue:318", "📄 没有须知文件");
          }
        } else {
          common_vendor.index.__f__("log", "at pages/index/index.vue:321", "📄 API返回失败或无数据");
        }
      } catch (error) {
        common_vendor.index.__f__("error", "at pages/index/index.vue:324", "❌ 加载须知文件失败:", error);
      }
    },
    // 阅读须知文件
    handleReadNotice() {
      if (!this.noticeFileUrl) {
        common_vendor.index.showToast({
          title: "暂无须知文件",
          icon: "none"
        });
        return;
      }
      common_vendor.index.showLoading({ title: "加载中..." });
      common_vendor.index.downloadFile({
        url: this.noticeFileUrl,
        success: (res) => {
          common_vendor.index.hideLoading();
          if (res.statusCode === 200) {
            common_vendor.index.openDocument({
              filePath: res.tempFilePath,
              fileType: "pdf",
              showMenu: true,
              success: () => {
                common_vendor.index.__f__("log", "at pages/index/index.vue:353", "文件打开成功");
              },
              fail: (err) => {
                common_vendor.index.__f__("error", "at pages/index/index.vue:356", "文件打开失败:", err);
                common_vendor.index.showToast({
                  title: "文件打开失败",
                  icon: "error"
                });
              }
            });
          }
        },
        fail: (err) => {
          common_vendor.index.hideLoading();
          common_vendor.index.__f__("error", "at pages/index/index.vue:367", "文件下载失败:", err);
          common_vendor.index.showToast({
            title: "文件下载失败",
            icon: "error"
          });
        }
      });
    },
    // 勾选同意
    handleAgreeChange(e) {
      this.hasAgreed = e.detail.value.length > 0;
    },
    // 确认签署（需要勾选）
    handleConfirmSign() {
      if (!this.hasAgreed) {
        common_vendor.index.showToast({
          title: "请先勾选同意",
          icon: "none"
        });
        return;
      }
      this.goToSignPage(this.currentContractId);
      this.closeNoticeModal();
    },
    // 关闭须知弹窗
    closeNoticeModal() {
      this.showNoticeModal = false;
      this.hasAgreed = false;
      this.noticeFileName = "";
      this.noticeFileUrl = "";
      this.currentContractId = null;
    },
    // 进入签署页面
    goToSignPage(contractId) {
      common_vendor.index.navigateTo({
        url: `/pages/contract/sign-h5?id=${contractId}`
      });
    },
    goToContracts() {
      common_vendor.index.switchTab({
        url: "/pages/contract/list"
      });
    },
    goToMy() {
      common_vendor.index.switchTab({
        url: "/pages/my/index"
      });
    }
  }
};
function _sfc_render(_ctx, _cache, $props, $setup, $data, $options) {
  return common_vendor.e({
    a: common_vendor.t($data.employeeName),
    b: common_vendor.t($options.welcomeText),
    c: $data.pendingContracts.length > 0
  }, $data.pendingContracts.length > 0 ? {
    d: common_vendor.f($data.pendingContracts, (contract, k0, i0) => {
      return {
        a: common_vendor.t($options.getContractTypeText(contract.contract_type)),
        b: common_vendor.t($options.formatTime(contract.uploaded_at)),
        c: contract.id,
        d: common_vendor.o(($event) => $options.goToDetail(contract.id), contract.id)
      };
    })
  } : {}, {
    e: common_vendor.o((...args) => $options.goToContracts && $options.goToContracts(...args)),
    f: common_vendor.o((...args) => $options.goToMy && $options.goToMy(...args)),
    g: $data.showNoticeModal
  }, $data.showNoticeModal ? {
    h: common_vendor.o((...args) => $options.closeNoticeModal && $options.closeNoticeModal(...args)),
    i: common_vendor.o((...args) => $options.closeNoticeModal && $options.closeNoticeModal(...args)),
    j: common_vendor.t($data.noticeFileName),
    k: common_vendor.o((...args) => $options.handleReadNotice && $options.handleReadNotice(...args)),
    l: $data.hasAgreed,
    m: common_vendor.o((...args) => $options.handleAgreeChange && $options.handleAgreeChange(...args)),
    n: common_vendor.o((...args) => $options.closeNoticeModal && $options.closeNoticeModal(...args)),
    o: !$data.hasAgreed ? 1 : "",
    p: common_vendor.o((...args) => $options.handleConfirmSign && $options.handleConfirmSign(...args))
  } : {});
}
const MiniProgramPage = /* @__PURE__ */ common_vendor._export_sfc(_sfc_main, [["render", _sfc_render], ["__scopeId", "data-v-1cf27b2a"]]);
wx.createPage(MiniProgramPage);
//# sourceMappingURL=../../../.sourcemap/mp-weixin/pages/index/index.js.map
