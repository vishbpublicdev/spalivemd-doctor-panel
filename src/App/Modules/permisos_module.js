Ext.define('Admin.Tab.Permisos', {
    extend: 'AppController', name: 'Admin.Tab.Permisos',
    title: 'Permisos', layout: 'fit', id: 'tab_permisos', closable: true,
    constructor: function(config){
    	config.items = [];

    	config.items.push(this.__center());

    	this.callParent(arguments);
    },__center : function(){
        var obj_model_menu = Ext.define('ModelModulos', {
            extend: 'Ext.data.Model',
            fields: [
                { name: 'node_id', type: 'int'},
                { name: 'text', type: 'string'},
                { name: 'module', type: 'string'},
                { name: 'active', type: 'int'},
            ]
        });

        return this.treegrid = Ext.create('Ext.tree.Panel', {
            border: false, animate: true, rootVisible: false, bufferedRenderer: false, useArrows: true,
            store: new Ext.data.TreeStore({
                model: obj_model_menu,
                autoLoad: true,
                proxy: {
                    type: 'ajax',
                    url: App.url('permisos'),
                    extraParams: {
                        action: 'tree_grid',
                        show_id: true
                    }
                }//, folderSort: true
            }),
            viewConfig: {
                plugins: [ { ptype: 'treeviewdragdrop', containerScroll: true } ],
                listeners: {
                    drop: function( node, data, overModel, dropPosition, eOpts ){
                        this.move_node(data.records[0], dropPosition, overModel);
                    },scope: this
                }
            },
            bbar: [
                {
                    tooltip: 'Actualizar', iconCls: 'x-tbar-loading', scope: this,
                    handler:function(){ this.treegrid.getStore().reload(); },
                },
            ],
            columns: [
                { xtype: 'treecolumn', text: 'Nombre', width: 320, sortable: false, dataIndex: 'text',
                    editor: { xtype: 'textfield', selectOnFocus: true, allowOnlyWhitespace: false }
                },
                // { text: 'Módulo', width: 320, dataIndex: 'module', sortable: false },
                { text: 'Descripción', flex: 1, dataIndex: 'description', sortable: false },
                { text: 'Activo', width: 80, dataIndex: 'active', sortable: false, align: 'center', renderer: function (value, record) { return value == 1? 'Si' : 'No'; } },
                {
                    xtype: 'actioncolumn', width: 70, sortable: false, menuDisabled: true,
                    items: [
	                    { iconCls: 'x-fa fa-edit', tooltip: 'Editar', scope: this, handler: this.node_edit, isDisabled: function(view, rowIndex, colIndex, item, record){ return record.data.node_id == 1? true : false; } },
                        { iconCls: 'x-fa fa-trash-alt', tooltip: 'Eliminar', scope: this, handler: this.node_delete, isDisabled: function(view, rowIndex, colIndex, item, record){ return record.data.node_id == 1? true : false; } }
                    ]
                }
            ],
            listeners:{
                itemcontextmenu: function( obj, record, item, index, e, eOpts ){
                    e.preventDefault();

                    this.item_menu(record).showAt(e.getXY());
                },
                itemdblclick: function( view , record , item , index , e , eOpts ) {
                    this.node_edit(null, null, null, item, e, record );
                },
                scope: this
            }
        });
    },reload: function(){
        this.treegrid.store.load({
            node: this.treegrid.getRootNode(),
            callback: function(){
                this.treegrid.getRootNode().expand();
            },scope: this
        });
    },item_menu: function(record){
        if(!this.menu_item){
            this.menu_item = Ext.create('Ext.menu.Menu', { plain: true,
                items: [
                    { text: 'Agregar Subopción', type: 'task', scope: this, handler: this.node_new }, '-',
                    { text: 'Borrar Opción' }
                ]
            });
        }

        this.menu_item.selected_record = record;

        return this.menu_item;
    },move_node: function(drop_node, dropPosition, root_node){
        // console.log(drop_node.get('node_id'), dropPosition, root_node.get('node_id'));
        Ext.Ajax.request({
            url: App.url('permisos'),
            params: { action: 'move', uid: drop_node.get('uid'), drop_position: dropPosition, parent_uid: root_node.get('uid') },
            success: function(response){
                var json = Ext.decode(response.responseText);
                if(json.success){
                }
                if(json.message) Ext.Msg.alert('Error', json.message);
            }
        });
    },node_delete: function(view, rowIndex, colIndex, item, e, record, row){
		Ext.Msg.confirm('Eliminar', '¿Eliminar "' + record.get('text') + '"?', function(btn){
			if(btn == 'yes'){
		        if (record) {
		            this.el.mask('Eliminando...');
		            Ext.Ajax.request({
		                url: App.url('permisos'),
		                params: { action: 'delete', uid: record.get('uid') },
		                success: function(response){
		                    this.el.unmask();
		                    var json = Ext.decode(response.responseText);
		                    if(json.success){
								this.reload();
		                    }
		                    if(json.message) Ext.Msg.alert('Aviso', json.message);
		                },scope: this
		            });
		        }
			}
		}, this);
    },node_edit: function(view, rowIndex, colIndex, item, e, record, row){
        Ext.create('Wnd.Permiso',{
            app: {
                record: record,
                callback: Ext.bind(function(){ this.reload(); }, this)
            }
        }).show(e.target);
    },node_new: function(option_menu, e){
        Ext.create('Wnd.Permiso',{
            app: {
                parent_record: option_menu.parentMenu.selected_record,
                callback: Ext.bind(function(){ this.reload(); }, this)
            }
        }).show(e.target)
        // var record = this.treegrid.getSelectionModel().getSelection()[0];
        // (new Wnd.Permiso({
        //     app: {
        //         parent_record: record,
        //         callback: Ext.bind(function(){ this.reload(); }, this)
        //     }
        // })).show(null, function(){
        // 	this.down('hidden[name=Menu_ParentId]').setValue(record.get('node_id'));
    	// });
    }
});

