"use strict";
const utils_request = require("../utils/request.js");
function login(data) {
  return utils_request.request.post("/login", data);
}
function getPendingContracts() {
  return utils_request.request.get("/pending-contracts");
}
function getMyContracts(status) {
  return utils_request.request.get("/my-contracts", { status });
}
function getContractDetail(id) {
  return utils_request.request.get(`/contracts/${id}`);
}
function signContract(id, data) {
  return utils_request.request.post(`/contracts/${id}/sign`, data);
}
function rejectContract(id, data) {
  return utils_request.request.post(`/contracts/${id}/reject`, data);
}
exports.getContractDetail = getContractDetail;
exports.getMyContracts = getMyContracts;
exports.getPendingContracts = getPendingContracts;
exports.login = login;
exports.rejectContract = rejectContract;
exports.signContract = signContract;
//# sourceMappingURL=../../.sourcemap/mp-weixin/api/contract.js.map
