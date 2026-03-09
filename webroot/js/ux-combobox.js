Ext.define('Ux.ComboBox', {
    extend: 'Ext.form.field.ComboBox',
    alias: 'widget.uxcombobox',
    // autoload: true, tplRowExpand: false, remoteSort: false,
    constructor: function(config){

        config.autoload = true;
        config.remoteSort = false;

        if(!config.store){
            config.store = this.__getStore(config);
        }

        config.valueField = 'uid';
        config.displayField = 'name';

        config.anyMatch = true;
        config.minChars = 0;
        config.triggerAction = 'all';
        config.queryMode = 'local';

        config.triggers = {
            picker: { cls: Ext.baseCSSPrefix+'form-clear-trigger', handler: function(field,trigger,event){ field.reset(); }, scope: this },
            picker2: { handler: function(field,trigger,event){
                field.onTriggerClick(field,trigger,event);
            }, scope: this }
        };

        // ,combobox_permisos: function(params){
        //     var params_config = Ext.apply({
        //         emptyText: 'Permiso', store: storeSearch,
        //         triggers: {
        //             picker: { cls: Ext.baseCSSPrefix+'form-clear-trigger', handler: function(field,trigger,event){ field.reset(); }, scope: this },
        //             picker2: { handler: function(field,trigger,event){ field.onTriggerClick(event); }, scope: this }
        //         },
        //         displayField: 'Permiso_Nombre', valueField: 'Permiso_Uid',
        //         anyMatch: true, queryMode: 'local', minChars: 0, triggerAction: 'all',
        //         listConfig: {
        //             minWidth: 300, maxHeight: 400,
        //             loadingText: 'Buscando...',
        //             emptyText: 'No se encontraron resultados.',
        //             cls: 'cls-combobox',
        //             getInnerTpl: function() {
        //                 return  '<div class="ux-item ux-margin-{Permiso_Level}">' +
        //                             '<p><b>{Permiso_Nombre}</b></p>' +
        //                             '<p class="subtitle">{Permiso_Path}</p>' +
        //                         '</div>';
        //             }
        //         }
        //     }, params);

        //     return Ext.create('Ext.form.field.ComboBox', params_config);
        // }

        this.callParent(arguments);
    },load : function(params){
        Ext.apply(this.store.proxy.extraParams, params || {});
        this.store.load();
    },reload : function(){
        this.store.reload();
    },__getStore : function(config){
        return Ext.create('Ext.data.Store', {
            fields: config.fields, autoLoad: config.autoload, /*model : config.model, remoteFilter: true, remoteSort: config.remoteSort*/
            proxy: {
                type: 'ajax', url: config.url, extraParams: config.params,
                reader: { type: 'json', root: 'data', totalProperty: 'total' }
            },
            // listeners :{
            //     load: config.onLoad || Ext.emptyFn,
            //     scope: config.scope || this
            // }
        });
    }
});

// var storeSearch = Ext.create('Ext.data.Store', {
//     fields:['Permiso_Uid','Permiso_Nombre','Permiso_Level','Permiso_Path'], autoLoad: true,
//     proxy: { type: 'ajax', url: Panel.url('permisos'), extraParams:{ action: 'combobox' }, reader: { type: 'json', root: 'data' } }
// });
