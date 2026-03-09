Ext.define('Admin.Tab.Modulos', {
    extend: 'Ext.panel.Panel',
    name: 'Admin.Tab.Modulos',
    id: 'tabModulos',
    title: 'Módulos',
    closable: true,
    layout: 'fit',
    constructor: function(config){
        config.items = [];

        config.items.push(this.__src_center());

        this.callParent(arguments);
    },
    __src_center : function(){
        return this.grid = Ext.create('Ux.Grid', {
            tbar : [
                { xtype: 'searchfield', width: 300, hideLabel: true, emptyText: 'Buscar', source: 'grid' }, '-','->',
                { text:'Agregar Módulo', iconCls:'x-fa fa-plus', closable: false, handler: this.node_new, scope: this }
            ],
            pageSize: 50, autoload: true, border: false, url : App.url('modulos'), params : { action: 'grid' }, model:'Modulo', remoteSort: true,
            columns: [
                { text: 'UID', width: 45, dataIndex: 'uid', hidden: true, sortable: false, type: 'string' },
                { text: 'Nombre', dataIndex: 'name', sortable: false, width: 300, type: 'string' },
                { text: 'Descripción', dataIndex: 'description', sortable: false, flex: 1, type: 'string' },
                { text: 'Permiso', dataIndex: 'permissions', sortable: false, width: 300, type: 'string' },
                { text: 'Activo', width: 80, dataIndex: 'active', sortable: false, align: 'center', renderer: function (value, record) { return value == 1? 'Si' : 'No'; } },
                {
                    xtype: 'actioncolumn', width: 70, sortable: false,
                    items: [
                        { iconCls: 'x-fa fa-edit', tooltip: 'Editar registro', scope: this, handler: this.node_edit },
                        { iconCls: 'x-fa fa-trash-alt', tooltip: 'Eliminar registro', scope: this, handler: this.node_delete },
                    ]
                }
            ],
            listeners:{
                itemdblclick: function( view , record , item , index , e , eOpts ) {
                    this.node_edit(null, null, null, item, e, record );
                },
                scope: this
            },
        });
    },reload: function(){
        this.grid.reload();
    },node_edit: function(grid, rowIndex, colIndex, item, e, record, row){
        Ext.create('Wnd.Modulo',{
            title: 'Módulo: '+record.get('name'),
            app: {
                record: record,
                callback: Ext.bind(function(){ this.reload(); }, this),
            }
        }).show(e.target);
    },node_new: function(){
        Ext.create('Wnd.Modulo',{
            title: '*Nuevo Módulo',
            app: {
                Modulo_Uid: '',
                callback: Ext.bind(function(){ this.reload(); }, this),
            }
        }).show();
    },node_delete: function(grid, rowIndex, colIndex, item, e, record, row){
        Ext.Msg.confirm('Eliminar', '¿Desea eliminar el Módulo "' + record.get('name') + '"?', function(btn){
            if(btn == 'yes'){
                if (record) {
                    this.el.mask('Eliminando...');
                    Ext.Ajax.request({
                        url: App.url('modulos'),
                        params: { action: 'delete', uid: record.get('uid') },
                        success: function(response){
                            this.el.unmask();
                            var json = Ext.decode(response.responseText);
                            if(json.success){
                                this.reload();
                            }
                            App.message(json.success, json.message);
                        },scope: this
                    });
                }
            }
        }, this);
    }
});

Ext.define('Wnd.Modulo', {
    extend: 'Ext.window.Window',
    title: 'Módulo', modal: true, border: false, width: 650, height: 500, layout: 'fit', //closeAction: 'hide',
    minimizable: false,
    app : { callback: Ext.emptyFn, Modulo_Uid: '' },
    constructor: function(config){
        Ext.applyIf(config.app, this.app);

        config.items = [];
        config.items.push(this.get_form());

        config.buttons = [
            ,{text: 'Guardar', handler: function(){ this.save(); },scope: this}
            ,{text: 'Cancelar', handler: function(){ this.close(); },scope: this}
        ];

        this.callParent(arguments);
    },afterRender: function() {
        this.callParent(arguments);

        this.load();
    },get_form: function(){
        return this.form_panel = Ext.create('Ext.form.Panel',{
            border: false, bodyStyle: 'background:#FFFFFF;', bodyPadding: 10, defaults: { margin: '0 0 0 0' },
            items:[
                {
                    xtype: 'fieldset', title: 'Detalles', layout:'column', defaults: { margin: '0 0 10 0', labelWidth:80 },
                    items: [
                        { xtype: 'textfield', fieldLabel: 'Nombre', name: 'name', columnWidth: 1, allowBlank: false },
                        { xtype: 'textfield', fieldLabel: 'Archivo', name: 'file', columnWidth: 1, allowBlank: false },
                        { xtype: 'textfield', fieldLabel: 'Controller', name: 'controller', columnWidth: 1, allowBlank: false },
                        // { xtype: 'textfield', fieldLabel: 'Class', name: 'Modulo_Name', columnWidth: 1, allowBlank: false },
                        { xtype: 'uxcombobox', fieldLabel: 'Permiso', name: 'permission_uid', url: App.url('permisos'), params: { action: 'combobox' }, columnWidth: 1, allowBlank: false },
                        // App.combobox_permisos({ fieldLabel: 'Permiso', name: 'Modulo_PermisoUid', columnWidth: 1, allowBlank: true }),
                        { xtype: 'textareafield', fieldLabel: 'Descripción', name: 'description', columnWidth: 1, height: 100, allowBlank: true },
                        { xtype: 'checkboxfield', fieldLabel: 'Activo', name: 'active', columnWidth: 1 }
                    ]
                }
                ,{ xtype: 'hidden', name: 'uid', value: '' }
            ]
        });
    },load: function(record){
        if(!this.app.record) return;

        this.el.mask('Cargando...');
        this.form_panel.getForm().load({
            url: App.url('modulos'),
            params: { action: 'load', uid : this.app.record.get('uid') },
            success: function(form, action){
                this.el.unmask();

                var json = action.result;
                if(json.success){
                }

                App.message(json.success, json.message);
            },
            failure: function(form, action){
                this.el.unmask();

                App.message(false, json.message);
            },scope: this
        });
    },save: function(){
        var form = this.form_panel.getForm();
        if (form.isValid()){
            this.el.mask('Guardando...');
            form.submit({
                url: App.url('modulos'),
                params: { action: 'save' },
                success: function(form, action){
                    this.el.unmask();
                    this.app.callback();

                    if(action.result.message)
                        Ext.Message.show('Aviso', action.result.message);
                    this.close();
                },
                failure: function(form, action){
                    this.el.unmask();
                    if(action.result.message) Ext.Msg.alert('Aviso', action.result.message);
                },scope: this
            });
        }else{
            Ext.Msg.alert('Aviso', 'Debe llenar los campos Requeridos.');
        }
    }
});