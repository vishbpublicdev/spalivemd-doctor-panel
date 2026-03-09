Ext.define('Ux.Grid', {
    extend: 'Ext.grid.Panel',
    alias: 'widget.uxgrid', cls: 'uxGrid',
    pager: true, pageSize: 20, autoload: true, tplRowExpand: false, remoteSort: false, groupField : false, features: false,
    constructor: function(config){
        var obj = this.getFields(config.model, config.columns);

        config.Model = obj.model; config.columns = obj.columns;

        config.autoload = typeof config.autoload == 'undefined'? this.autoload : config.autoload;
        config.params = config.params || {};
        config.pager = typeof config.pager == 'undefined'? this.pager : config.pager;
        config.remoteSort = typeof config.remoteSort == 'undefined'? this.remoteSort : config.remoteSort;
        config.pageSize = typeof config.pageSize == 'undefined'? this.pageSize : config.pageSize;
        config.groupField = typeof config.groupField == 'undefined'? this.groupField : config.groupField;
        config.features = typeof config.features == 'undefined'? this.features : config.features;
        config.selModel = typeof config.selModel == 'undefined'? this.selModel : config.selModel;

        config.store = config.store || this.__getStore(config);
        // if(config.pager) config.bbar = this.getPager(config.store);
        if(Ext.isDefined(config.bbar) || config.bbar == false){
            // empty
        }else{
            config.bbar = this.getPager(config.pager, config.store);
        }

        config.plugins = config.plugins || [];
        if(config.tplRowExpand) config.plugins.push({ ptype: 'rowexpander', rowBodyTpl : config.tplRowExpand });
        if(config.editable === true){
            config.row_typeadd = config.row_typeadd || 'insert'; // [add | insert]
            config.row_beforeedit = config.row_beforeedit || Ext.emptyFn;
            config.row_edit = config.row_edit || function(editor, context) { context.record.commit(); };

            config.tbar = [{text: 'Agregar', handler: this.onAddRow, scope: this}];
            config.plugins.push(this.getPluginRowEditing(config));
            config.selModel = 'rowmodel';
        }

        this.callParent(arguments);
    },getJson : function(){
        var result = [];
        this.store.each(function(record){
            result.push(record.data);
        });
        return result;
    },onAddRow : function(){
        if(this.row_typeadd == 'insert'){
            this.store.insert(0, new this.Model());
            this.rowEditing.startEdit(0, 0);
        }else{
            this.store.add(new this.Model());
            this.rowEditing.startEdit(this.store.getCount()-1, 0);
        }

        this.getView().refresh();
    },load : function(params){
        Ext.apply(this.store.proxy.extraParams, params || {});
        this.store.currentPage = 1;
        this.store.load();
    },reload : function(callback){
        this.store.reload({
            callback: callback || Ext.emptyFn
        });
    },getPluginRowEditing : function(config){
        return this.rowEditing = Ext.create('Ext.grid.plugin.RowEditing', {
            listeners: {
                beforeedit: config.row_beforeedit,
                edit: config.row_edit,
                cancelEdit: function(rowEditing, context) {
                    // Canceling editing of a locally added, unsaved record: remove it
                    if (context.record.phantom) {
                        this.store.remove(context.record);
                    }
                },scope: this
            }
        });
    },getPager : function(bPager, store){
        if(bPager){
            return Ext.create('Ext.PagingToolbar', {
                store: store, displayInfo: true, displayMsg: 'Showing {0} - {1} of {2}', emptyMsg: 'No data to show'
            });
        }else{
            store.on('load',function() {
                var total = this.getStore().getCount();
                if (this.pager == true)
                    this.labelShow.setText(total > 0? 'Showing '+Ext.util.Format.usNumber(total)+' rows(s)' : 'No data to show');
            },this);

            return [
                {
                    scope: this,
                    iconCls: 'x-tbar-loading',
                    handler:function(){this.getStore().reload();},
                    tooltip: 'Refresh'
                },
                '->',
                this.labelShow = Ext.create('Ext.form.Label', { text: '' })
            ];
            // bbar: [{text: '', iconCls: 'x-tbar-loading', handler: function(){ this.grdImportaciones.store.load(); },scope: this}]
        }
    },getFields : function(model, columns){
        var result = [], fields = [], column;
        for (var x = 0; x < columns.length; x++){
            column = columns[x];
            if((column.dataIndex && column.text) || column.xtype || column.grouped == true) result.push(column);
            if(column.type) fields.push({name: column.dataIndex, type: column.type});
        };

        return {model: Ext.define(model, { extend: 'Ext.data.Model', fields: fields }), columns: result};
    },__getStore : function(config){
        return Ext.create('Ext.data.Store', {
            pageSize: config.pageSize, autoLoad: config.autoload, model : config.model, remoteFilter: true, remoteSort: config.remoteSort,
            proxy: {
                type: 'ajax', url: config.url, extraParams: config.params,
                reader: { type: 'json', root: 'data', totalProperty: 'total', keepRawData: true }
            },
            groupField : config.groupField,
            listeners :{
                load: config.onLoad || Ext.emptyFn,
                scope: config.scope || this
            }
        });
    }
});