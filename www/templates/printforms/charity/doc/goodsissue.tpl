<table class="ctable  " border="0" cellspacing="0" cellpadding="2"  >
<!-- SquirrelStock: расходная накладная в профиле благотворительной организации (App\SQS\Profile): выдал / получатель, без цен и оплаты -->
    {{#customer_name}}
    <tr>
        <td></td>
        <td valign="top"><b>{{#t}}sqs.charity.recipient{{/t}}</b></td>
        <td colspan="4">{{customer_name}} {{#phone}} {{#t}}common.tel_3{{/t}} {{/phone}}</td>
    </tr>
    {{#iscustaddress}}
    <tr>
        <td></td>
        <td valign="top">{{#t}}common.adresa_2{{/t}}</td>
        <td colspan="4">{{custaddress}}</td>
    </tr>
    {{/iscustaddress}}
    {{/customer_name}}
    {{#isfirm}}
    <tr>
        <td></td>
        <td valign="top"><b>{{#t}}sqs.charity.issued_by{{/t}}</b></td>
        <td colspan="4">{{firm_name}} {{#fphone}} {{#t}}common.tel_4{{/t}} {{/fphone}}</td>
    </tr>
    {{/isfirm}}
    {{#iscontract}}
    <tr>
        <td></td>
        <td valign="top"><b>{{#t}}common.dohovir{{/t}}</b></td>
        <td colspan="4">{{#t}}common.vid{{/t}}</td>
    </tr>
    {{/iscontract}}
    <tr>
        <td></td>
        <td valign="top">{{#t}}common.sklad{{/t}}</td>
        <td colspan="4">{{store_name}}</td>
    </tr>
    <tr>
        <td colspan="6">{{{notes}}}</td>
    </tr>

    <tr>
        <td style="font-weight: bolder;font-size: larger;" align="center" colspan="6" valign="middle">
            {{#t}}sqs.charity.issue_title{{/t}} <br>
        </td>
    </tr>

    <tr style="font-weight: bolder;">
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">№</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;" colspan="2">{{#t}}common.naimenuvannia{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;">{{#t}}common.kod{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.od{{/t}}</th>
        <th style="text-align: right;border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.kil{{/t}}</th>
    </tr>
    {{#_detail}}
    <tr>
        <td align="right">{{no}}</td>
        <td colspan="2">{{tovar_name}}</td>
        <td data-type="s">{{tovar_code}}</td>
        <td>{{msr}}</td>
        <td align="right">{{quantity}}</td>
    </tr>
    {{/_detail}}
    <tr>
        <td style="border-top:1px #000 solid;" colspan="6">&nbsp;</td>
    </tr>
    <tr>
        <td colspan="3">{{#t}}sqs.charity.issued_by{{/t}} ___________</td>
        <td colspan="3">{{#t}}sqs.charity.recipient{{/t}} ___________</td>
    </tr>
    <tr>
        <td></td>
        <td colspan="5">
            {{#isstamp}}
            <img style="height:100px;" src="{{stamp}}"/>
            {{/isstamp}}
            {{^isstamp}}
            <br><br>
            {{#t}}print.goodsissue.nbsp_nbsp_nbsp_nbsp_nbsp{{/t}}
            <br><br>
            {{/isstamp}}
        </td>
    </tr>
</table>
