Ext.define('Admin.Tab.Settings', {
    extend: 'Ext.panel.Panel',
    name: 'Admin.Tab.Settings',
    id: 'tabSettings',
    title: 'Settings',
    closable: true,
    layout: 'border',
    bodyBorder: false,
    constructor: function(config){
        config.items = [];

        config.items.push(this.__src_west());
        config.items.push(this.__src_detail());
        config.items.push(this.__src_south());

        this.callParent(arguments);
    },__reload : function() {
        this.grid.reload();
    },
    __src_west : function(){

        var store_filter = Ext.create('Ext.data.Store', {
            fields: ['id', 'name'],
            data : [
                {'id':'ACTIVE USERS', 'name': 'ACTIVE USERS'},
                {'id':'INACTIVE USERS', 'name': 'INACTIVE USERS'},
                {'id':'DELETED USERS', 'name': 'DELETED USERS'},
                {'id':'LIKELY NOT AN INJECTOR', 'name': 'LIKELY NOT AN INJECTOR'},
                {'id':'LOOKING FOR PROVIDER', 'name': 'LOOKING FOR PROVIDER'},
                {'id':'KNOWS A PROVIDER', 'name': 'KNOWS A PROVIDER'},
                {'id':'NONE', 'name': 'NONE'},
                {'id':'INJECTORS', 'name': 'INJECTORS'},
                {'id':'WEIGHT LOSS', 'name': 'WEIGHT LOSS'},
            ]
        });
        var _this = this;
        this.grid = Ext.create('Ux.Grid', {
            tbar : [
                { xtype: 'searchfield', width: 300, hideLabel: true, emptyText: 'Search', source: 'grid' }, '-',//'->',
                { xtype: 'combobox', fieldLabel: 'Filter:', labelWidth: 50, tipo: 'filter', columnWidth: 1, name: 'type', store: store_filter, width: 300, queryMode: 'local', displayField: 'name', valueField: 'id', 
                allowBlank: false , editable: false, value: 'ACTIVE USERS',
                        listeners : {
                            select : function() {
                                _this.grid.store.reload();
                            }
                    }
                 },
                 { xtype: 'button', text:'PDF', handler: this.on_csv_patients, scope: this, hidden: false, width:150},
            ],
            pageSize: 100, autoload: true, border: false, url : App.url('patients'), params : { action: 'grid', type : 'injector'}, model:'Modulo', remoteSort: true,
            columns: [               
                { text: 'UID', width: 300, dataIndex: 'uid', hidden: false, sortable: false, type: 'string' },
                { text: 'Name', dataIndex: 'name', sortable: true, width: 300, type: 'string' },                                                                                                
                { text: 'username', dataIndex: 'username', sortable: true, width: 300, type: 'string' },                                                                                                
            ],
            onLoad: function(store, records, successful, operation, eOpts){ 
                var data = store.getProxy().getReader().rawData;
                this.down('dataview[uid=summary]').setData(data.summary);
            },
            scope: this,
            listeners:{               
                cellclick: function( view , td, cellIndex, record, tr, rowIndex, e, eOpts ) {
                    if (cellIndex == 8) {
                        this.node_sales(null, null, null, null, null, record, null);                    
                    } else if (cellIndex == 11) {
                        this.node_notes(null, null, null, null, null, record, null);                    
                    } else if (cellIndex == 10) {
                        this.node_welcome(null, null, null, null, null, record, null);                    
                    } else if(cellIndex >= 0) {
                        this.west_panel.expand();
                        this.node_load(null, null, null, td, e, record );
                    }  
                },
                scope: this
            },
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
            layout:'fit', region: 'center', activeTab: 0, plain: true, border: true, height: '100%', background: 'red',//bodyStyle: 'border-top: 0 none; border-left: 0 none;',
            items: [
                this.grid,
            ]
        });

    },__src_south: function(){
        return Ext.create('Ext.panel.Panel',{
            layout:'fit', region: 'south', activeTab: 0, plain: true, border: true, height: 40, background: 'red',//bodyStyle: 'border-top: 0 none; border-left: 0 none;',
            collapsible: false, 
            items: [
                { xtype: 'dataview', tpl: this.tpl_summary(), uid: 'summary'}
            ]
        });
    },__src_detail : function() {

        var store_states = Ext.create('Ext.data.Store', {
            autoLoad: true,
            fields: [
                { name: 'id', type: 'int' },
                { name: 'name', type: 'string' },
            ],proxy: {
                type: 'ajax', url: App.url('patients'), extraParams: { action: 'cat_states' },
                reader: { type: 'json', root: 'data', totalProperty: 'count' }
            }
        });


        this.form_panel = Ext.create('Ext.form.Panel', {
            scrollable: true, region: 'center', width: '100%', overflowY: 'scroll',
            items : [{
                xtype: 'fieldset', title: 'Settings:', layout: { type: 'table', columns: 1 }, defaults: { labelAlign: 'top', labelWidth: 110, margin: '0 15px 0 0', emptyText: '' }, margin: '0 20 20 20',
                items: [            
                    //{ xtype: 'displayfield',fieldLabel: 'Home',name: 'home_score',value: '10'},                                                         
                    {
                        xtype: 'label',forId: 'myFieldId',text: 'You approve the following treatments:',margin: '0 0 0 10'},
                    { xtype: 'checkbox', name: 'Neurotoxin', boxLabel: 'Neurotoxin', inputValue: '1', uncheckedValue: '1',checked: true, disabled:true},
                    { xtype: 'checkbox', name: 'IV Therapy', boxLabel: 'IV', inputValue: '1', uncheckedValue: '0',checked: true, disabled:true},
                    { xtype: 'checkbox', name: 'Fillers', boxLabel: 'Fillers', inputValue: '1', uncheckedValue: '0',checked: true, disabled:true},
                    
                ]},{ xtype: 'hidden', fieldLabel: 'UID', name: 'uid' },
            ],
            buttons: [
                //{ text: 'Save', handler: function(){ this.node_save(); },scope: this},                                
            ]
        });
 
        return this.west_panel = Ext.create('Ext.panel.Panel',{
            layout: 'border', region: 'east', title: 'Detail', collapsible: true, collapsed: true, activeTab: 0, plain: true, border: true, width: 500, //bodyStyle: 'border-top: 0 none; border-left: 0 none;',
            items: [
                this.form_panel,                
            ]            
        });


    },node_load: function(grid, rowIndex, colIndex, item, e, record, row){

        this.on_clear(false);


        this.el.mask('Loading...');
        this.form_panel.getForm().load({
            url: App.url('patients'),
            params: { action: 'load', uid : record.data.uid },
            success: function(form, action){
                this.el.unmask();

                var json = action.result;
                if(json.success){
                    this.west_panel.expand();

                    if (this.form_panel.down('image[uid=drive_licence]') ){
                     if (json.data.driver_lic_id > 0)
                        this.form_panel.down('image[uid=drive_licence]').setSrc(App.env_url + '?key=2fe548d5ae881ccfbe2be3f6237d7951&l3n4p=6092482f7ce858.91169218&action=get-file&token=6092482f7ce858.91169218&id=' + json.data.driver_lic_id);
                    else 
                        this.form_panel.down('image[uid=drive_licence]').setSrc('https://cdn.pixabay.com/photo/2015/10/05/22/37/blank-profile-picture-973460_1280.png');
                    }
                }
                if(json.msg) Ext.Msg.alert('Aviso', json.msg);
            },
            failure: function(form, action){
                this.el.unmask();
                if(action.result.msg) Ext.Msg.alert('Aviso', action.result.msg);
            },scope: this
        });       
    },on_clear : function(force){
        this.form_panel.reset();
        //this.form_panel.down('datefield').setValue( new Date());                        
        if (force)
            this.grid.getSelectionModel().deselectAll();

        this.west_panel.collapse();
    },reload: function(callback){
        this.grid.reload(callback);
    },
    tpl_summary: function(){
        return new Ext.XTemplate(
            `<p style="padding-left: 10px">
            <b>total: {total}</b>&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;
            
            </p>`
        );
    }
    
});







