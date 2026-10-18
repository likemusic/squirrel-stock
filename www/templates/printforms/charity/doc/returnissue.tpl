<table class="ctable" border="0" cellspacing="0" cellpadding="2">
<!-- SquirrelStock: возврат в профиле благотворительной организации (App\SQS\Profile): вернул / принял, без цен и оплаты -->
    <tr>
        <td style="font-weight: bolder;font-size: larger;" align="center" colspan="5" valign="middle">
            <br> {{#t}}print.returnissue.povernennia_vid{{/t}} <br><br>
        </td>
    </tr>
    {{#customer_name}}
    <tr>
        <td></td>
        <td valign="top"><b>{{#t}}sqs.charity.returned_by{{/t}}</b></td>
        <td colspan="3">{{customer_name}}</td>
    </tr>
    {{/customer_name}}
    {{#firm_name}}
    <tr>
        <td></td>
        <td valign="top"><b>{{#t}}sqs.charity.accepted_by{{/t}}</b></td>
        <td colspan="3">{{firm_name}}</td>
    </tr>
    {{/firm_name}}
    <tr>
        <td colspan="5">{{{notes}}}</td>
    </tr>

    <tr style="font-weight: bolder;">
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;">№</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;" colspan="2">{{#t}}common.naimenuvannia{{/t}}</th>
        <th style="border-top:1px #000 solid;border-bottom:1px #000 solid;text-align: left;">{{#t}}common.od{{/t}}</th>
        <th style="text-align: right;border-top:1px #000 solid;border-bottom:1px #000 solid;">{{#t}}common.kil{{/t}}</th>
    </tr>
    {{#_detail}}
    <tr>
        <td align="right">{{no}}</td>
        <td colspan="2">{{tovar_name}}</td>
        <td>{{msr}}</td>
        <td align="right">{{quantity}}</td>
    </tr>
    {{/_detail}}
    <tr>
        <td style="border-top:1px #000 solid;" colspan="5">&nbsp;</td>
    </tr>
    <tr>
        <td colspan="3">{{#t}}sqs.charity.returned_by{{/t}} ___________</td>
        <td colspan="2">{{#t}}sqs.charity.accepted_by{{/t}} ___________</td>
    </tr>
</table>
