Ext.define('Wnd.Perfil', {
    extend: 'Ext.window.Window',
    title: 'Perfil', modal: true, border: false, width: 800, height: 600, layout: 'border',
    minimizable: false,
    app : { callback: Ext.emptyFn, permisos: [], recursos: [], grupos: [] },
    constructor: function(config){
        Ext.applyIf(config.app, this.app);

        config.items = [];
        config.items.push(this.get_form());
        config.items.push(Ext.create('Ext.tab.Panel',{
            region: 'center', maxTabWidth: 150, activeTab: 0, plain: true, border: true,
            items: [
            ]
        }));

        config.buttons = [
            ,{text: 'Cerrar', handler: function(){ this.close(); },scope: this}
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
                        { xtype: 'textfield', fieldLabel: 'Nombre', name: 'name', readOnly: true, columnWidth: 1, allowBlank: false },
                        { xtype: 'textfield', fieldLabel: 'Usuario', name: 'username', readOnly: true, columnWidth: 1, allowBlank: false },
                    ]
                },
                { xtype: 'hidden', name: 'uid', value: '' }
            ]
        });
    },load: function(){
        this.el.mask('Cargando...');
        this.form_panel.getForm().load({
            url: App.url('usuarios'),
            params: { action: 'profile' },
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
    }
});