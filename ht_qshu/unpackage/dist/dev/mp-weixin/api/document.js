"use strict";
const utils_request = require("../utils/request.js");
function getMyDocuments() {
  return utils_request.request.get("/my-documents");
}
function uploadDocument(configId, filePath) {
  return utils_request.request.upload("/documents/upload", filePath, {
    document_config_id: configId
  });
}
function deleteDocument(documentId) {
  return utils_request.request.delete(`/documents/${documentId}`);
}
exports.deleteDocument = deleteDocument;
exports.getMyDocuments = getMyDocuments;
exports.uploadDocument = uploadDocument;
//# sourceMappingURL=../../.sourcemap/mp-weixin/api/document.js.map
