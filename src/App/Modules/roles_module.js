Ext.define('Admin.Tab.Roles', {
    extend: 'Ext.panel.Panel',
    name: 'Admin.Tab.Roles',
    id: 'tabRoles',
    title: 'Roles',
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
                { text:'Agregar Rol', iconCls:'x-fa fa-plus', closable: false, handler: this.node_new, scope: this }
            ],
            pageSize: 50, autoload: true, border: false, url : App.url('roles'), params : { action: 'grid' }, model:'Modulo', remoteSort: true,
            columns: [
                { text: 'UID', width: 45, dataIndex: 'uid', hidden: true, sortable: false, type: 'string' },
                { text: 'Nombre', dataIndex: 'title', sortable: false, width: 300, type: 'string' },
                { text: 'Descripción', dataIndex: 'detail', sortable: false, flex: 1, type: 'string' },
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
        Ext.create('Wnd.Rol',{
            title: 'Rol '+record.get('title'),
            app: {
                record: record,
                callback: Ext.bind(function(){ this.reload(); }, this),
            }
        }).show(e.target);
    },node_new: function(button, e){
        Ext.create('Wnd.Rol',{
            title: '*Nuevo Modulo',
            app: {
                Modulo_Uid: '',
                callback: Ext.bind(function(){ this.reload(); }, this),
            }
        }).show(e.target);
    },node_delete: function(grid, rowIndex, colIndex, item, e, record, row){
        Ext.Msg.confirm('Eliminar', '¿Desea eliminar el Rol "' + record.get('title') + '"?', function(btn){
            if(btn == 'yes'){
                if (record) {
                    this.el.mask('Eliminando...');
                    Ext.Ajax.request({
                        url: App.url('roles'),
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

Ext.define('Wnd.Rol', {
    extend: 'Ext.window.Window',
    title: 'Rol', modal: true, border: false, width: 800, height: 600, layout: 'border', //closeAction: 'hide',
    minimizable: false,
    app : { callback: Ext.emptyFn, permisos: [], recursos: [] },
    constructor: function(config){
        Ext.applyIf(config.app, this.app);

        config.items = [];
        config.items.push(this.get_form());
        config.items.push(Ext.create('Ext.tab.Panel',{
            region: 'center', maxTabWidth: 150, activeTab: 0, plain: true, border: true, //bodyStyle: 'border-top: 0 none; border-left: 0 none;',
            items: [
                this.tab_recursos(),
                this.tab_permisos(),
            ]
        }));

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
            region: 'west', width: 250, border: false, bodyStyle: 'background:#FFFFFF;', bodyPadding: 10, defaults: { margin: '0 0 0 0' },
            items:[
                {
                    xtype: 'fieldset', title: 'Detalles', layout:'column', defaults: { margin: '0 0 10 0', labelAlign: 'top', labelWidth:80 },
                    items: [
                        { xtype: 'textfield', fieldLabel: 'Nombre', name: 'name', columnWidth: 1, allowBlank: false },
                        { xtype: 'textareafield', fieldLabel: 'Descripción', name: 'description', columnWidth: 1, allowBlank: false },
                        { xtype: 'checkboxfield', fieldLabel: 'Activo', name: 'active', columnWidth: 1 }
                    ]
                }
                ,{ xtype: 'hidden', name: 'uid', value: '' }
            ]
        });
    },tab_recursos: function(){
        this.store_recursos = Ext.create('Ext.data.Store', {
            fields:['uid','title','subtitle'],
        });

        return this.panel_permisos = Ext.create('Ux.DataView',{
            title: 'Recursos', border: false, store: this.store_recursos, bbartool: null,
            tbar: [ { text: 'Agregar', handler: this.on_recursos, scope: this } ],
        });
    },tab_permisos: function(){
        this.store_permisos = Ext.create('Ext.data.Store', {
            fields:['uid','title','subtitle'],
        });

        return this.panel_permisos = Ext.create('Ux.DataView',{
            title: 'Permisos', border: false, store: this.store_permisos, bbartool: null,
            tbar: [ { text: 'Agregar', handler: this.on_permisos, scope: this } ],
        });
    },on_permisos: function(button, e){
        Ext.create('Wnd.Permisos',{
            app: {
                data: this.app.permisos,
                callback: Ext.bind(this.set_permisos, this)
            }
        }).show(e.target);
    },on_recursos: function(button, e){
        Ext.create('Wnd.Recursos',{
            app: {
                data: this.app.recursos,
                callback: Ext.bind(this.set_recursos, this)
            }
        }).show(e.target);
    },set_permisos: function(permisos){
        this.app.permisos = permisos;
        console.log('set_permisos',this.app.permisos);
        this.store_permisos.removeAll();
        this.store_permisos.add(this.app.permisos);

        this._permisos_humanize_store();
    },_permisos_humanize_store: function(){
        this.store_permisos.each(function(item){
            item.set('label', 'permiso:');

            var info1 = '-';
            switch(item.get('access_level')){
                case 'Deny': info1 = 'Denegado'; break;
                case 'Reader': info1 = 'Lector'; break;
                case 'Contributed': info1 = 'Contribuidor'; break;
                case 'Administrator': info1 = 'Administrador'; break;
                case 'Owner': info1 = 'Propietario'; break;
            }

            var info2 = '-';
            switch(item.get('access_resources')){
                case 'Assigned': info2 = 'Asignados'; break;
                case 'Own': info2 = 'Propios'; break;
                case 'Both': info2 = 'Asignados y Propios'; break;
            }

            item.set('info2', 'Como: <span>'+info1+'</span> de Recursos: <span>'+info2+'</span>');
        },this);
    },set_recursos: function(recursos){
        this.app.recursos = recursos;
        console.log('set_recursos',recursos);
        this.store_recursos.removeAll();
        this.store_recursos.add(this.app.recursos);

        this._recursos_humanize_store();
    },_recursos_humanize_store: function(){
        this.store_recursos.each(function(item){
            item.set('label', 'recurso: <span>'+item.get('model')+'</span>');

            // item.set('info2', 'Como: <span>'+info1+'</span> de Recursos: <span>'+info2+'</span>');
        },this);
    },load: function(record){
        if(!this.app.record) return;

        this.el.mask('Cargando...');
        this.form_panel.getForm().load({
            url: App.url('roles'),
            params: { action: 'load', uid : this.app.record.data.uid },
            success: function(form, action){
                this.el.unmask();

                var json = action.result;
                if(json.success){
                    this.set_permisos(json.data.permissions);
                    this.set_recursos(json.data.resources);
                }

                App.message(json.success, json.message);
            },
            failure: function(form, action){
                this.el.unmask();

                App.message(false, json.message);
            },scope: this
        });
    },save: function(){
        // Ext.create('Wnd.Usuario',{
        //     title: '*Nuevo Modulo2'
        // }).show(this);

        var recursos = [], item = null;
        for(var x = 0; x < this.app.recursos.length; x++){
            item = this.app.recursos[x];
            recursos.push({uid: item.uid, model: item.model });
        }

        var permisos = [], item = null;
        for(var x = 0; x < this.app.permisos.length; x++){
            item = this.app.permisos[x];
            permisos.push({uid: item.uid, access_level: item.access_level, access_resources: item.access_resources });
        }

        var form = this.form_panel.getForm();
        if (form.isValid()){
            this.el.mask('Guardando...');
            form.submit({
                url: App.url('roles'),
                params: { action: 'save', resources: Ext.encode(recursos), permissions: Ext.encode(permisos) },
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

Ext.define('Wnd.Recursos', {
    extend: 'Ext.window.Window',
    title: 'Recursos', modal: true, border: false, width: 800, height: 600, layout: 'border', //closeAction: 'hide',
    minimizable: false,
    app : { callback: Ext.emptyFn, data: [], selected_model: null },
    constructor: function(config){
        Ext.applyIf(config.app, this.app);

        config.tbar = [
            { xtype: 'uxcombobox', emptyText: 'Seleccione un Tipo de Recurso', url: App.url('recursos'), params: { action: 'combobox' }, width: 260, listeners: { select: this.on_select_model,  scope: this } },
            '->',
            { xtype: 'checkbox', type: 'all', labelStyle: 'text-align: right;', fieldLabel: '', hidden: true, width: 400, labelWidth: 380, listeners: { change: this.on_todos, scope: this } }
        ];

        config.items = [];
        config.items.push(this.__left());
        config.items.push(this.__center());

        config.buttons = [
            {text: 'Aceptar', handler: function(){ this.save(); },scope: this},
            {text: 'Cancelar', handler: function(){ this.close(); },scope: this}
        ];

        this.callParent(arguments);
    },afterRender: function() {
        this.callParent(arguments);

        this.down('searchfield').setStore(this.dataview_source.store);

        this._filter_destination();
        this.dataview_destination.store.add(this.app.data);

        this.dataview_source.store.on('load',function(){
            this._filter_source();
        },this);
    },__left : function(){
        return this.dataview_destination = Ext.create('Ux.DataView', {
            title: 'Recursos Asignados', region: 'west', width: 300, autoload: false, bbartool: null, url : App.url('recursos'), params: { action: 'grid' },
            emptyText: 'Sin datos para mostrar.', disabled: true, split: true,
            listeners:{
                itemdblclick: this.remove_resource,
                scope: this
            }
        });
    },__center : function(){
        return this.dataview_source = Ext.create('Ux.DataView', {
            title: 'Recursos', region: 'center', autoload: false, url : App.url('recursos'), params: { action: 'grid' },
            emptyText: 'Sin datos para mostrar.', disabled: true,
            tbar: [
                { xtype: 'searchfield', width: 200, hideLabel: true, emptyText: 'Buscar Recurso' },'->',
            ],listeners:{
                itemdblclick: this.add_resource,
                scope: this
            }
        });
    },on_todos: function(field, newValue, oldValue, eOpts){
        if(newValue == true){
            this.dataview_source.setDisabled(true);
            this.dataview_destination.setDisabled(true);
        }else{
            this.dataview_source.setDisabled(false);
            this.dataview_destination.setDisabled(false);
        }
    },remove_resource: function(view, record, item, index, e, eOpts){
        this.dataview_destination.store.remove(record);

        this._filter_source();
    },add_resource: function(view, record, item, index, e, eOpts){
        console.log('add_resource', record);
        this.dataview_destination.store.add({
            uid: record.data.uid,
            title: record.data.title,
            // subtitle: record.data.subtitle,
            name: '-',
            access: 'one',
            model: record.data.model,
        });

        this._filter_source();
    },_filter_source: function(){
        this.dataview_source.store.clearFilter();
        this.dataview_source.store.filterBy(function(item){
            return this.dataview_destination.store.find('uid',item.data.uid) == -1;
        },this);
    },_filter_destination: function(){
        this.dataview_destination.store.clearFilter();
        this.dataview_destination.store.filterBy(function(item){
            var model = this.app.selected_model? this.app.selected_model.data.model : null;
            return item.data.model == model;
        },this);
    },_find_all_selected: function(){
        var result = false;
        this.dataview_destination.store.findBy(function(record,id){
            console.log('_find_all_selected', record);
            if(record.get('model') == this.app.selected_model.get('model') && record.get('uid') == '_'){
                result = true;
                return true;
            }
        },this);

        if(result === true){
            this.down('checkbox[type=all]').setValue(true);
        }
    },on_select_model: function( field, record, eOpts){
        // console.log('selec', field.getValue());
        var value = field.getValue();

        if(value){
            var record = field.findRecordByValue(value);
            this.app.selected_model = record? record : null;

            this._filter_destination();
            this.dataview_source.load({
                uid: value
            });

            this.dataview_source.setDisabled(false);
            this.dataview_destination.setDisabled(false);

            this.down('checkbox[type=all]').setFieldLabel('Todos los Recursos de '+record.data.name);
            this.down('checkbox[type=all]').setHidden(false);

            this._find_all_selected();
        }else{
            this.app.selected_model = null;

            this.down('checkbox[type=all]').reset();
            this.down('checkbox[type=all]').setHidden(true);
            this.dataview_source.store.removeAll();
            this.dataview_source.setDisabled(true);
            // this.dataview_destination.store.removeAll();
            this.dataview_destination.setDisabled(true);
            this._filter_destination();
        }

    },save: function(){
        var result = [];

        if(this.down('checkbox[type=all]').getValue() === true){
            result.push({
                uid: '_',
                title: 'Todos los Recursos de: '+this.app.selected_model.data.name,
                model: this.app.selected_model.data.model,
                detail: '-',
                subtitle: '-',
                created: '-',
            });
        }else{
            this.dataview_destination.store.each(function(item){
                console.log('save',item);
                result.push({
                    uid: item.data.uid,
                    title: item.data.title,
                    model: item.data.model,
                    detail: '-',
                    subtitle: '-',
                    created: '-',
                });
            },this);
        }

        this.app.callback(result);
        this.close();
    }
});

Ext.define('Wnd.Permisos', {
    extend: 'Ext.window.Window',
    title: 'Permisos', modal: true, border: false, width: 800, height: 600, layout: 'fit', //closeAction: 'hide',
    minimizable: false,
    app : { callback: Ext.emptyFn, data: [] },
    constructor: function(config){
        Ext.applyIf(config.app, this.app);

        config.items = [];
        config.items.push(this.__center());

        config.buttons = [
            ,{text: 'Aceptar', handler: function(){ this.save(); },scope: this}
            ,{text: 'Cancelar', handler: function(){ this.close(); },scope: this}
        ];

        this.callParent(arguments);
    },afterRender: function() {
        this.callParent(arguments);
    },__center : function(){
        return this.tree_grid = Ext.create('Ext.tree.Panel', {
            cls: 'tree-permisos', border: false, rootVisible: false/*, checkPropagation: 'both'*/, useArrows: true, animate: true, bufferedRenderer: false,
            store: new Ext.data.TreeStore({
                autoLoad: true,
                proxy: { type: 'ajax', url: App.url('permisos'), extraParams: { action: 'tree_grid', checkbox: true } },
                listeners: {
                    load: function(store, records, successful, operation, node, eOpts){
                        this.select_permissions();
                    },scope: this
                }
            }),
            viewConfig: {
                stripeRows: true, disableSelection: true, // enableTextSelection: false,// markDirty: false
            },
            columns: [
                { xtype: 'treecolumn', text: 'Nombre', width: 400, sortable: false, dataIndex: 'text',
                    editor: { xtype: 'textfield', selectOnFocus: true, allowOnlyWhitespace: false }
                },
                // { text: 'Descripción', flex: 1, dataIndex: 'description', sortable: false },
                {
                    text: 'Acceso', width: 180, xtype: 'widgetcolumn',
                    widget: {
                        xtype: 'combo', cls:'combo-access', displayField: 'name', valueField: 'uid', editable: false, bind: '{record.access_level}', allowBlank: true, disabled: true,
                        store: [
                            {uid: 'Deny', name: 'Denegado'},
                            {uid: 'Reader', name: 'Lectura'},
                            {uid: 'Contributed', name: 'Contribuyente'},
                            {uid: 'Administrator', name: 'Administrador'},
                        ]
                    }
                },
                {
                    text: 'Recursos', width: 180, xtype: 'widgetcolumn',
                    widget: {
                        xtype: 'combo', cls:'combo-resource', displayField: 'name', valueField: 'uid', editable: false, bind: '{record.access_resources}', allowBlank: true, disabled: true,
                        store: [
                            {uid: 'Assigned', name: 'Asignados'},
                            {uid: 'Own', name: 'Propios'},
                            {uid: 'Both', name: 'Ambos'},
                        ]
                    }
                },
                // { text: 'Módulo', width: 320, dataIndex: 'module', sortable: false },
            ],
            listeners:{
                checkchange: this.checkchange,
                beforeitemexpand: this.beforeitemexpand,
                beforeitemcollapse: this.beforeitemcollapse,
                beforecheckchange: this.before_checkchange,
                // itemcontextmenu: function( obj, record, item, index, e, eOpts ){
                //     e.preventDefault();

                //     this.item_menu(record).showAt(e.getXY());
                // },
                scope: this
            }
        });
    },before_checkchange: function(record, checked, e, eOpts){
        var view = this.tree_grid.getView();
        // console.log('before_checkchange',record, checked);
        // if (record.get('text') === 'Take a nap' && !checkedState) {
        //     Ext.toast('No rest for the weary!', null, 't');

        //     return false;
        // }
        if(record.get('status') == 'default'){
            return false;
        }

        this._set_node_status(view, record, !checked);

        return true;
    },beforeitemcollapse: function(record, eOpts){
        return false;
    },beforeitemexpand: function(record, eOpts){
        return false;
    },checkchange: function(record, checked, e, eOpts){
        this._set_row_disabled(record, checked);
    },_set_row_disabled: function(record, checked){
        record.set('status', checked? 'check' : 'uncheck');

        var view = this.tree_grid.getView();

        var combo_access = view.getCell(record, 1, true).query('.x-form-item')[0].id;
        Ext.getCmp(combo_access).setDisabled(!checked);
        if(!record.data.access_level) Ext.getCmp(combo_access).setValue('Contributed');

        var combo_access = view.getCell(record, 2, true).query('.x-form-item')[0].id;
        Ext.getCmp(combo_access).setDisabled(!checked);
        if(!record.data.access_resources)  Ext.getCmp(combo_access).setValue('Own');
    },_set_node_status: function(view, record, checked){
        record.eachChild(function(item){
            item.set('status', checked? 'default' : 'uncheck');

            if(checked){
                Ext.fly(view.getRow(item)).addCls('ux-item-disabled');
            }else{
                Ext.fly(view.getRow(item)).removeCls('ux-item-disabled');
            }

            this._set_node_status(view, item, checked);
        },this);
    },select_permissions: function(){
        if(this.app.data.length == 0) return;

        var view = this.tree_grid.getView();

        var item = null, record = null;

        // console.log('root',this.tree_grid.getRootNode());
        for(var x = 0; x < this.app.data.length; x++){
            item = this.app.data[x];

            record = this.tree_grid.getRootNode().findChild('uid', item.uid, true);

            record.set('checked',true);
            record.set('access_level',item.access_level);
            record.set('access_resources',item.access_resources);

            this._set_row_disabled(record, true);
            this._set_node_status(view,record, true);
        }
    },_get_selected: function(node){
        var result = [], item = null;
        if(node.data.checked === true){
            result.push({
                uid: node.get('uid'),
                title: node.get('text'),
                subtitle: node.get('access_level') + ' - ' + node.get('access_resources'),
                detail: node.get('description'),
                access_level: node.get('access_level'),
                access_resources: node.get('access_resources')
            });
        }else{
            for(var x = 0; x < node.childNodes.length; x++){
                result = result.concat(this._get_selected(node.childNodes[x]));
            }
        }
        return result;
    },save: function(){
        var permisos = this._get_selected(this.tree_grid.getRootNode()),
            item = null,
            error = false;

        for(var x = 0; x < permisos.length; x++){
            item = permisos[x];

            if(!item.access_level || !item.access_resources){
                error = true;
            }
        }

        if(error == false){
            this.app.callback(permisos);
            this.close();
        }else{
            App.toast(false, 'Campos incompletos!');
        }
    }
});