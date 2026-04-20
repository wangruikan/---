<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>入职登记表</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            font-family: 'SimSun', 'DejaVu Sans', sans-serif;
        }
        
        body {
            padding: 20px;
            font-size: 14px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .company {
            font-size: 13px;
            margin-bottom: 10px;
        }
        
        h1 {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 10px;
            margin: 15px 0;
        }
        
        .date {
            text-align: right;
            margin-bottom: 15px;
            font-size: 12px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        
        td, th {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
            font-size: 12px;
        }
        
        .label {
            background-color: #f5f5f5;
            font-weight: normal;
            text-align: center;
            width: 12%;
        }
        
        .photo {
            width: 100px;
            text-align: center;
            vertical-align: middle;
            color: #999;
        }
        
        .section-title {
            background-color: #f0f0f0;
            text-align: center;
            font-weight: bold;
        }
        
        .checkbox {
            display: inline-block;
            margin-right: 20px;
        }
        
        .signature-section {
            margin-top: 30px;
            padding: 20px 0;
        }
        
        .declaration {
            margin-bottom: 20px;
            line-height: 1.8;
            text-indent: 2em;
        }
        
        .signature-line {
            text-align: right;
            margin-right: 80px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">新工人力资源服务有限公司</div>
        <h1>入 职 登 记 表</h1>
    </div>
    
    <div class="date">登记日期：2025年10月31日</div>
    
    <!-- 基本信息表 -->
    <table>
        <tr>
            <td class="label">姓名</td>
            <td>张三</td>
            <td class="label">性别</td>
            <td>男</td>
            <td class="label">民族</td>
            <td>汉</td>
            <td rowspan="5" class="photo">照<br>片</td>
        </tr>
        <tr>
            <td class="label">政治面貌</td>
            <td>群众</td>
            <td class="label">籍贯</td>
            <td colspan="3">广东深圳</td>
        </tr>
        <tr>
            <td class="label">出生年月</td>
            <td colspan="5">1990-04-20</td>
        </tr>
        <tr>
            <td class="label">毕业学校</td>
            <td colspan="2">深圳大学</td>
            <td class="label">毕业时间</td>
            <td colspan="2">2012-05-21</td>
        </tr>
        <tr>
            <td class="label">文化程度</td>
            <td>本科</td>
            <td class="label">所学专业</td>
            <td colspan="3">计算机科学与技术</td>
        </tr>
        <tr>
            <td class="label">学位</td>
            <td>学士</td>
            <td class="label">技术职称</td>
            <td>中级工程师</td>
            <td class="label">健康状况</td>
            <td colspan="2">良好</td>
        </tr>
        <tr>
            <td class="label">身高</td>
            <td>175cm</td>
            <td class="label">体重</td>
            <td>70.5kg</td>
            <td class="label">婚姻状况</td>
            <td colspan="2">已婚</td>
        </tr>
        <tr>
            <td class="label">身份证号码</td>
            <td colspan="6">430281200401096273</td>
        </tr>
        <tr>
            <td class="label">现居住地</td>
            <td colspan="6">深圳市南山区科技园</td>
        </tr>
        <tr>
            <td class="label">户口所在地</td>
            <td colspan="6">湖南省长沙市</td>
        </tr>
    </table>
    
    <!-- 学习简历 -->
    <table>
        <tr>
            <td rowspan="3" class="section-title" style="width: 10%;">
                学习<br>简历<br><br>(从中<br>学起)
            </td>
            <td class="label" style="width: 20%;">起止时间</td>
            <td class="label" style="width: 30%;">在何学校学习</td>
            <td class="label" style="width: 15%;">学习层次</td>
            <td class="label" style="width: 25%;">证明人</td>
        </tr>
        <tr>
            <td>2006.09 - 2009.06</td>
            <td>深圳市第一中学</td>
            <td>高中</td>
            <td>李老师</td>
        </tr>
        <tr>
            <td>2009.09 - 2012.06</td>
            <td>深圳大学</td>
            <td>本科</td>
            <td>王教授</td>
        </tr>
    </table>
    
    <!-- 工作经历 -->
    <table>
        <tr>
            <td rowspan="3" class="section-title" style="width: 10%;">
                工作<br>经历<br><br>(最近<br>二份或<br>三份)
            </td>
            <td class="label" style="width: 20%;">起止时间</td>
            <td class="label" style="width: 25%;">在何工作单位</td>
            <td class="label" style="width: 30%;">主要工作内容</td>
            <td class="label" style="width: 15%;">证明人</td>
        </tr>
        <tr>
            <td>2012.07 - 2018.06</td>
            <td>深圳科技有限公司</td>
            <td>软件开发</td>
            <td>张经理</td>
        </tr>
        <tr>
            <td>2018.07 - 2024.12</td>
            <td>腾讯科技有限公司</td>
            <td>高级工程师</td>
            <td>李总监</td>
        </tr>
    </table>
    
    <!-- 家庭情况 -->
    <table>
        <tr>
            <td rowspan="4" class="section-title" style="width: 10%;">
                家庭<br>情况
            </td>
            <td class="label" style="width: 15%;">姓名</td>
            <td class="label" style="width: 15%;">关系</td>
            <td class="label" style="width: 35%;">所在单位</td>
            <td class="label" style="width: 25%;">联系电话</td>
        </tr>
        <tr>
            <td>李四</td>
            <td>配偶</td>
            <td>深圳市人民医院</td>
            <td>13900139000</td>
        </tr>
        <tr>
            <td>张五</td>
            <td>父亲</td>
            <td>退休</td>
            <td>13900139001</td>
        </tr>
        <tr>
            <td>王六</td>
            <td>母亲</td>
            <td>退休</td>
            <td>13900139002</td>
        </tr>
    </table>
    
    <!-- 其他信息 -->
    <table>
        <tr>
            <td class="label">岗位</td>
            <td style="width: 35%;">软件工程师</td>
            <td class="label">求职地区</td>
            <td style="width: 35%;">深圳</td>
        </tr>
        <tr>
            <td class="label">是否服从调配</td>
            <td colspan="3">
                <span class="checkbox">☑ 是</span>
                <span class="checkbox">☐ 否</span>
                （万框内打√，二选一）
            </td>
        </tr>
        <tr>
            <td class="label">联系地址</td>
            <td colspan="3">深圳市南山区科技园南路100号</td>
        </tr>
        <tr>
            <td class="label">联系电话</td>
            <td colspan="3">13800138000</td>
        </tr>
        <tr>
            <td class="label">备注</td>
            <td colspan="3">熟悉Java、Python等编程语言</td>
        </tr>
    </table>
    
    <!-- 声明和签名 -->
    <div class="signature-section">
        <div class="declaration">
            本人保证以上所填写的信息真实可靠，如有虚假，本人愿承担一切法律责任。
        </div>
        <div class="signature-line">
            本人签名：__________________
        </div>
    </div>
</body>
</html>
