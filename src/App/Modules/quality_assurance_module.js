Ext.define('Admin.Tab.Quality', {
    extend: 'Ext.panel.Panel',
    name: 'Admin.Tab.Quality',
    id: 'tabQuality',
    title: 'Quality Assurance',
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


        var store_mds = Ext.create('Ext.data.Store', {
            autoLoad: true,
            fields: [
                { name: 'id', type: 'int' },
                { name: 'name', type: 'string' },
            ],proxy: {
                type: 'ajax', url: App.url('quality'), extraParams: { action: 'grid' },
                reader: { type: 'json', root: 'data', totalProperty: 'count' }
            }
        });

        _this = this;
        this.grid = Ext.create('Ux.Grid', {
            selModel: {
                selType: 'checkboxmodel',
                checkOnly: false,
                mode: 'MULTI',
                injectCheckbox: 'last',
                headerText: 'Test',
                showHeaderCheckbox: false
            },
            listeners: {
                select: function (row, record, index, eOpts) {

                    if (record.get('approved') == 'PENDING') return false;

                    row.deselect(record);

                },
                itemclick: function( view , record , item , index , e , eOpts ) {

                },
                scope: this
            },
            viewConfig: {
                getRowClass: function(record) {
                    if (record.get('approved') == 'PENDING') return '';

                    return 'selection-disabled';
                },
                enableTextSelection: true,
                stripeRows: false, 
                getRowClass: function(record) {
                    var cls = '';

                    if (record.get('approved') != 'PENDING') cls += ' selection-disabled ';
                    if (record.get('login_status') == 'APPROVE') cls += 'green-row';
                    
                    return cls;
                } 
            },
            tbar : [
                { xtype: 'searchfield', width: 300, hideLabel: true, emptyText: 'Search', source: 'grid' }
            ],
            pageSize: 300, autoload: true, border: false, url : App.url('quality'), params : { action: 'grid' }, model:'Modulo', remoteSort: true,
            columns: [
                // { text: 'No', width: 45, dataIndex: 'id', hidden: false, sortable: false, type: 'string' },
                { text: "Injectors's Name", dataIndex: 'injector_name', sortable: false, flex: 1, type: 'string', handler: this.node_delete},
                { text: "Patient's Name", dataIndex: 'pacient_name', sortable: false, flex: 1, type: 'string', handler: this.node_delete},
                { text: "Treatment date", dataIndex: 'schedule_date', sortable: false, flex: 1, type: 'string', handler: this.node_delete,
                    renderer: function(date) {
                        if (!date) return 'None';
                        return moment(date).format('MM-D-YYYY h:mm A');
                    }
                },
                { text: "Survey date", dataIndex: 'created', sortable: false, flex: 1, type: 'string', handler: this.node_delete,
                    renderer: function(date) {
                        if (!date) return 'None';
                        return moment(date).format('MM-D-YYYY h:mm A');
                    }
                },
                { text: 'Experience', dataIndex: 'experience', sortable: false, flex: 1, type: 'string', handler: this.node_delete},
                { text: 'The injector was friendly', dataIndex: 'injector_behave', sortable: false, flex: 1, type: 'string', handler: this.node_delete},
                { text: 'The injector explained <br>after the treatment', dataIndex: 'injector_confident', sortable: false, flex: 1, type: 'string', handler: this.node_delete},
                { text: 'Explanation', dataIndex: 'injector_explain', sortable: false, flex: 1, type: 'string', handler: this.node_delete },
                { text: 'Company Future Suggestions', dataIndex: 'company_future', sortable: false, flex: 1, type: 'string', handler: this.node_delete},
                { text: 'Negative Responses', dataIndex: 'negative_answers', sortable: false, flex: 1, type: 'string', handler: this.node_delete},
                { text: 'Comments to improve', dataIndex: 'done_improve', sortable: false, flex: 1, type: 'string', handler: this.node_delete},

            ]
            ,tplRowExpand : new Ext.XTemplate(
                '<B><b style="color:red;">Negative Responses:</b><br>{negative_answers}</B>',
                '<p><b style="color:green;">Comments to improve:</b><br>{done_improve}</p>',

                {
                    set_files: function(value){
                       return '';
                    }
                }
            ),
        });

        this.grid.store.on( 'beforeload', function( store, options ) {
            var cb = _this.grid.down('combobox');
            if (cb) {
                Ext.apply(store.getProxy().extraParams, {
                       mfilter: cb.getValue()
                    });
            }
        });

         return this.west_panel = Ext.create('Ext.panel.Panel',{
            layout:'fit', activeTab: 0, plain: true, border: true, height: '100%', background: 'red',//bodyStyle: 'border-top: 0 none; border-left: 0 none;',
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
    },node_delete: function(grid, rowIndex, colIndex, item, e, record, row){
        if (!record) return;

        Ext.Msg.confirm('Destail', 'Do you want to delete training notes"' + record.get('title') + '"?', function(btn){
            if(btn == 'yes'){
                
                    this.el.mask('Deleting...');
                    Ext.Ajax.request({
                        url: App.url('trainings'),
                        params: { action: 'grid_notes_delete',id: record.get('id') },
                        success: function(response){
                            this.el.unmask();
                            var json = Ext.decode(response.responseText);
                            if(json.success){
                                this.grid.reload();
                            }
                            App.message(json.success, json.message);
                        },scope: this
                    });
                
            }
        }, this);
    }
});


Ext.define('Wnd.Survey', {
    extend: 'Ext.window.Window',
    title: 'Details', modal: true, border: true, width: 600, height: 550, layout: 'fit',//closeAction: 'hide',
    scrollable: true, overflowY: 'scroll',
    // minimizable: false,
    app : { callback: Ext.emptyFn },

    constructor: function(config){
        Ext.applyIf(config.app, this.app);

        config.items = [];
        config.items.push(this.get_form());

        this.callParent(arguments);
    },afterRender: function() {
        this.callParent(arguments);
        this.load();
    },get_form: function(){
        return this.form_panel = Ext.create('Ext.form.Panel',{
            border: false, bodyStyle: 'background:#FFFFFF;', bodyPadding: 10, defaults: { margin: '0 0 0 0' },
            scrollable: true, autoScroll: true, overflowY: 'scroll',
            items:[
                {
                    xtype: 'fieldset', title: 'Basic Info', layout:'column', defaults: { margin: '0 0 10 0', labelAlign: 'top', labelWidth:80 },
                    items: [
                        { xtype: 'textfield', fieldLabel: 'Injectors Name', name: 'injector_name', columnWidth: 1, allowBlank: false, },
                        { xtype: 'textfield', fieldLabel: 'Patients Name', name: 'pacient_name', columnWidth: 1, allowBlank: true, },    
                    ]
                },
                { xtype: 'hidden', name: 'id', value: '' },
            ]
        });
    }
});
