"use strict";
const common_vendor = require("../../common/vendor.js");
const api_document = require("../../api/document.js");
const _sfc_main = {
  data() {
    return {
      employeeName: "",
      employeePhone: "",
      pendingCount: 0,
      registrationFormType: "onboarding",
      // 默认入职登记表
      showResignationCertificate: false
      // 是否显示离职证明入口
    };
  },
  onShow() {
    this.loadUserInfo();
    this.loadPendingDocumentsCount();
  },
  methods: {
    loadUserInfo() {
      const employeeInfo = common_vendor.index.getStorageSync("employeeInfo");
      if (employeeInfo) {
        this.employeeName = employeeInfo.name;
        this.employeePhone = employeeInfo.phone;
        this.registrationFormType = employeeInfo.registration_form_type || "onboarding";
        const contractStatus = employeeInfo.contract_status;
        this.showResignationCertificate = ["terminated", "retired"].includes(contractStatus);
      }
    },
    async loadPendingDocumentsCount() {
      try {
        const res = await api_document.getMyDocuments();
        if (res.success) {
          const documents = res.data || [];
          this.pendingCount = documents.filter((d) => !d.uploaded && d.is_required).length;
        }
      } catch (error) {
        common_vendor.index.__f__("error", "at pages/my/index.vue:89", "加载待上传资料数失败:", error);
      }
    },
    goToOnboardingForm() {
      common_vendor.index.navigateTo({
        url: "/pages/onboarding/index"
      });
    },
    goToRegistrationForm() {
      common_vendor.index.navigateTo({
        url: "/pages/registration/index"
      });
    },
    goToDocumentUpload() {
      common_vendor.index.navigateTo({
        url: "/pages/document/upload"
      });
    },
    goToResignationCertificate() {
      common_vendor.index.navigateTo({
        url: "/pages/resignation/index"
      });
    },
    handleLogout() {
      common_vendor.index.showModal({
        title: "提示",
        content: "确定要退出登录吗？",
        success: (res) => {
          if (res.confirm) {
            common_vendor.index.removeStorageSync("token");
            common_vendor.index.removeStorageSync("employeeInfo");
            common_vendor.index.reLaunch({
              url: "/pages/login/login"
            });
          }
        }
      });
    }
  }
};
function _sfc_render(_ctx, _cache, $props, $setup, $data, $options) {
  return common_vendor.e({
    a: common_vendor.t($data.employeeName.substring(0, 1)),
    b: common_vendor.t($data.employeeName),
    c: common_vendor.t($data.employeePhone),
    d: $data.registrationFormType === "onboarding"
  }, $data.registrationFormType === "onboarding" ? {
    e: common_vendor.o((...args) => $options.goToOnboardingForm && $options.goToOnboardingForm(...args))
  } : {}, {
    f: $data.registrationFormType === "registration"
  }, $data.registrationFormType === "registration" ? {
    g: common_vendor.o((...args) => $options.goToRegistrationForm && $options.goToRegistrationForm(...args))
  } : {}, {
    h: $data.pendingCount > 0
  }, $data.pendingCount > 0 ? {
    i: common_vendor.t($data.pendingCount)
  } : {}, {
    j: common_vendor.o((...args) => $options.goToDocumentUpload && $options.goToDocumentUpload(...args)),
    k: $data.showResignationCertificate
  }, $data.showResignationCertificate ? {
    l: common_vendor.o((...args) => $options.goToResignationCertificate && $options.goToResignationCertificate(...args))
  } : {}, {
    m: common_vendor.o((...args) => $options.handleLogout && $options.handleLogout(...args))
  });
}
const MiniProgramPage = /* @__PURE__ */ common_vendor._export_sfc(_sfc_main, [["render", _sfc_render], ["__scopeId", "data-v-f97bc692"]]);
wx.createPage(MiniProgramPage);
//# sourceMappingURL=../../../.sourcemap/mp-weixin/pages/my/index.js.map
