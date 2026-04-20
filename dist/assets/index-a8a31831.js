import{_ as pe}from"./_plugin-vue_export-helper-05c35a1f.js";/* empty css                        *//* empty css                          *//* empty css                  *//* empty css                   *//* empty css                             *//* empty css                      *//* empty css               *//* empty css                  *//* empty css                  *//* empty css                     */import"./el-tooltip-4ed993c7.js";/* empty css                *//* empty css                             */import{J as ce,a as me,v as ye,x as B,b as L,r as x,o as fe,c as m,d as y,B as F,e as u,f as t,w as a,k as N,ak as ge,ai as be,F as O,A as R,i as s,h as q,a9 as he,t as i,C as ke,ar as ve,p as we,G as xe,H as Ce,m as Se,E as Ee,l as ze,af as je,D as Ve,ah as De,am as Ae,aw as Fe,ax as Ne,az as Ie,aR as Pe,aj as Me,a3 as Ye,aS as $e}from"./index-f6f56618.js";import{N as Te}from"./NoAccountSetTip-278a124a.js";import{g as Ue}from"./projects-1371f49f.js";/* empty css                 */function Be(I){return ce({url:"/salary-summaries",method:"get",params:I})}const Le={class:"salary-summaries-page"},Oe={key:1},Re={class:"card-header"},qe={style:{"overflow-x":"auto"}},Ge={key:0},Je={key:1},He={key:2},Ke={key:3},We={key:0},Qe={key:1},Xe={style:{color:"#409EFF","font-weight":"bold"}},Ze={style:{color:"#67C23A","font-weight":"bold"}},et={style:{color:"#67C23A","font-weight":"bold"}},tt={class:"dialog-title"},at={key:0},lt={key:0},ot={key:1},nt={style:{color:"#409EFF","font-weight":"bold"}},rt={style:{color:"#67C23A","font-weight":"bold"}},st={style:{color:"#F56C6C"}},it={style:{display:"flex","justify-content":"space-between","align-items":"center","margin-bottom":"15px"}},dt={style:{"margin-top":"10px",color:"#909399","font-size":"12px"}},G="salary_summaries_print_columns",_t={__name:"index",setup(I){const J=me(),j=ye(),H=B(()=>J.userInfo?.role==="admin"),K=B(()=>j.currentAccountSetId),k=L({month:null,project_id:null}),g=L({page:1,pageSize:20,total:0}),C=x([]),V=x(!1),P=x([]),z=x(!1),c=x(null),E=x(!1),f=x([]),w=[{key:"index",label:"序号"},{key:"project_name",label:"单位"},{key:"insurance_import_setting",label:"社保"},{key:"social_security_location",label:"参保地"},{key:"status",label:"状态"},{key:"month",label:"所属期"},{key:"employee_count",label:"人数"},{key:"salary_payment_day",label:"发薪日"},{key:"category",label:"类别"},{key:"form",label:"形式"},{key:"pension_personal",label:"个人社保-社保"},{key:"medical_personal",label:"个人社保-医保"},{key:"large_medical_personal",label:"个人社保-大额"},{key:"housing_fund_personal",label:"公积金-个人"},{key:"other_deduction",label:"其他扣除"},{key:"finished",label:"完结"},{key:"approved",label:"审核"},{key:"total_gross_salary",label:"应发工资"},{key:"total_net_salary",label:"实发工资"},{key:"tax_deduction",label:"扣除个税"},{key:"actual_tax",label:"实际个税"},{key:"previous_adjustment",label:"往期调整"},{key:"actual_payment",label:"实际发放"},{key:"difference",label:"差额"},{key:"payment_date",label:"发放日期"},{key:"remarks",label:"备注"},{key:"unadjusted_statistics",label:"未调整统计/补发"},{key:"annotation",label:"标注"}],_=n=>(n==null&&(n=0),"¥"+Number(n).toLocaleString("zh-CN",{minimumFractionDigits:2,maximumFractionDigits:2})),D=n=>{if(!n)return"-";const e=new Date(n),o=e.getFullYear(),p=String(e.getMonth()+1).padStart(2,"0"),v=String(e.getDate()).padStart(2,"0");return`${o}${p}${v}`},M=n=>{if(!n)return"-";const e=n.split("-");return e.length===2?e[1]+"月":n},Y=(n,e)=>{if(!n||!e)return"-";const o=n.split("-");if(o.length===2){const p=o[0],v=o[1],r=String(e).padStart(2,"0");return`${p}${v}${r}`}return"-"},S=async()=>{V.value=!0;try{const n={month:k.month,project_id:k.project_id,page:g.page,per_page:g.pageSize,current_account_set_id:j.currentAccountSetId},e=await Be(n);if(e&&e.success&&(C.value=e.data.data||[],g.total=e.data.total||0,C.value.length>0)){const o=C.value[0];console.log("========== 前端接收到的数据 =========="),console.log("ID:",o.id),console.log("项目名称:",o.project_name),console.log("养老保险个人:",o.total_pension_personal,"类型:",typeof o.total_pension_personal),console.log("医疗保险个人:",o.total_medical_personal,"类型:",typeof o.total_medical_personal),console.log("失业保险个人:",o.total_unemployment_personal,"类型:",typeof o.total_unemployment_personal),console.log("大额医疗个人:",o.total_large_medical_personal,"类型:",typeof o.total_large_medical_personal),console.log("计算测试:");const p=(o.total_pension_personal||0)+(o.total_medical_personal||0)+(o.total_unemployment_personal||0);console.log("三项相加结果:",p,"类型:",typeof p),console.log("Number(sum):",Number(p)),console.log("formatMoney结果:",_(p)),console.log("完整第一条记录:",o),console.log("=====================================")}}catch(n){console.error("Load salary summaries error:",n),N.error("加载工资汇总列表失败"),C.value=[],g.total=0}finally{V.value=!1}},W=()=>{k.month=null,k.project_id=null,g.page=1,S()},Q=n=>{c.value=n,z.value=!0},X=()=>{const n=Z();if(n&&n.length>0){const e=n.filter(o=>w.some(p=>p.key===o));e.length>0?f.value=e:f.value=w.map(o=>o.key)}else f.value=w.map(e=>e.key);E.value=!0},Z=()=>{try{const n=localStorage.getItem(G);if(n)return JSON.parse(n)}catch(n){console.error("加载打印列选择失败:",n)}return null},A=()=>{try{localStorage.setItem(G,JSON.stringify(f.value))}catch(n){console.error("保存打印列选择失败:",n)}},ee=()=>{f.value=w.map(n=>n.key),A()},te=()=>{f.value=[],A()},ae=()=>{if(f.value.length===0){N.warning("请至少选择一列进行打印");return}E.value=!1;const n=window.open("","_blank");if(!n){N.error("无法打开打印窗口，请检查浏览器弹窗设置");return}let e=`
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      <title>工资汇总打印</title>
      <style>
        * {
          margin: 0;
          padding: 0;
          box-sizing: border-box;
        }
        body {
          font-family: "Microsoft YaHei", Arial, sans-serif;
          font-size: 11px;
          color: #000;
        }
        .print-container {
          width: 100%;
        }
        h2 {
          text-align: center;
          margin: 10px 0 15px 0;
          font-size: 16px;
          font-weight: bold;
        }
        table {
          width: 100%;
          border-collapse: collapse;
          margin: 0 auto;
          table-layout: fixed;
        }
        th, td {
          border: 1px solid #000;
          padding: 4px 6px;
          text-align: center;
          vertical-align: middle;
          word-wrap: break-word;
          font-size: 11px;
        }
        th {
          background-color: #D9D9D9;
          font-weight: bold;
          height: 25px;
        }
        td {
          background-color: #fff;
          height: 22px;
        }
        tr:nth-child(even) td {
          background-color: #F2F2F2;
        }
        /* 打印样式优化 */
        @media print {
          @page {
            size: A4 landscape;
            margin: 1.5cm 1cm;
          }
          body {
            margin: 0;
            padding: 0;
          }
          .print-container {
            width: 100%;
          }
          table {
            page-break-inside: auto;
          }
          tr {
            page-break-inside: avoid;
            page-break-after: auto;
          }
          thead {
            display: table-header-group;
          }
          tfoot {
            display: table-footer-group;
          }
          /* 每页都显示表头 */
          thead tr {
            page-break-after: avoid;
            page-break-inside: avoid;
          }
        }
      </style>
    </head>
    <body>
      <div class="print-container">
        <h2>工资汇总表</h2>
        <table>
        <thead>
          <tr>
  `;f.value.forEach(o=>{const p=w.find(v=>v.key===o);p&&(e+=`<th>${p.label}</th>`)}),e+=`
          </tr>
        </thead>
        <tbody>
  `,C.value.forEach((o,p)=>{e+="<tr>",f.value.forEach(v=>{let r="";switch(v){case"index":r=p+1;break;case"project_name":r=o.project_name||"-";break;case"insurance_import_setting":o.insurance_import_setting==="current"?r="当月":o.insurance_import_setting==="next"?r="次月":o.insurance_import_setting==="none"?r="不导入":r="-";break;case"social_security_location":r=o.social_security_location||"-";break;case"status":r=o.salary_basis_uploaded?"已上传":o.requires_salary_basis?"未上传":"-";break;case"month":r=M(o.month);break;case"employee_count":r=o.employee_count||0;break;case"salary_payment_day":r=Y(o.month,o.salary_payment_day);break;case"category":r="工资";break;case"form":r="现金";break;case"pension_personal":r=_((o.total_pension_personal||0)+(o.total_unemployment_personal||0));break;case"medical_personal":r=_(o.total_medical_personal||0);break;case"large_medical_personal":r=_(o.total_large_medical_personal||0);break;case"housing_fund_personal":r=_(o.total_housing_fund_personal||0);break;case"other_deduction":r=_((o.total_work_injury_company||0)+(o.total_maternity_company||0));break;case"finished":r="✓";break;case"approved":r="✓";break;case"total_gross_salary":r=_(o.total_gross_salary);break;case"total_net_salary":r=_(o.total_net_salary);break;case"tax_deduction":r=_(o.total_tax_payable_or_refundable||0);break;case"actual_tax":r=_(o.total_tax_payable_or_refundable||0);break;case"previous_adjustment":r=_(0);break;case"actual_payment":r=_(o.total_net_salary||0);break;case"difference":r=_(0);break;case"payment_date":r=D(o.approved_at);break;case"remarks":r="-";break;case"unadjusted_statistics":r="-";break;case"annotation":r="-";break;default:r="-"}e+=`<td>${r}</td>`}),e+="</tr>"}),e+=`
        </tbody>
      </table>
      </div>
    </body>
    </html>
  `,n.document.write(e),n.document.close(),setTimeout(()=>{n.print()},500)},le=async()=>{try{const n=await Ue({current_account_set_id:j.currentAccountSetId,all:!0});n.success&&n.data&&(P.value=Array.isArray(n.data)?n.data:n.data.data||[])}catch(n){console.error("加载项目列表失败:",n)}};return fe(()=>{S(),le()}),(n,e)=>{const o=ve,p=we,v=xe,r=Ce,b=Se,oe=Ee,$=ge,T=ze,d=je,ne=Ve,re=De,se=Ae,h=Fe,ie=Ne,U=be,de=Ie,_e=Pe,ue=Me;return m(),y("div",Le,[!H.value&&!K.value?(m(),F(Te,{key:0})):(m(),y("div",Oe,[e[28]||(e[28]=u("div",{class:"page-header"},[u("h1",null,"工资汇总")],-1)),t($,{shadow:"never",style:{"margin-bottom":"20px"}},{default:a(()=>[t(oe,{inline:!0,model:k,class:"search-form"},{default:a(()=>[t(p,{label:"工资期间"},{default:a(()=>[t(o,{modelValue:k.month,"onUpdate:modelValue":e[0]||(e[0]=l=>k.month=l),type:"month",placeholder:"选择月份",format:"YYYY-MM","value-format":"YYYY-MM",clearable:""},null,8,["modelValue"])]),_:1}),t(p,{label:"项目"},{default:a(()=>[t(r,{modelValue:k.project_id,"onUpdate:modelValue":e[1]||(e[1]=l=>k.project_id=l),placeholder:"请选择项目",clearable:"",filterable:""},{default:a(()=>[(m(!0),y(O,null,R(P.value,l=>(m(),F(v,{key:l.id,label:l.name,value:l.id},null,8,["label","value"]))),128))]),_:1},8,["modelValue"])]),_:1}),t(p,null,{default:a(()=>[t(b,{type:"primary",onClick:S},{default:a(()=>[...e[9]||(e[9]=[s("查询",-1)])]),_:1}),t(b,{onClick:W},{default:a(()=>[...e[10]||(e[10]=[s("重置",-1)])]),_:1})]),_:1})]),_:1},8,["model"])]),_:1}),t($,{shadow:"never"},{header:a(()=>[u("div",Re,[e[13]||(e[13]=u("span",{class:"title"},"工资汇总列表",-1)),u("div",null,[t(b,{type:"text",onClick:S},{default:a(()=>[t(T,null,{default:a(()=>[t(q(Ye))]),_:1}),e[11]||(e[11]=s(" 刷新 ",-1))]),_:1}),t(b,{type:"primary",onClick:X},{default:a(()=>[t(T,null,{default:a(()=>[t(q($e))]),_:1}),e[12]||(e[12]=s(" 打印 ",-1))]),_:1})])])]),default:a(()=>[u("div",qe,[he((m(),F(re,{data:C.value,border:"",stripe:"",style:{width:"100%","font-size":"12px"}},{default:a(()=>[t(d,{type:"index",label:"序号",width:"60",align:"center",fixed:"left"}),t(d,{prop:"project_name",label:"单位",width:"150","show-overflow-tooltip":"",fixed:"left"}),t(d,{label:"社保",width:"100",align:"center"},{default:a(l=>[l.row.insurance_import_setting==="current"?(m(),y("span",Ge,"当月")):l.row.insurance_import_setting==="next"?(m(),y("span",Je,"次月")):l.row.insurance_import_setting==="none"?(m(),y("span",He,"不导入")):(m(),y("span",Ke,"-"))]),_:1}),t(d,{prop:"social_security_location",label:"参保地",width:"100",align:"center"},{default:a(l=>[s(i(l.row.social_security_location||"-"),1)]),_:1}),t(d,{label:"状态",width:"100",align:"center"},{default:a(l=>[l.row.requires_salary_basis?(m(),y("span",We,[t(ne,{type:l.row.salary_basis_uploaded?"success":"danger",size:"small"},{default:a(()=>[s(i(l.row.salary_basis_uploaded?"已上传":"未上传"),1)]),_:2},1032,["type"])])):(m(),y("span",Qe,"-"))]),_:1}),t(d,{label:"所属期",width:"80",align:"center"},{default:a(l=>[s(i(M(l.row.month)),1)]),_:1}),t(d,{label:"人数",width:"60",align:"center"},{default:a(l=>[s(i(l.row.employee_count),1)]),_:1}),t(d,{label:"发薪日",width:"100",align:"center"},{default:a(l=>[s(i(Y(l.row.month,l.row.salary_payment_day)),1)]),_:1}),t(d,{label:"类别",width:"80",align:"center"},{default:a(()=>[...e[14]||(e[14]=[s("工资",-1)])]),_:1}),t(d,{label:"形式",width:"80",align:"center"},{default:a(()=>[...e[15]||(e[15]=[s("现金",-1)])]),_:1}),t(d,{label:"个人社保",align:"center"},{default:a(()=>[t(d,{label:"社保",width:"100",align:"right"},{default:a(l=>[s(i(_((l.row.total_pension_personal||0)+(l.row.total_unemployment_personal||0))),1)]),_:1}),t(d,{label:"医保",width:"100",align:"right"},{default:a(l=>[s(i(_(l.row.total_medical_personal||0)),1)]),_:1}),t(d,{label:"大额",width:"100",align:"right"},{default:a(l=>[s(i(_(l.row.total_large_medical_personal||0)),1)]),_:1})]),_:1}),t(d,{label:"公积金",align:"center"},{default:a(()=>[t(d,{label:"个人",width:"100",align:"right"},{default:a(l=>[s(i(_(l.row.total_housing_fund_personal||0)),1)]),_:1})]),_:1}),t(d,{label:"其他扣除",width:"100",align:"right"},{default:a(l=>[s(i(_((l.row.total_work_injury_company||0)+(l.row.total_maternity_company||0))),1)]),_:1}),t(d,{label:"完结",width:"80",align:"center"},{default:a(()=>[...e[16]||(e[16]=[u("span",{style:{color:"#67C23A","font-size":"18px"}},"✓",-1)])]),_:1}),t(d,{label:"审核",width:"80",align:"center"},{default:a(()=>[...e[17]||(e[17]=[u("span",{style:{color:"#67C23A","font-size":"18px"}},"✓",-1)])]),_:1}),t(d,{label:"应发工资",width:"120",align:"right"},{default:a(l=>[u("span",Xe,i(_(l.row.total_gross_salary)),1)]),_:1}),t(d,{label:"实发工资",width:"120",align:"right"},{default:a(l=>[u("span",Ze,i(_(l.row.total_net_salary)),1)]),_:1}),t(d,{label:"扣除个税",width:"100",align:"right"},{default:a(l=>[s(i(_(l.row.total_tax_payable_or_refundable||0)),1)]),_:1}),t(d,{label:"实际个税",width:"100",align:"right"},{default:a(l=>[s(i(_(l.row.total_tax_payable_or_refundable||0)),1)]),_:1}),t(d,{label:"往期调整",width:"100",align:"right"},{default:a(()=>[s(i(_(0)),1)]),_:1}),t(d,{label:"实际发放",width:"120",align:"right"},{default:a(l=>[u("span",et,i(_(l.row.total_net_salary||0)),1)]),_:1}),t(d,{label:"差额",width:"100",align:"right"},{default:a(()=>[s(i(_(0)),1)]),_:1}),t(d,{prop:"approved_at",label:"发放日期",width:"160",align:"center"},{default:a(l=>[s(i(D(l.row.approved_at)),1)]),_:1}),t(d,{label:"备注",width:"120",align:"center"},{default:a(()=>[...e[18]||(e[18]=[s("-",-1)])]),_:1}),t(d,{label:"未调整统计/补发",width:"120",align:"center"},{default:a(()=>[...e[19]||(e[19]=[s("-",-1)])]),_:1}),t(d,{label:"标注",width:"120",align:"center"},{default:a(()=>[...e[20]||(e[20]=[s("-",-1)])]),_:1}),t(d,{label:"操作",width:"100",fixed:"right",align:"center"},{default:a(l=>[t(b,{link:"",type:"primary",size:"small",onClick:ut=>Q(l.row)},{default:a(()=>[...e[21]||(e[21]=[s(" 查看详情 ",-1)])]),_:1},8,["onClick"])]),_:1})]),_:1},8,["data"])),[[ue,V.value]])]),t(se,{"current-page":g.page,"onUpdate:currentPage":e[2]||(e[2]=l=>g.page=l),"page-size":g.pageSize,"onUpdate:pageSize":e[3]||(e[3]=l=>g.pageSize=l),total:g.total,"page-sizes":[10,20,50,100],layout:"total, sizes, prev, pager, next, jumper",onSizeChange:S,onCurrentChange:S,style:{"margin-top":"20px","justify-content":"flex-end"}},null,8,["current-page","page-size","total"])]),_:1}),t(U,{modelValue:z.value,"onUpdate:modelValue":e[5]||(e[5]=l=>z.value=l),width:"95%",top:"5vh"},{header:a(()=>[u("span",tt,"工资汇总详情 - "+i(c.value?.project_name)+" ("+i(c.value?.month)+")",1)]),footer:a(()=>[t(b,{onClick:e[4]||(e[4]=l=>z.value=!1)},{default:a(()=>[...e[22]||(e[22]=[s("关闭",-1)])]),_:1})]),default:a(()=>[c.value?(m(),y("div",at,[t(ie,{column:2,border:""},{default:a(()=>[t(h,{label:"项目名称"},{default:a(()=>[s(i(c.value.project_name),1)]),_:1}),t(h,{label:"工资期间"},{default:a(()=>[s(i(c.value.month),1)]),_:1}),t(h,{label:"工资周期"},{default:a(()=>[c.value.period_start&&c.value.period_end?(m(),y("span",lt,i(c.value.period_start)+" - "+i(c.value.period_end),1)):(m(),y("span",ot,"-"))]),_:1}),t(h,{label:"员工人数"},{default:a(()=>[s(i(c.value.employee_count)+" 人",1)]),_:1}),t(h,{label:"应发工资合计"},{default:a(()=>[u("span",nt,"¥"+i(_(c.value.total_gross_salary)),1)]),_:1}),t(h,{label:"实发工资合计"},{default:a(()=>[u("span",rt,"¥"+i(_(c.value.total_net_salary)),1)]),_:1}),t(h,{label:"单位保险合计"},{default:a(()=>[s(" ¥"+i(_(c.value.total_company_insurance_total)),1)]),_:1}),t(h,{label:"个人保险合计"},{default:a(()=>[u("span",st,"¥"+i(_(c.value.total_personal_insurance_total)),1)]),_:1}),t(h,{label:"个税合计"},{default:a(()=>[s(" ¥"+i(_(c.value.total_tax_payable_or_refundable)),1)]),_:1}),t(h,{label:"审批时间"},{default:a(()=>[s(i(D(c.value.approved_at)),1)]),_:1})]),_:1})])):ke("",!0)]),_:1},8,["modelValue"]),t(U,{modelValue:E.value,"onUpdate:modelValue":e[8]||(e[8]=l=>E.value=l),title:"打印设置",width:"600px"},{footer:a(()=>[t(b,{onClick:e[7]||(e[7]=l=>E.value=!1)},{default:a(()=>[...e[26]||(e[26]=[s("取消",-1)])]),_:1}),t(b,{type:"primary",onClick:ae},{default:a(()=>[...e[27]||(e[27]=[s("确认打印",-1)])]),_:1})]),default:a(()=>[u("div",null,[u("div",it,[e[25]||(e[25]=u("p",{style:{margin:"0"}},"请选择要打印的列：",-1)),u("div",null,[t(b,{link:"",type:"primary",size:"small",onClick:ee},{default:a(()=>[...e[23]||(e[23]=[s("全选",-1)])]),_:1}),t(b,{link:"",type:"primary",size:"small",onClick:te},{default:a(()=>[...e[24]||(e[24]=[s("取消全选",-1)])]),_:1})])]),t(_e,{modelValue:f.value,"onUpdate:modelValue":e[6]||(e[6]=l=>f.value=l),onChange:A,style:{display:"flex","flex-direction":"column",gap:"10px","max-height":"400px","overflow-y":"auto"}},{default:a(()=>[(m(),y(O,null,R(w,l=>t(de,{key:l.key,label:l.key},{default:a(()=>[s(i(l.label),1)]),_:2},1032,["label"])),64))]),_:1},8,["modelValue"]),u("div",dt," 已选择 "+i(f.value.length)+" / "+i(w.length)+" 列 ",1)])]),_:1},8,["modelValue"])]))])}}},Dt=pe(_t,[["__scopeId","data-v-a1e53dfb"]]);export{Dt as default};
