        {{#sqs_due_show}}
        <div class="card border-warning mb-3" id="sqs-due">
            <div class="card-body">
                <h4>{{#t}}sqs.due.title{{/t}}</h4>
                <small>{{#t}}sqs.due.help{{/t}} <a href="/index.php?p=App/Pages/Report/EquipmentWhere">{{#t}}sqs.due.all{{/t}}</a></small>
                <div class="table-responsive">
                <table class="table table-sm">
                    <tr>
                        <th>{{#t}}sqs.due.customer{{/t}}</th>
                        <th>{{#t}}sqs.due.phone{{/t}}</th>
                        <th>{{#t}}sqs.due.unit{{/t}}</th>
                        <th>{{#t}}sqs.due.contract{{/t}}</th>
                        <th>{{#t}}sqs.due.enddate{{/t}}</th>
                        <th></th>
                    </tr>
                    {{#sqs_due}}
                    <tr>
                        <td>{{customer}}</td>
                        <td>{{phone}}</td>
                        <td>{{unit}}</td>
                        <td>{{contract}}</td>
                        <td>{{enddate}}</td>
                        <td class="{{#overdue}}text-danger fw-bold{{/overdue}}">{{days}}{{#extend}} <a class="sqs-extend btn btn-sm btn-outline-primary ms-2" href="{{extend}}">{{#t}}sqs.extend.link{{/t}}</a>{{/extend}}</td>
                    </tr>
                    {{/sqs_due}}
                </table>
                </div>
            </div>
        </div>
        {{/sqs_due_show}}
