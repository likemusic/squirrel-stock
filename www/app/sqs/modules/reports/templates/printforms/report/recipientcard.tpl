<table class="ctable sqs-card-contacts" border="0" cellpadding="2" cellspacing="0">
    <tr style="font-weight: bolder;">
        <td colspan="2"><h4>{{#t}}report.recipientcard.title{{/t}}: {{name}}</h4></td>
    </tr>
    {{#phone}}<tr><td style="width: 180px;">{{#t}}common.telefon{{/t}}</td><td>{{phone}}</td></tr>{{/phone}}
    {{#email}}<tr><td>Email</td><td>{{email}}</td></tr>{{/email}}
    {{#address}}<tr><td>{{#t}}report.recipientcard.address{{/t}}</td><td>{{address}}</td></tr>{{/address}}
    {{#tags}}<tr><td>{{#t}}report.recipientcard.tags{{/t}}</td><td>{{tags}}</td></tr>{{/tags}}
    {{#comment}}<tr><td>{{#t}}report.recipientcard.comment{{/t}}</td><td>{{comment}}</td></tr>{{/comment}}
    {{#_sent}}<tr><td>{{#t}}report.recipientcard.sent_by{{/t}}</td><td>{{sent}} ({{contract}})</td></tr>{{/_sent}}
    <tr><td>{{#t}}report.recipientcard.period{{/t}}</td><td>{{period}}</td></tr>
</table>

{{#equipment}}
<h5 class="mt-3">{{#t}}report.recipientcard.on_hand{{/t}}</h5>
{{#hasunits}}
<table class="ctable sqs-card-units" border="0" cellpadding="2" cellspacing="0">
    <tr style="font-weight: bolder;">
        <th style="border: solid black 1px;">{{#t}}common.tovar{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.recipientcard.inv{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}common.kilkist{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.recipientcard.issued_on{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.recipientcard.contract{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.recipientcard.term{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.recipientcard.sent_by{{/t}}</th>
    </tr>
    {{#_units}}
    <tr>
        <td style="border: solid black 1px;">{{itemname}}</td>
        <td style="border: solid black 1px;">{{snumber}}{{#factory}}, {{#t}}sqs.unit.factory{{/t}} {{factory}}{{/factory}}</td>
        <td style="border: solid black 1px; text-align: right;">{{qty}}</td>
        <td style="border: solid black 1px;">{{date}}, <a class="sqs-doc" href="{{docurl}}">{{document}}</a></td>
        <td style="border: solid black 1px;">{{contract}}</td>
        <td style="border: solid black 1px;{{#overdue}} color: red;{{/overdue}}">{{term}}{{#extend}} <a class="sqs-extend btn btn-sm btn-outline-primary ms-1 py-0 d-print-none" href="{{extend}}">{{#t}}report.recipientcard.extend{{/t}}</a>{{/extend}}</td>
        <td style="border: solid black 1px;">{{sent}}</td>
    </tr>
    {{/_units}}
</table>
{{/hasunits}}
{{^hasunits}}<p>{{#t}}report.recipientcard.none{{/t}}</p>{{/hasunits}}
{{/equipment}}

<h5 class="mt-3">{{#t}}report.recipientcard.consumables{{/t}} {{period}}</h5>
{{#hasitems}}
<table class="ctable sqs-card-items" border="0" cellpadding="2" cellspacing="0">
    <tr style="font-weight: bolder;">
        <th style="border: solid black 1px;">{{#t}}common.tovar{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}common.od_vym{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.recipientcard.issued{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.recipientcard.returned{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.recipientcard.net{{/t}}</th>
    </tr>
    {{#_items}}
    <tr>
        <td style="border: solid black 1px;">{{itemname}}</td>
        <td style="border: solid black 1px;">{{msr}}</td>
        <td style="border: solid black 1px; text-align: right;">{{issued}}</td>
        <td style="border: solid black 1px; text-align: right;">{{returned}}</td>
        <td style="border: solid black 1px; text-align: right;">{{net}}</td>
    </tr>
    {{/_items}}
    <tr style="font-weight: bolder;">
        <td style="border: solid black 1px;" colspan="4">{{#t}}report.recipientcard.total{{/t}} ({{#t}}report.recipientcard.issues{{/t}}: {{issues}})</td>
        <td style="border: solid black 1px; text-align: right;">{{total}}</td>
    </tr>
</table>

<h5 class="mt-3">{{#t}}report.recipientcard.by_months{{/t}}</h5>
<table class="ctable sqs-card-months" border="0" cellpadding="2" cellspacing="0">
    <tr style="font-weight: bolder;">
        <th style="border: solid black 1px; text-align: left;">{{#t}}common.tovar{{/t}}</th>
        {{#_months}}<th style="border: solid black 1px; min-width: 60px;">{{m}}</th>{{/_months}}
        <th style="border: solid black 1px;">{{#t}}report.recipientcard.total{{/t}}</th>
    </tr>
    {{#_mrows}}
    <tr>
        <td style="border: solid black 1px;">{{itemname}}</td>
        {{#cells}}<td style="border: solid black 1px; text-align: center;">{{v}}</td>{{/cells}}
        <td style="border: solid black 1px; text-align: center;">{{net}}</td>
    </tr>
    {{/_mrows}}
    <tr style="font-weight: bolder;">
        <td style="border: solid black 1px;">{{#t}}report.recipientcard.issues{{/t}}</td>
        {{#_mdocs}}<td style="border: solid black 1px; text-align: center;">{{n}}</td>{{/_mdocs}}
        <td style="border: solid black 1px; text-align: center;">{{issues}}</td>
    </tr>
</table>
{{/hasitems}}
{{^hasitems}}<p>{{#t}}report.recipientcard.none{{/t}}</p>{{/hasitems}}

<h5 class="mt-3">{{#t}}report.recipientcard.history{{/t}} {{period}}</h5>
{{#hashistory}}
<table class="ctable sqs-card-history" border="0" cellpadding="2" cellspacing="0">
    <tr style="font-weight: bolder;">
        <th style="border: solid black 1px;">{{#t}}common.data{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.recipientcard.document{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.recipientcard.what{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.recipientcard.state{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.recipientcard.notes{{/t}}</th>
    </tr>
    {{#_history}}
    <tr>
        <td style="border: solid black 1px;">{{date}}</td>
        <td style="border: solid black 1px;">{{type}} <a class="sqs-doc" href="{{docurl}}">{{number}}</a>{{#contract}}<br>{{contract}}{{/contract}}</td>
        <td style="border: solid black 1px;">{{#_lines}}{{text}}<br>{{/_lines}}</td>
        <td style="border: solid black 1px;">{{state}}</td>
        <td style="border: solid black 1px;">{{notes}}</td>
    </tr>
    {{/_history}}
</table>
{{/hashistory}}
{{^hashistory}}<p>{{#t}}report.recipientcard.none{{/t}}</p>{{/hashistory}}

{{#intervals}}
{{#hasnext}}
<h5 class="mt-3">{{#t}}report.recipientcard.next{{/t}}</h5>
<table class="ctable sqs-card-intervals" border="0" cellpadding="2" cellspacing="0">
    <tr style="font-weight: bolder;">
        <th style="border: solid black 1px;">{{#t}}report.recipientcard.rule{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.recipientcard.interval{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.recipientcard.last{{/t}}</th>
        <th style="border: solid black 1px;">{{#t}}report.recipientcard.next_date{{/t}}</th>
        <th style="border: solid black 1px;"></th>
    </tr>
    {{#_next}}
    <tr>
        <td style="border: solid black 1px;">{{what}}</td>
        <td style="border: solid black 1px; text-align: right;">{{days}}</td>
        <td style="border: solid black 1px;">{{last}}</td>
        <td style="border: solid black 1px;">{{next}}</td>
        <td style="border: solid black 1px;{{^can}} color: #b45309;{{/can}}">{{wait}}</td>
    </tr>
    {{/_next}}
</table>
{{/hasnext}}
{{/intervals}}
<p class="mt-2">{{#t}}report.recipientcard.made{{/t}} {{date}}</p>
