Ext.define('Admin.Tab.Usuarios', {
    extend: 'Ext.panel.Panel',
    name: 'Admin.Tab.Usuarios',
    id: 'tabUsuarios',
    title: 'Usuarios',
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
                { text:'Agregar Usuario', iconCls:'x-fa fa-plus', closable: false, handler: this.node_new, scope: this }
            ],
            pageSize: 50, autoload: true, border: false, url : App.url('usuarios'), params : { action: 'grid' }, model:'Modulo', remoteSort: true,
            columns: [
                { text: 'UID', width: 45, dataIndex: 'uid', hidden: true, sortable: false, type: 'string' },
                { text: 'Usuario', dataIndex: 'username', sortable: false, width: 200, type: 'string' },
                { text: 'Nombre', dataIndex: 'name', sortable: false, width: 300, type: 'string' },
                { text: 'Ultimo Acceso', dataIndex: 'last_login', sortable: false, width: 300, xtype: 'datecolumn',  format:'d-m-Y H:i:s' },
                { text: 'Grupos', dataIndex: 'groups', sortable: false, flex: 1, type: 'string' },
                { text: 'Activo', width: 80, dataIndex: 'active', sortable: false, align: 'center', renderer: function (value, record) { return value == 1? 'Si' : 'No'; } },
                {
                    xtype: 'actioncolumn', width: 120, sortable: false,
                    items: [
                        { iconCls: 'x-fa fa-key', tooltip: 'Cambiar Contraseña', scope: this, handler: this.on_change_password },
                        { iconCls: 'x-fa fa-edit', tooltip: 'Editar Registro', scope: this, handler: this.node_edit },
                        { iconCls: 'x-fa fa-trash-alt', tooltip: 'Eliminar Registro', scope: this, handler: this.node_delete },
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
    },reload: function(callback){
        this.grid.reload(callback);
    },node_edit: function(grid, rowIndex, colIndex, item, e, record, row){
        Ext.create('Wnd.Usuario',{
            title: 'Usuario: '+record.get('name'),
            app: {
                record: record,
                callback: Ext.bind(function(){ this.reload(); }, this),
            }
        }).show(e.target);
    },change_password: function(record, e){
        if(!record) return;

        Ext.create('Wnd.ChangePassword',{
            app: {
                record: record,
                callback: Ext.bind(function(){ this.reload(); }, this),
            }
        }).show(e? e.target : null);
    },node_new: function(button, e){
        Ext.create('Wnd.Usuario',{
            title: '*Nuevo Usuario',
            app: {
                callback: Ext.bind(function(json){
                    this.reload(Ext.bind(function(records, operation, success){
                        var record = records.find(function(item){
                            return  item.get('uid') == json.uid;
                        },this);

                        this.change_password(record);
                    },this));
                }, this),
            }
        }).show(button);
    },node_delete: function(grid, rowIndex, colIndex, item, e, record, row){
        Ext.Msg.confirm('Eliminar', '¿Desea eliminar el Usuario "' + record.get('name') + '"?', function(btn){
            if(btn == 'yes'){
                if (record) {
                    this.el.mask('Eliminando...');
                    Ext.Ajax.request({
                        url: App.url('usuarios'),
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
    },on_change_password: function(grid, rowIndex, colIndex, item, e, record, row){
        this.change_password(record, e);
    }
});


Ext.define('Wnd.ChangePassword', {
    extend: 'Ext.window.Window',
    title: 'Change password', modal: true, border: false, width: 400, height: 400, layout: 'fit', //closeAction: 'hide',
    minimizable: false,
    app : { callback: Ext.emptyFn, permisos: [], recursos: [], grupos: [] },
    constructor: function(config){
        Ext.applyIf(config.app, this.app);

        config.items = [];
        config.items.push(this.get_form());

        config.buttons = [
            ,{text: 'Save', handler: function(){ this.save(); },scope: this}
            ,{text: 'Cancel', handler: function(){ this.close(); },scope: this}
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
                    xtype: 'fieldset', title: 'Details', layout:'column', defaults: { margin: '0 0 10 0', labelAlign: 'top', labelWidth:80 },
                    items: [
                        { xtype: 'textfield', fieldLabel: 'Actual Password', name: 'password', inputType: 'password', columnWidth: 1, allowBlank: false, },
                        { xtype: 'textfield', fieldLabel: 'New password', name: 'password_new', inputType: 'password', columnWidth: 1, allowBlank: false, },
                        { xtype: 'textfield', fieldLabel: 'Confirm new password', name: 'password_confirm', inputType: 'password', columnWidth: 1, allowBlank: false, },
                    ]
                },
                { xtype: 'hidden', name: 'uid', value: '' }
            ]
        });
    },load: function(){
        if(!this.app.record) return;

        this.down('hidden[name=uid]').setValue(this.app.record.data.uid);
        this.down('textfield[name=username]').setValue(this.app.record.data.username);
        this.down('textfield[name=password]').setValue(this.generate_password());
    },generate_password: function(){
        var length = 8,
            charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789",
            retVal = "";
        for (var i = 0, n = charset.length; i < length; ++i) {
            retVal += charset.charAt(Math.floor(Math.random() * n));
        }
        return retVal;
    },save: function(){


        var form = this.form_panel.getForm();
        if (form.isValid()){
            this.el.mask('Wait...');
            form.submit({
                url: App.url('usuarios'),
                params: { action: 'password' },
                success: function(form, action){
                    this.el.unmask();
                    this.app.callback();

                    if(action.result.message)
                        Ext.Message.show('Alert', action.result.message);
                    this.close();
                },
                failure: function(form, action){
                    this.el.unmask();
                    // if(action.result.message) Ext.Msg.alert('Aviso', action.result.message);
                },scope: this
            });
        }else{
            Ext.Msg.alert('Alert', 'Please fill all fields.');
        }
    }
});

Ext.define('Wnd.Usuario', {
    extend: 'Ext.window.Window',
    title: 'Usuario', modal: true, border: false, width: 800, height: 600, layout: 'border', //closeAction: 'hide',
    minimizable: false,
    app : { callback: Ext.emptyFn, permisos: [], recursos: [], grupos: [] },
    constructor: function(config){
        Ext.applyIf(config.app, this.app);

        config.items = [];
        config.items.push(this.get_form());
        config.items.push(Ext.create('Ext.tab.Panel',{
            region: 'center', maxTabWidth: 150, activeTab: 0, plain: true, border: true, //bodyStyle: 'border-top: 0 none; border-left: 0 none;',
            items: [
                this.tab_grupos(),
                this.tab_recursos(),
                this.tab_permisos(),
                // this.__src_tab_permisos(),
                // Ext.create('Ext.panel.Panel', {title: 'Log', html: 'Aún no implemtando.', padding: 10})
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
            region: 'west', width: 250, border: false, bodyStyle: 'background:#FFFFFF;', bodyPadding: 10, defaults: { margin: '0 0 0 0' }, split: true,
            items:[
                {
                    xtype: 'fieldset', title: 'Detalles', layout:'column', defaults: { margin: '0 0 10 0', labelAlign: 'top', labelWidth:80 },
                    items: [
                        { xtype: 'textfield', fieldLabel: 'Nombre', name: 'name', columnWidth: 1, allowBlank: false },
                        { xtype: 'textfield', fieldLabel: 'Usuario', name: 'username', columnWidth: 1, allowBlank: false },
                        { xtype: 'checkboxfield', fieldLabel: 'Activo', name: 'active', columnWidth: 1 }
                    ]
                },
                { xtype: 'hidden', name: 'uid', value: '' }
            ]
        });
    },tab_grupos: function(){
        this.store_grupos = Ext.create('Ext.data.Store', {
            fields:['uid','title','subtitle'],
        });

        return this.panel_grupos = Ext.create('Ux.DataView',{
            title: 'Grupos', border: false, store: this.store_grupos, bbartool: null,
            tbar: [ { text: 'Agregar', handler: this.on_grupos, scope: this } ],
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
    },set_grupos: function(grupos){
        this.app.grupos = grupos;
        console.log('set_grupos',this.app.grupos);

        this.store_grupos.removeAll();
        this.store_grupos.add(this.app.grupos);

        this._grupo_humanize_store();

        this.set_permisos();
        this.set_recursos();
    },_grupo_humanize_store: function(){
        this.store_grupos.each(function(item){
            item.set('label', 'grupo:');

            // item.set('info2', 'Como: <span>'+info1+'</span> de Recursos: <span>'+info2+'</span>');
        },this);
    },set_permisos: function(permisos){
        if(Ext.isDefined(permisos)){
            this.app.permisos = permisos;
        }
        console.log('set_permisos',this.app.permisos);

        this.store_permisos.removeAll();

        this.store_permisos.add(this.app.permisos);

        this.app.grupos.forEach(function(item){
            // console.log('grupos',item);
            if(item.permissions.length > 0){
                for(var x = 0; x < item.permissions.length; x++){
                    item.permissions[x].info2 = 'Grupo: <span>'+item.title+'</span>';
                }
                // console.log('for',item.permissions);
                this.store_permisos.add(item.permissions);
            }
        },this);

        this._permisos_humanize_store();
    },_permisos_humanize_store: function(){
        this.store_permisos.each(function(item){
            item.set('label', 'permiso');

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

            item.set('info1', 'Como: <span>'+info1+'</span> de Recursos: <span>'+info2+'</span>');
        },this);
    },set_recursos: function(recursos){
        if(Ext.isDefined(recursos)){
            this.app.recursos = recursos;
        }
        console.log('set_recursos',this.app.recursos);
        // console.log('recursos',recursos);
        this.store_recursos.removeAll();
        this.store_recursos.add(this.app.recursos);

        this.app.grupos.forEach(function(item){
            // console.log('grupos',item);
            if(item.resources.length > 0){
                for(var x = 0; x < item.resources.length; x++){
                    item.resources[x].info2 = 'Grupo: <span>'+item.title+'</span>';
                }

                this.store_recursos.add(item.resources);
            }
        },this);

        this._recursos_humanize_store();
    },_recursos_humanize_store: function(){
        this.store_recursos.each(function(item){
            item.set('label', 'recurso: <span>'+item.get('model')+'</span>');

            // item.set('info2', 'Como: <span>'+info1+'</span> de Recursos: <span>'+info2+'</span>');
        },this);
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
    },on_grupos: function(button, e){
        Ext.create('Wnd.Grupos',{
            app: {
                data: this.app.grupos,
                callback: Ext.bind(this.set_grupos, this)
            }
        }).show(e.target);
    },load: function(record){
        if(!this.app.record) return;

        this.el.mask('Cargando...');
        this.form_panel.getForm().load({
            url: App.url('usuarios'),
            params: { action: 'load', uid : this.app.record.data.uid },
            success: function(form, action){
                this.el.unmask();

                var json = action.result;
                if(json.success){
                    this.set_grupos(json.data.groups);
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

        var grupos = [], item = null;
        for(var x = 0; x < this.app.grupos.length; x++){
            item = this.app.grupos[x];
            grupos.push({uid: item.uid});
        }

        var form = this.form_panel.getForm();
        if (form.isValid()){
            this.el.mask('Guardando...');
            form.submit({
                url: App.url('usuarios'),
                params: { action: 'save', resources: Ext.encode(recursos), permissions: Ext.encode(permisos), groups: Ext.encode(grupos) },
                success: function(form, action){
                    this.el.unmask();

                    this.app.callback(action.result);

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

Ext.define('Wnd.Grupos', {
    extend: 'Ext.window.Window',
    title: 'Grupos', modal: true, border: false, width: 800, height: 600, layout: 'border', //closeAction: 'hide',
    minimizable: false,
    app : { callback: Ext.emptyFn, data: [], selected_model: null },
    constructor: function(config){
        Ext.applyIf(config.app, this.app);

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
            title: 'Grupos Asignados', region: 'west', width: 300, autoload: false, pager: false, url : App.url('recursos'), params: { action: 'grid' },
            emptyText: 'Sin datos para mostrar.', split: true,
            listeners:{
                itemdblclick: this.remove_resource,
                scope: this
            }
        });
    },__center : function(){
        return this.dataview_source = Ext.create('Ux.DataView', {
            title: 'Grupos', region: 'center', autoload: true, pager: false, url : App.url('roles'), params: { action: 'grid' },
            emptyText: 'Sin datos para mostrar.',
            tbar: [
                // ,'->',
                { xtype: 'searchfield', width: 200, hideLabel: true, emptyText: 'Buscar' },
            ],listeners:{
                itemdblclick: this.add_group,
                scope: this
            }
        });
    },__getTemplate : function(){
        return new Ext.XTemplate(
            '<tpl for=".">',
                '<div class="thumb-wrap">',
                    '<div class="content">',
                        '<h4>{name}</h4>',
                        '<p>{description}</p>',
                        '<p style="text-align: left;">{subtitle}</p>',
                        '<p style="text-align: right;">{created}</p>',
                    '</div>',
                '</div>',
            '</tpl>'
        );
    },remove_resource: function(view, record, item, index, e, eOpts){
        this.dataview_destination.store.remove(record);

        this._filter_source();
    },add_group: function(view, record, item, index, e, eOpts){
        this.dataview_destination.store.add({
            uid: record.data.uid,
            title: record.data.title,
            detail: record.data.detail,
            permissions: record.data.permissions,
            resources: record.data.resources,
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
            return item.data.model == this.app.selected_model;
        },this);
    },on_change_model: function( field, newValue, oldValue, eOpts){
        var record = field.findRecordByValue(field.getValue());
        this.app.selected_model = record? record.data.model : null;

        this._filter_destination();

        this.dataview_source.load({
            uid: newValue
        });
    },save: function(){
        var result = [];

        this.dataview_destination.store.each(function(item){
            console.log('save',item);
            result.push({
                uid: item.data.uid,
                title: item.data.title,
                detail: item.data.detail,
                permissions: item.data.permissions,
                resources: item.data.resources,
                subtitle: '-',
                created: '-',
            });
        },this);

        this.app.callback(result);
        this.close();
    }
});