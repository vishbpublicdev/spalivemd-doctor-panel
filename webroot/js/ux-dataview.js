Ext.define('Ux.DataView', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.uxdataview',
    layout: 'fit',
    //  pageSize: 20, autoload: true, tplRowExpand: false, remoteSort: false,
    constructor: function(config){
        // var obj = this.getFields(config.model, config.columns);
        // config.Model = obj.model; config.columns = obj.columns;

        // config.bbartool = Ext.isDefined(config.bbartool)? config.bbartool : 'pagingtool';
        config.autoload = Ext.isDefined(config.autoload)? config.autoload : true;


        // config.params = config.params || {};
        // alert(config.autoload);
        // config.breadcrumb = Ext.isDefined(config.breadcrumb)? config.breadcrumb : false;
        // config.remoteSort = typeof config.remoteSort == 'undefined'? this.remoteSort : config.remoteSort;
        // config.pageSize = typeof config.pageSize == 'undefined'? this.pageSize : config.pageSize;
        // config.store = config.store || this.__getStore(config);
        // // if(config.pager) config.bbar = this.getPager(config.store);
        // if(Ext.isDefined(config.bbar) || config.bbar == false){
        //     // empty
        // }else{
        //     config.bbar = this.getPager(config.pager, config.store);
        // }
        config._listeners = Ext.isDefined(config.listeners)? config.listeners : [];
        delete config.listeners;
// console.log('constructor',config);
        // config.plugins = [];
        // if(config.tplRowExpand) config.plugins.push({ ptype: 'rowexpander', rowBodyTpl : config.tplRowExpand });
        // if(config.editable === true){
        //     config.row_typeadd = config.row_typeadd || 'insert'; // [add | insert]
        //     config.row_beforeedit = config.row_beforeedit || Ext.emptyFn;
        //     config.row_edit = config.row_edit || function(editor, context) { context.record.commit(); };

        //     config.tbar = [{text: 'Agregar', handler: this.onAddRow, scope: this}];
        //     config.plugins.push(this.getPluginRowEditing(config));
        //     config.selModel = 'rowmodel';
        // }

        this.callParent(arguments);
    },initComponent: function() {
        var me = this;
        // console.log('initComponent',me,Ext.isDefined(me.bbar));
        me.store = me.store || me.__getStore();

        me.items = me.__getView();
        me.bbar = Ext.isDefined(me.bbartool)? me.bbartool : me.__getBottomBar();

        me.listeners = {
            afterrender: function(){
                if(this.autoLoad == true){
                    this.store.reload();
                }
            },
            scope: me
        };

        me.callParent();
    },__getBottomBar : function(){
        var me = this;

        if(me.bbartool == 'single'){
            return { xtype: 'pagingtoolbar', store: me.store, displayInfo: true, displayMsg: 'Showing {0} - {1} de {2}', emptyMsg: 'Sin datos para mostrar' };
        }else{
            // me.store.on('load',function() {
            //     // var total = this.getStore().getCount();
            //     // if (this.pager == true)
            //     //     this.labelShow.setText(total > 0? 'Mostrando '+Ext.util.Format.usNumber(total)+' registro(s)' : 'Sin datos para mostrar');
            // },me);

            return [
                {  tooltip: 'Refresh', iconCls: 'x-tbar-loading', handler:function(){ this.reload(); }, scope: this },
                '->',
                this.labelShow = Ext.create('Ext.form.Label', { text: '' })
            ];
        }
    },__getView : function(){
        var me = this;

        // config._listeners

        return me.view = Ext.create('Ext.view.View', {
            store: me.store, tpl: me.tpl || me.__getTemplate(), cls: me.cls_view || 'ux-dataview',
            itemSelector: 'div.thumb-wrap', overItemCls: 'item-over', scrollable: true,
            emptyText: '<span style="margin: 10px;display: inline-block;">No data to show..</span>', //style: 'overflow-y: auto',
            listeners: this._listeners  /*{
                itemdblclick: me.itemdblclick || Ext.emptyFn,
                itemclick: me.itemclick || Ext.emptyFn,
                itemcontextmenu: me.itemcontextmenu || Ext.emptyFn,
                containercontextmenu: me.containercontextmenu || Ext.emptyFn,
                scope: me.scope || me
            }*/
        });
    },__getTemplate : function(){
        return new Ext.XTemplate(
            '<tpl for=".">',
                '<div class="thumb-wrap">',
                    '<div class="content">',
                        '<p class="label">{label}</p>',
                        '<h4>{title}</h4>',
                        '<p class="detail">{detail}</p>',
                        '<div class="info">',
                            '<p style="float:left; text-align: left;">{info1}</p>',
                            '<p style="float:right; text-align: right;">{info2}</p>',
                        '</div>',
                    '</div>',
                '</div>',
            '</tpl>'
        );
    },__getStore : function(){
        var me = this;

        return Ext.create('Ext.data.Store', {
            // fields: ['Category_Uid', 'Category_Title'],
            autoLoad: me.autoload,
            proxy: {
                type: 'ajax',
                url: me.url,
                extraParams: me.params,
                reader: { type: 'json', rootProperty : 'data', totalProperty: 'total', messageProperty : 'error' }
            },
            // listeners: {
            //     load: me.loadData || Ext.emptyFn
            // }
        });
        // return {
        //     // pageSize: config.pageSize, autoLoad: config.autoload, model : config.model, remoteFilter: true, remoteSort: config.remoteSort,
        //     proxy: {
        //         type: 'ajax', url: config.url, extraParams: config.params,
        //         reader: { type: 'json', root: 'data', totalProperty: 'total' }
        //     },
        //     listeners :{
        //         load: config.onLoad || Ext.emptyFn,
        //         scope: config.scope || this
        //     }
        // };
    },onAddRow : function(){
        if(this.row_typeadd == 'insert'){
            this.store.insert(0, new this.Model());
            this.rowEditing.startEdit(0, 0);
        }else{
            this.store.add(new this.Model());
            this.rowEditing.startEdit(this.store.getCount()-1, 0);
        }

        this.getView().refresh();
    },load : function(params, callback){
        Ext.apply(this.store.proxy.extraParams, params || {});
        if(this.treestore) Ext.apply(this.treestore.proxy.extraParams, params || {});
// console.log('callback',params,callback);
        this.store.load({
            scope: this,
            callback: callback || Ext.emptyFn
        });

        // this.store.load({
        //     scope: this,
        //     callback: function(records, operation, success) {
        //         // the operation object
        //         // contains all of the details of the load operation
        //         console.log('store.load',records, operation, success);
        //         console.log(operation._response.responseJson);
        //         // console.log(operation.response.responseText);
        //     }
        // });

        if(this.treestore) this.treestore.load();
    },reload : function(){
        this.store.reload();
    }/*,getPager : function(bPager, store){
        if(bPager){
            return Ext.create('Ext.PagingToolbar', {
                store: store, displayInfo: true, displayMsg: 'Mostrando {0} - {1} de {2}', emptyMsg: 'Sin datos para mostrar'
            });
        }else{
            store.on('load',function() {
                var total = this.getStore().getCount();
                if (this.pager == true)
                    this.labelShow.setText(total > 0? 'Mostrando '+Ext.util.Format.usNumber(total)+' registro(s)' : 'Sin datos para mostrar');
            },this);

            return [
                {
                    scope: this,
                    iconCls: 'x-tbar-loading',
                    handler:function(){this.getStore().reload();},
                    tooltip: 'Actualizar'
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
            if((column.dataIndex && column.text) || column.xtype) result.push(column);
            if(column.type) fields.push({name: column.dataIndex, type: column.type});
        };

        return {model: Ext.define(model, { extend: 'Ext.data.Model', fields: fields }), columns: result};
    }*/
});
