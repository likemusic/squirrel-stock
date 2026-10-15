{{#hassummary}}
<table class="ctable sqs-summary" border="0" cellpadding="2" cellspacing="0" style="margin-bottom: 1em;">
    <tr style="font-weight: bolder;">
        <td colspan="{{scolspan}}">{{#t}}report.equipmentwhere.summary{{/t}}</td>
    </tr>
    <tr style="font-weight: bolder;">
        <th style="border: solid black 1px; text-align: left;">{{#t}}report.equipmentwhere.model{{/t}}</th>
        {{#_scols}}<th style="border: solid black 1px;">{{name}}</th>{{/_scols}}
        <th style="border: solid black 1px;">{{#t}}report.equipmentwhere.col_total{{/t}}</th>
    </tr>
    {{#_srows}}
    <tr>
        <td style="border: solid black 1px;">{{name}}</td>
        {{#cells}}<td style="border: solid black 1px; text-align: right;">{{v}}</td>{{/cells}}
        <td style="border: solid black 1px; text-align: right; font-weight: bolder;">{{total}}</td>
    </tr>
    {{/_srows}}
</table>
{{/hassummary}}
<table class="ctable sqs-units" border="0" cellpadding="2" cellspacing="0">
    <tr style="font-weight: bolder;">
        <td colspan="11">{{#t}}report.equipmentwhere.title{{/t}} {{#t}}report.equipmentwhere.on_date{{/t}} {{date}} ({{#t}}report.equipmentwhere.units{{/t}}: {{cnt}})</td>
    </tr>
    <tr style="font-weight: bolder;">
        <th style="border: solid black 1px; text-align: left;">{{#t}}common.naimenuvannia{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.equipmentwhere.inv{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.equipmentwhere.where{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.equipmentwhere.recipient{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}common.telefon{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}common.adresa_2{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}common.dohovir{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.equipmentwhere.since{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.equipmentwhere.term{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.equipmentwhere.sent_by{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}common.dokument{{/t}}</th>
    </tr>
    {{#_detail}}
    <tr>
        <td style="border: solid black 1px;">{{itemname}}</td>
        <td style="border: solid black 1px;" data-type="s">{{snumber}}</td>
        <td style="border: solid black 1px;">{{where}}</td>
        <td style="border: solid black 1px;">{{customer}}</td>
        <td style="border: solid black 1px;" data-type="s">{{phone}}</td>
        <td style="border: solid black 1px;">{{address}}</td>
        <td style="border: solid black 1px;" data-type="s">{{contract}}</td>
        <td style="border: solid black 1px;">{{date}}</td>
        <td style="border: solid black 1px;{{#overdue}} color: red; font-weight: bolder;{{/overdue}}">{{term}}</td>
        <td style="border: solid black 1px;">{{sent}}</td>
        <td style="border: solid black 1px;">{{document}}</td>
    </tr>
    {{/_detail}}
</table>
