"use strict";
const common_vendor = require("../../common/vendor.js");
const api_contract = require("../../api/contract.js");
const _sfc_main = {
  data() {
    return {
      form: {
        phone: "13800132111",
        password: "156598"
      },
      loading: false
    };
  },
  computed: {
    canSubmit() {
      return this.form.phone.length === 11 && this.form.password.length >= 6;
    }
  },
  methods: {
    async handleLogin() {
      if (!this.canSubmit)
        return;
      this.loading = true;
      try {
        const res = await api_contract.login(this.form);
        if (res.success) {
          common_vendor.index.setStorageSync("token", res.data.token);
          common_vendor.index.setStorageSync("employeeInfo", res.data.employee);
          common_vendor.index.showToast({
            title: "登录成功",
            icon: "success"
          });
          setTimeout(() => {
            common_vendor.index.switchTab({
              url: "/pages/index/index"
            });
          }, 1e3);
        }
      } catch (error) {
        common_vendor.index.__f__("error", "at pages/login/login.vue:94", "登录失败:", error);
      } finally {
        this.loading = false;
      }
    }
  }
};
function _sfc_render(_ctx, _cache, $props, $setup, $data, $options) {
  return {
    a: $data.form.phone,
    b: common_vendor.o(($event) => $data.form.phone = $event.detail.value),
    c: $data.form.password,
    d: common_vendor.o(($event) => $data.form.password = $event.detail.value),
    e: $data.loading,
    f: !$options.canSubmit,
    g: common_vendor.o((...args) => $options.handleLogin && $options.handleLogin(...args))
  };
}
const MiniProgramPage = /* @__PURE__ */ common_vendor._export_sfc(_sfc_main, [["render", _sfc_render], ["__scopeId", "data-v-e4e4508d"]]);
wx.createPage(MiniProgramPage);
//# sourceMappingURL=../../../.sourcemap/mp-weixin/pages/login/login.js.map
