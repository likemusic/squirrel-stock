<table class="ctable" border="0" cellpadding="2" cellspacing="0">
    <tr style="font-weight: bolder;">
        <td colspan="10">{{#t}}report.itemturnover.title{{/t}} {{period}}{{#customer}}, {{#t}}report.itemturnover.recipient{{/t}}: {{customer}}{{/customer}}</td>
    </tr>
    <tr style="font-weight: bolder;">
        <th style="border: solid black 1px; text-align: left;">{{#t}}common.naimenuvannia{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}common.kod{{/t}}</th>
        {{#stock}}<th style="border: solid black 1px;">{{#t}}report.itemturnover.start{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.itemturnover.receipt{{/t}}</th>{{/stock}}
        <th style="border: solid black 1px;">{{#t}}report.itemturnover.issued{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.itemturnover.returned{{/t}}</th>
        {{#stock}}<th style="border: solid black 1px;">{{#t}}report.itemturnover.writeoff{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.itemturnover.other{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.itemturnover.end{{/t}}</th>{{/stock}}
        <th style="border: solid black 1px;">{{#t}}report.itemturnover.issues{{/t}}</th>
    </tr>
    {{#_detail}}
    <tr>
        <td style="border: solid black 1px;">{{name}}</td>
        <td style="border: solid black 1px;" data-type="s">{{code}}</td>
        {{#stock}}<td style="border: solid black 1px; text-align: right;">{{start}}</td>
        <td style="border: solid black 1px; text-align: right;">{{receipt}}</td>{{/stock}}
        <td style="border: solid black 1px; text-align: right;">{{issued}}</td>
        <td style="border: solid black 1px; text-align: right;">{{returned}}</td>
        {{#stock}}<td style="border: solid black 1px; text-align: right;">{{writeoff}}</td>
        <td style="border: solid black 1px; text-align: right;">{{other}}</td>
        <td style="border: solid black 1px; text-align: right; font-weight: bolder;">{{end}}</td>{{/stock}}
        <td style="border: solid black 1px; text-align: right;">{{issues}}</td>
    </tr>
    {{/_detail}}
</table>
