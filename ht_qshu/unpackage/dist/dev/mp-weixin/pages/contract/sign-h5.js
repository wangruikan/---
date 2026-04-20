"use strict";
const common_vendor = require("../../common/vendor.js");
const utils_request = require("../../utils/request.js");
const api_contract = require("../../api/contract.js");
const _sfc_main = {
  data() {
    return {
      h5Url: ""
    };
  },
  async onLoad(options) {
    const contractId = options.id;
    const token = common_vendor.index.getStorageSync("token");
    if (!contractId || !token) {
      common_vendor.index.showToast({
        title: "参数错误",
        icon: "none"
      });
      setTimeout(() => {
        common_vendor.index.navigateBack();
      }, 1500);
      return;
    }
    await this.loadAndLogSignaturePositions(contractId);
    const serverUrl = utils_request.BASE_URL.replace("/api/mini", "");
    this.h5Url = `${serverUrl}/h5-sign/index.html?contractId=${contractId}&token=${token}`;
    common_vendor.index.__f__("log", "at pages/contract/sign-h5.vue:37", "H5签署页面URL:", this.h5Url);
  },
  methods: {
    async loadAndLogSignaturePositions(contractId) {
      try {
        const res = await api_contract.getContractDetail(contractId);
        common_vendor.index.__f__("log", "at pages/contract/sign-h5.vue:45", "========== 签署页面 - 占位符位置 ==========");
        common_vendor.index.__f__("log", "at pages/contract/sign-h5.vue:46", "合同ID:", contractId);
        if (res && res.success) {
          const contract = res.data.contract;
          const positions = (contract == null ? void 0 : contract.signature_positions) || [];
          common_vendor.index.__f__("log", "at pages/contract/sign-h5.vue:52", "signature_positions:", positions);
          common_vendor.index.__f__("log", "at pages/contract/sign-h5.vue:53", "位置数量:", positions.length);
          if (positions.length > 0) {
            positions.forEach((pos, i) => {
              common_vendor.index.__f__("log", "at pages/contract/sign-h5.vue:57", `位置${i + 1}: 页=${pos.page}, x=${pos.x}, y=${pos.y}, 宽=${pos.width}, 高=${pos.height}`);
            });
          } else {
            common_vendor.index.__f__("warn", "at pages/contract/sign-h5.vue:60", "⚠️ 没有预设签名位置!");
          }
        } else {
          common_vendor.index.__f__("log", "at pages/contract/sign-h5.vue:63", "获取合同失败:", res);
        }
        common_vendor.index.__f__("log", "at pages/contract/sign-h5.vue:65", "==========================================");
      } catch (e) {
        common_vendor.index.__f__("error", "at pages/contract/sign-h5.vue:67", "获取合同出错:", e);
      }
    }
  }
};
function _sfc_render(_ctx, _cache, $props, $setup, $data, $options) {
  return {
    a: $data.h5Url
  };
}
const MiniProgramPage = /* @__PURE__ */ common_vendor._export_sfc(_sfc_main, [["render", _sfc_render]]);
wx.createPage(MiniProgramPage);
//# sourceMappingURL=../../../.sourcemap/mp-weixin/pages/contract/sign-h5.js.map
