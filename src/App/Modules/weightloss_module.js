Ext.define('Admin.Tab.WeightLoss', {
    extend: 'Ext.panel.Panel',
    name: 'Admin.Tab.WeightLoss',
    id: 'tabWeightLoss',
    title: 'WeightLoss',
    closable: true,
    layout: 'fit',
    bodyBorder: false,
    constructor: function(config){
        config.items = [];
        config.items.push(this.__src_grid());
        
        this.callParent(arguments);
    },__reload : function() {
        this.grid.reload();
    },
    __src_grid : function(){


        

        _this = this;
        this.grid = Ext.create('Ux.Grid', {                                                
            tbar : [
                { xtype: 'searchfield', width: 300, hideLabel: true, emptyText: 'Search', source: 'grid' },
                //{ xtype: 'combobox', fieldLabel: 'Doctor:', labelWidth: 50, tipo: 'filter', columnWidth: 1, name: 'type', store: store_mds, width: 300, queryMode: 'local', displayField: 'name', valueField: 'id', 
                //allowBlank: false , editable: false, value: 0, hidden: !App.IS_MASTER,listeners : {select : function() {_this.grid.store.reload();}}},
                //'->',
                //{ xtype: 'button', text: 'Approve / Reject multiple treatments', handler: this.on_approve_multiple_treatments, scope: this }
            ],
            pageSize: 50, autoload: true, border: false, url : App.url('weightloss'), params : { action: 'grid' }, model:'Modulo', remoteSort: true,
            columns: [
               
                { text: 'Examiner', dataIndex: 'examiner', sortable: false, flex: 1, type: 'string' },
                { text: 'Patient', dataIndex: 'patient', sortable: false, flex: 1, type: 'string' },               
                //{ text: 'Service', dataIndex: 'service', sortable: false, flex: 1, type: 'string' },
                { text: 'Date', dataIndex: 'date_time', sortable: false, flex: 1, type: 'string', renderer: function(date) {return moment(date).format('MM-D-YYYY h:mm A');} },
                //{ text: 'Date', dataIndex: 'schedule_date', sortable: false, flex: 1, type: 'string', renderer: function(date) {return moment(date).format('MM-D-YYYY h:mm A');} },
                { text: 'State', dataIndex: 'status', sortable: false, flex: 1, type: 'string' },
                { text: 'Payment Status', dataIndex: 'payment_status', sortable: false, flex: 1, type: 'string' ,
                renderer: function(value, metadata, record) {                        
                    if (value == 1) {
                        metadata.tdCls = 'green-row';
                        return 'Yes';
                    } else if (value == 0) {
                        metadata.tdCls = 'yellow-row';
                        return 'No';
                    }
                    metadata.tdCls = 'yellow-row';
                    return value;
                }
            },
                { text: 'Type', dataIndex: 'call_title', sortable: false, flex: 1, type: 'string' },   
                { text: 'Product type', dataIndex: 'product_type', sortable: true, width: 150, type: 'string', hidden: false},
                //{ text: 'Type', dataIndex: 'call_type', sortable: false, flex: 1, type: 'string' },   
                             
                { text: 'Shipping date', dataIndex: 'shipping_date', sortable: false, width: 180, type: 'string' },
                { text: 'Tracking', dataIndex: 'tracking', sortable: false, width: 180, type: 'string' },
                //{ text: 'Schedule Date', dataIndex: 'schedule_date', sortable: false, width: 180, type: 'string' ,
                /*renderer: function(value, metadata, record) {                        
                    if (record.get('status') ==  'SCHEDULED') {                        
                        return moment(value).format('MM-D-YYYY h:mm A');
                    } else {                        
                        return '';
                    } 
                }*/
            //}
                
            ],tplRowExpand : new Ext.XTemplate(
               `<p><b>Questions:</b></p>               
               <p></p>               
                <tpl for="Questions">
                <p><b><span class="cert-link-">{question}</span></b></br> <span class="cert-link-">{answer}</span></p>
                </tpl>               
               `,
                    
            ),
        });

       

         return this.west_panel = Ext.create('Ext.panel.Panel',{
          
            layout:'fit',       activeTab: 0, plain: true, border: true, height: '100%', background: 'red',//bodyStyle: 'border-top: 0 none; border-left: 0 none;',
            
            items: [
                this.grid,
            ]
        });

    },reload: function(callback){
        this.grid.reload(callback);
        
    },node_edit: function(grid, rowIndex, colIndex, item, e, record, row){
        Ext.create('Wnd.ApprTreatment', {
            app: {record: record, callback: Ext.bind(function(){ this.reload(); }, this)}
        }).show();
    }, on_approve_multiple_treatments: function() {
        var selectedRecords = this.grid.getSelectionModel().getSelected().items;
        
        if (selectedRecords.length == 0) App.message(false, 'You must select a record(s) to approve.');
        else {
            // Ext.Msg.confirm('Authorize payments', 'Do you want to approve / reject ' + selectedRecords.length + ' treatment(s)?', function(btn){
            //     if(btn == 'yes'){
                    Ext.create('Wnd.ApprTreatment', {
                        title: 'Evaluate ' + selectedRecords.length + ' treatment(s)',
                        app: {multiple: true, selections: selectedRecords, callback: Ext.bind(function(){ this.reload(); }, this)}
                    }).show();
            //     }
            // }, this);
        }
    },
});


