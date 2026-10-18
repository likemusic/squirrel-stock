<table class="ctable sqs-writeoffs" border="0" cellpadding="2" cellspacing="0">
    <thead>
    <tr style="font-weight: bolder;">
        <td colspan="7"><h4>{{#t}}report.writeoffs.title{{/t}} {{period}}</h4></td>
    </tr>
    {{#hasrows}}
    <tr style="font-weight: bolder;">
        <th style="border: solid black 1px;">{{#t}}common.data{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.writeoffs.document{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}common.sklad{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}common.tovar{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.writeoffs.inv{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}common.kilkist{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.writeoffs.reason{{/t}}</th>
    </tr>
    {{/hasrows}}
    </thead>
    <tbody>
    {{#hasrows}}
    {{#_rows}}
    <tr>
        <td style="border: solid black 1px;">{{date}}</td>
        <td style="border: solid black 1px;"><a class="sqs-doc" href="{{docurl}}">{{number}}</a></td>
        <td style="border: solid black 1px;">{{store}}</td>
        <td style="border: solid black 1px;">{{itemname}}</td>
        <td style="border: solid black 1px;">{{snumber}}</td>
        <td style="border: solid black 1px; text-align: right;">{{qty}} {{msr}}</td>
        <td style="border: solid black 1px;">{{reason}}</td>
    </tr>
    {{/_rows}}
    <tr style="font-weight: bolder;">
        <td style="border: solid black 1px;" colspan="5">{{#t}}report.writeoffs.total{{/t}} ({{#t}}report.writeoffs.docs{{/t}}: {{docs}})</td>
        <td style="border: solid black 1px; text-align: right;">{{total}}</td>
        <td style="border: solid black 1px;"></td>
    </tr>
    {{/hasrows}}
    {{^hasrows}}<tr><td colspan="7">{{#t}}report.writeoffs.none{{/t}}</td></tr>{{/hasrows}}
    </tbody>
</table>
<p class="mt-2">{{#t}}report.writeoffs.made{{/t}} {{date}}</p>