Ext.define('Wnd.Permiso', {
    extend: 'Ext.window.Window',
    title: 'Permiso', modal: true, border: false, width: 650, height: 400, layout: 'fit', //closeAction: 'hide',
    minimizable: false,
    app : { record: null, parent_record: null, callback: function(){} },
    constructor: function(config){
        config.items = [];

        config.items.push(this.get_form());

        config.buttons = [
            ,{text: 'Guardar', handler: function(){ this.save(); },scope: this}
            ,{text: 'Cancelar', handler: function(){ this.close(); },scope: this}
        ];

        this.callParent(arguments);
    },afterRender: function() {
        this.callParent(arguments);

        if(this.app.parent_record)
            this.down('hidden[name=parent_uid]').setValue(this.app.parent_record.data.uid);

        this.load();
    },get_form: function(){
        var store = Ext.create('Ext.data.Store', {
            autoLoad: true, fields: ['id','name'],
            proxy: {
                type: 'ajax',
                url: App.url('modulos'),
                extraParams: { action: 'combobox' },
                reader: {
                    type:'json',
                    root: 'data'
                }
            },
            root: 'data',
        });

        return this.form_panel = Ext.create('Ext.form.Panel',{
            border: false, bodyStyle: 'background:#FFFFFF;', bodyPadding: 10, defaults: { margin: '0 0 0 0' },
            items:[
                {
                	xtype: 'fieldset', title: 'Detalles', layout:'column', defaults: { margin: '0 0 10 0', labelWidth:80 },
                	items: [
                        { xtype: 'textfield', fieldLabel: 'Nombre', name: 'name', columnWidth: 1, allowBlank: false },
                        // { xtype: 'combobox', fieldLabel: 'Permisos', name: 'module_uid', columnWidth: 1, allowBlank: true, queryMode: 'local', anchor: '100%', multiSelect: false, emptyText : '', store: store, displayField: 'name', valueField: 'uid', editable: false, triggerAction: 'all',
                            // listConfig : {
                            //     width: 250, minWidth: 250, getInnerTpl : function() {
                            //         return '<div class="x-combo-list-item"><img src="' + Ext.BLANK_IMAGE_URL + '" class="chkCombo-default-icon chkCombo" /> {name} </div>';
                            //     }
                            // }
                        // },
	                    { xtype: 'textareafield', fieldLabel: 'Descripción', name: 'description', columnWidth: 1, height: 100, allowBlank: true },
	                    { xtype: 'checkboxfield', fieldLabel: 'Activo', name: 'active', columnWidth: 1 }
                	]
                }
                ,{ xtype: 'hidden', name: 'uid', value: '' }
                ,{ xtype: 'hidden', name: 'parent_uid', value: '' }
            ]
        });
    },load: function(){
        if(!this.app.record) return;

        this.el.mask('Cargando...');
        this.form_panel.getForm().load({
            url: App.url('permisos'),
            params: { action: 'load', uid : this.app.record.get('uid') },
            success: function(form, action){
                this.el.unmask();

                var json = action.result;
                if(json.success){
                }
                if(json.msg) Ext.Msg.alert('Aviso', json.msg);
            },
            failure: function(form, action){
                this.el.unmask();
                if(action.result.msg) Ext.Msg.alert('Aviso', action.result.msg);
            },scope: this
        });
    },save: function(){
        var form = this.form_panel.getForm();
        if (form.isValid()){
            this.el.mask('Guardando...');
            form.submit({
                url: App.url('permisos'),
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