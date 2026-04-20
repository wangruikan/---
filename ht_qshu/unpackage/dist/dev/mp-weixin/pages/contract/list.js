"use strict";
const common_vendor = require("../../common/vendor.js");
const api_contract = require("../../api/contract.js");
const _sfc_main = {
  data() {
    return {
      currentTab: "",
      contracts: [],
      tabs: [
        { label: "全部", value: "" },
        { label: "待签署", value: "pending_sign" },
        { label: "已签署", value: "employee_signed" },
        { label: "已完成", value: "completed" }
      ],
      // 须知弹窗相关
      showNoticeModal: false,
      hasAgreed: false,
      noticeFileName: "",
      noticeFileUrl: "",
      currentContractId: null
    };
  },
  onShow() {
    this.checkLogin();
    this.loadContracts();
  },
  methods: {
    checkLogin() {
      const employeeInfo = common_vendor.index.getStorageSync("employeeInfo");
      if (!employeeInfo) {
        common_vendor.index.reLaunch({
          url: "/pages/login/login"
        });
      }
    },
    switchTab(value) {
      this.currentTab = value;
      this.loadContracts();
    },
    async loadContracts() {
      try {
        common_vendor.index.showLoading({ title: "加载中..." });
        const res = await api_contract.getMyContracts(this.currentTab);
        if (res.success) {
          this.contracts = res.data;
        }
      } catch (error) {
        common_vendor.index.__f__("error", "at pages/contract/list.vue:146", "加载失败:", error);
      } finally {
        common_vendor.index.hideLoading();
      }
    },
    async goToDetail(id) {
      common_vendor.index.showToast({ title: "点击了合同:" + id, icon: "none", duration: 1e3 });
      common_vendor.index.__f__("log", "at pages/contract/list.vue:155", "🖱️ 点击合同，ID:", id);
      const contract = this.contracts.find((c) => c.id === id);
      common_vendor.index.__f__("log", "at pages/contract/list.vue:160", "📋 合同对象:", contract);
      common_vendor.index.__f__("log", "at pages/contract/list.vue:161", "📊 合同状态:", contract ? contract.status : "null");
      if (contract && contract.status === "pending_sign") {
        common_vendor.index.__f__("log", "at pages/contract/list.vue:165", "✅ 是待签署合同，开始检查须知");
        await this.checkNoticeAndSign(id);
      } else {
        common_vendor.index.__f__("log", "at pages/contract/list.vue:168", "ℹ️ 不是待签署合同，跳转详情页");
        common_vendor.index.navigateTo({
          url: `/pages/contract/detail?id=${id}`
        });
      }
    },
    // 检查须知并签署
    async checkNoticeAndSign(contractId) {
      common_vendor.index.__f__("log", "at pages/contract/list.vue:178", "🔍 准备显示须知弹窗，合同ID:", contractId);
      this.showNoticeModal = true;
      this.hasAgreed = false;
      this.currentContractId = contractId;
      this.noticeFileName = "劳动合同须知.pdf";
      this.noticeFileUrl = "";
      common_vendor.index.__f__("log", "at pages/contract/list.vue:187", "✅ 弹窗已显示");
      try {
        const res = await api_contract.getContractDetail(contractId);
        common_vendor.index.__f__("log", "at pages/contract/list.vue:193", "📦 API返回:", res);
        if (res && res.success) {
          const { notice_file } = res.data;
          if (notice_file) {
            common_vendor.index.__f__("log", "at pages/contract/list.vue:199", "📄 加载到须知文件:", notice_file);
            this.noticeFileName = notice_file.name;
            this.noticeFileUrl = notice_file.view_url;
          } else {
            common_vendor.index.__f__("log", "at pages/contract/list.vue:203", "📄 没有须知文件");
          }
        } else {
          common_vendor.index.__f__("log", "at pages/contract/list.vue:206", "📄 API返回失败或无数据");
        }
      } catch (error) {
        common_vendor.index.__f__("error", "at pages/contract/list.vue:209", "❌ 加载须知文件失败:", error);
      }
    },
    // 阅读须知文件
    handleReadNotice() {
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
                common_vendor.index.__f__("log", "at pages/contract/list.vue:230", "文件打开成功");
              },
              fail: (err) => {
                common_vendor.index.__f__("error", "at pages/contract/list.vue:233", "文件打开失败:", err);
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
          common_vendor.index.__f__("error", "at pages/contract/list.vue:244", "文件下载失败:", err);
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
      const classes = {
        draft: "status-draft",
        pending_sign: "status-pending",
        employee_signed: "status-signed",
        completed: "status-completed",
        rejected: "status-rejected"
      };
      return classes[status] || "";
    },
    formatTime(time) {
      if (!time)
        return "";
      const date = new Date(time);
      const m = (date.getMonth() + 1).toString().padStart(2, "0");
      const d = date.getDate().toString().padStart(2, "0");
      const h = date.getHours().toString().padStart(2, "0");
      const min = date.getMinutes().toString().padStart(2, "0");
      return `${m}-${d} ${h}:${min}`;
    }
  }
};
function _sfc_render(_ctx, _cache, $props, $setup, $data, $options) {
  return common_vendor.e({
    a: common_vendor.f($data.tabs, (tab, k0, i0) => {
      return {
        a: common_vendor.t(tab.label),
        b: tab.value,
        c: common_vendor.n($data.currentTab === tab.value ? "tab-active" : ""),
        d: common_vendor.o(($event) => $options.switchTab(tab.value), tab.value)
      };
    }),
    b: $data.contracts.length > 0
  }, $data.contracts.length > 0 ? {
    c: common_vendor.f($data.contracts, (contract, k0, i0) => {
      return common_vendor.e({
        a: common_vendor.t($options.getContractTypeText(contract.contract_type)),
        b: common_vendor.t($options.getStatusText(contract.status)),
        c: common_vendor.n($options.getStatusClass(contract.status)),
        d: common_vendor.t(contract.original_filename),
        e: common_vendor.t($options.formatTime(contract.uploaded_at)),
        f: contract.status === "pending_sign"
      }, contract.status === "pending_sign" ? {} : {}, {
        g: contract.id,
        h: common_vendor.o(($event) => $options.goToDetail(contract.id), contract.id)
      });
    })
  } : {}, {
    d: $data.showNoticeModal
  }, $data.showNoticeModal ? {
    e: common_vendor.o((...args) => $options.closeNoticeModal && $options.closeNoticeModal(...args)),
    f: common_vendor.o((...args) => $options.closeNoticeModal && $options.closeNoticeModal(...args)),
    g: common_vendor.t($data.noticeFileName),
    h: common_vendor.o((...args) => $options.handleReadNotice && $options.handleReadNotice(...args)),
    i: $data.hasAgreed,
    j: common_vendor.o((...args) => $options.handleAgreeChange && $options.handleAgreeChange(...args)),
    k: common_vendor.o((...args) => $options.closeNoticeModal && $options.closeNoticeModal(...args)),
    l: common_vendor.o((...args) => $options.handleConfirmSign && $options.handleConfirmSign(...args)),
    m: !$data.hasAgreed,
    n: common_vendor.o(() => {
    })
  } : {});
}
const MiniProgramPage = /* @__PURE__ */ common_vendor._export_sfc(_sfc_main, [["render", _sfc_render], ["__scopeId", "data-v-04caa8b0"]]);
wx.createPage(MiniProgramPage);
//# sourceMappingURL=../../../.sourcemap/mp-weixin/pages/contract/list.js.map
